<?php


/**
 * Created by PhpStorm.
 * User: Lenovo
 * Date: 5-10-2018
 * Time: 16:06
 */

namespace app\components;

use common\components\Module;
use common\models\Tenant;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;

class ModuleLoader implements BootstrapInterface {

    public $cacheId = 'modules_config'; //@TODO per tenant!!!

    /**
     * @param \yii\base\Application $app
     * @throws InvalidConfigException
     */
    public function bootstrap($app) {
        Yii::beginProfile('Module Loader',__CLASS__);
        $this->registerModules();
        Yii::endProfile('Module Loader',__CLASS__);
    }

    protected function getModules() {
        Yii::beginProfile('Get modules',__CLASS__);
        $cacheId = Tenant::getCurrentTenant()->id.'-modules-v1';

        $modules = Yii::$app->cache->getOrSet($cacheId,function() {
            return Tenant::getCurrentTenant()->getEnabledModules();
        },3600);

        Yii::endProfile('Get modules',__CLASS__);
        Yii::info("Modules enabled: ".join(',',array_keys($modules)),__CLASS__);
        return $modules;
    }

    protected function registerModules() {
        $modules = $this->getModules();
        foreach ($modules as $moduleId => $class) {
            $config = [
                'class' => $class,
            ];
            $this->register($moduleId,$config);
        }
    }


    /**
     * Registers a module
     *
     * @param string $id the module id
     * @param array $config the module configuration (config.php)
     */
    protected function register($id, $config) {
        // Register Yii Module
        Yii::beginProfile('Register modules: set module', __CLASS__);
        Yii::$app->setModule($id, $config);
        Yii::endProfile('Register modules: set module', __CLASS__);

        /** @var Module $class */
        $class = $config['class'];

        Yii::beginProfile('Register modules: register settings', __CLASS__);
        $class::registerSettings();
        Yii::endProfile('Register modules: register settings', __CLASS__);

        Yii::beginProfile('Register modules: register events', __CLASS__);
        $class::registerEventHandlers();
        Yii::endProfile('Register modules: register events', __CLASS__);

        Yii::beginProfile('Register modules: register URL routes', __CLASS__);
        $class::registerUrlRules();
        Yii::endProfile('Register modules: register URL routes', __CLASS__);

        Yii::beginProfile('Register modules: register RBAC rules', __CLASS__);
        $class::registerRbac(Yii::$app->authManager);
        Yii::endProfile('Register modules: register RBAC rules', __CLASS__);

    }

}