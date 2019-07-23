<?php
/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 8-10-2018
 * Time: 09:06
 */

namespace app\components;

use Exception;
use frontend\components\MainMenu;
use Yii;
use yii\base\Event;
use yii\rbac\Item;
use yii\rbac\ManagerInterface;
use niolab\settings\models\enumerables\SettingType;
use niolab\settings\models\SettingModel;

class Module extends \yii\base\Module {

    protected $name = "Base Module";

    public function getName() {
        return Yii::t('app', $this->name);
    }

    private static function getCacheId($key) {
        return 'module_cache_' . get_called_class() . '_' . $key;
    }

    /**
     * Returns a list with routing rules.
     *
     * @return array
     */
    protected static function getUrlRules() {
        return [];
    }

    /**
     * Returns a list with event handlers.
     *
     * @return array [
     *   eventName => [
     *     handler 1,
     *     handler 2,
     *     ...
     *   ]
     * ]
     */
    protected static function getEventHandlers() {
        return [
            [
                'class' => MainMenu::class,
                'event' => MainMenu::EVENT_MAINMENU_REGISTER,
                'callback' => [static::class, 'onMainMenuRegister']
            ]
        ];
    }


    /**
     * Returns a list with RBAC roles and/or permissions to create
     *
     * @return array [
     *   [
     *     type => (null|yii\rbac\Item::TYPE_PERMISSION|yii\rbac\Item::TYPE_ROLE),
     *     name => name,
     *     description => description
     *     children => [...],
     *   ],
     *   ...
     * ]
     */
    protected static function getAuthItems() {
        return [];
    }

    /**
     * @return array
     */
    protected static function getMainMenuItems() {
        return [];
    }

    /**
     * @return array
     */
    public static function getSettings() {
        return [];
    }

    public static function onMainMenuRegister(Event $event) {
        Yii::debug('Register main menu @ ' . get_called_class());
        foreach (static::getMainMenuItems() as $menuItem) {
            $event->sender->addItem($menuItem);
        }
    }


    public static function registerEventHandlers() {
        $eventHandlers = static::getEventHandlers();
        foreach ($eventHandlers as $event => $conf) {
            if (is_array($conf) && isset($conf['class'])) {
                Event::on($conf['class'], $conf['event'], $conf['callback']);
            } else {
                Yii::$app->on($event, $conf);
            }
        }
    }

    public static function registerUrlRules() {
        $urlRules = static::getUrlRules();
        Yii::$app->urlManager->addRules($urlRules);
    }

    public static function registerSettings() {
        if (!Yii::$app->has('settings')) return;

        /** @var \niolab\settings\components\Settings $settingsComponent */
        $settingsComponent = Yii::$app->settings;

        $allSettings = static::getSettings();
        $invalidateCache = false;

        foreach ($allSettings as $section => $settings) {
            foreach ($settings as $key => $setting) {
                $model = SettingModel::find()->where(['section'=>$section,'key'=>$key])->one();
                if ($model === null) {
                    $model = new SettingModel();
                    $model->section = $section;
                    $model->key = $key;
                    $model->value = $setting['defaultValue'] ?? $setting['default'] ?? null;
                    if ($model->value !== null) {
                        $model->value = strval($model->value);
                    }
                }
                $model->type = $setting['type'] ?? SettingType::STRING_TYPE;
                $model->input = $setting['input'] ?? null;
                $model->description = $setting['label'] ?? $setting['description'] ?? null;
                if (!$model->save()) {
                    throw new Exception(serialize($model->getErrors()));
                }
                $invalidateCache = true;
            }
        }
        if ($invalidateCache) {
            $settingsComponent->invalidateCache();
        }
    }

    public static function registerRbac(ManagerInterface $manager) {
        $items = static::getAuthItems();
        $hash = md5(serialize($items));

        if (Yii::$app->cache->get(self::getCacheId('rbac')) !== $hash) {

            $userIds = null;

            foreach ($items as $item) {
                $isNewItem = false;


                /** @var $authItem yii\rbac\Item */
                if (!isset($item['type']) || $item['type'] == Item::TYPE_PERMISSION) {
                    $authItem = $manager->getPermission($item['name']);
                    if ($authItem === null) {
                        $isNewItem = true;
                        $authItem = $manager->createPermission($item['name']);
                    }
                } elseif ($item['type'] == Item::TYPE_ROLE) {
                    $authItem = $manager->getRole($item['name']);
                    if ($authItem === null) {
                        $isNewItem = true;
                        $authItem = $manager->createRole($item['name']);
                    }
                }

                if (isset($item['description'])) {
                    $authItem->description = $item['description'];
                }
                if (isset($item['ruleName'])) {
                    $authItem->ruleName = $item['ruleName'];
                }

                /* Get current users assigned with this role/permission, to reassign them later */
//                $currentAssignments = $auth->getUserIdsByRole($item['name']);

                if ($isNewItem) {
                    $manager->add($authItem);
                } else {
                    $manager->update($item['name'],$authItem);
                }


                /* Re-add the parents */
//                foreach ($currentChildItems as $childItem) {
//                    $auth->addChild($authItem,$childItem);
//                }

                if (isset($item['children'])) {
                    foreach ($item['children'] as $childItem) {
                        if (!$manager->hasChild($authItem,$childItem)) {
                            $manager->addChild($authItem, $childItem);
                        }
                    }
                }

                $admin = $manager->getRole('admin');
                if (!$manager->hasChild($admin,$authItem)) {
                    $manager->addChild($admin,$authItem);
                }
            }
            Yii::$app->cache->set(self::getCacheId('rbac'), $hash);
        }

    }
}