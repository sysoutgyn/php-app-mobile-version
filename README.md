# PHP App Mobile Version

Installation
------------

The recommended way to install AppMobileVersion is through Composer.

```bash
composer require sysout/php-app-mobile-version
```
Documentation
-------------

to use the whole appversion library, you should follow the following structure in laravel:

```php
$options = [
    'bundleId' => 'package id',
    'useCache' => true,
    'cacheFilePath' => '/var/www/mobile-version.json',
    'cachePeriod' => 3600
];

$api= new AppMobileVersion($options);

$data =[
    $androidVersion = 'android: '.$api->getAndroid(),
    $iosVersion = 'ios: '.$api->getIos()
];

echo $data;
```


