# yii2-module-loader
Dynamic modules in Yii2


Currently just sample code.

`ModuleLoader` should be added your bootstrap.

At some point in ModuleLoader `getModules()` is called. It should return an array with module classes:
```
[
 "moduleId' => "app\modules\YourModule",
 ...
]
```

Your modules should extend from `Module`.

`MainMenu` is a way to have modules register their menu items for your views.
