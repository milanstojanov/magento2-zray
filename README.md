Z-Ray-Magento
=============

The Z-Ray tool from Zend Server adds to your Magento instance a toolbar with powerful features for profiling your PHP files. 

Using the Z-Ray, you can inspect, debug, and optimize your pages, and easily add additional functionality. Using the built-in hooks, you can tap into Z-Ray's tracking mechanism, add new Z-Ray panels, and extend the information displayed in existing Z-Ray panels.

Magento2 Z-Ray plugin represented in this repository extends profiling data available in the tool by adding information about Magento specific instances: modules, blocks, plugins, events and observers.

Using the Magento2 Z-Ray plugin you can track heavy constructors, blocks rendering calls, behavior extension by plugins, number of observers listening to specific event and time on their execution.

![ScreenShot](/doc/screenshots/magento2-zray_fullscreen.png)

Installation
------------
**Step 1**: Install the latest version of [Zend Server](http://www.zend.com/en/products/server/downloads).

[Getting Started Guide with Zend Server](http://files.zend.com/help/Zend-Server-IBMi/content/getting_started_with_z-ray.htm)

* Zend Server with PHP 5.6 version is recommended as has been tests for compatibility with Magento.
* Pay attention that Zend Server requires license data, though 30 days trial is available.

**Step 2**: In the Zend Server plugins directory create a ```magento2``` directory and add the contents of this repo.

Example: (assuming default install directory for Zend Server is ```/usr/local/zend/```)

```
    /usr/local/zend/var/plugins/magento2
```

NOTE: The directory and file names of zray/zray.php must not be changed. The name for logo.png is arbitary. It is specified in zray.php as follows:

```
    $zrayMagento->getZRay()->setMetadata(array(
        'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
    ));
```

**Step 3**: Edit zray/zray.php to set the absolute path of your Magento installation directory to the MAGENTO_PATH constant.

```
    define('MAGENTO_PATH', '/var/www/html/magento2');
```

**Step 4**: Open Magento in a browser and the Z-Ray toolbar appears at the bottom of the page. 

Data available in the Magento 2 Z-Ray plugin
------------
- **Events**: lists all the Magento events triggered by the request. For each event next information is available: event's name, class, method and target, as well as how long the event lasted.

![ScreenShot](/doc/screenshots/events_tab.png)

- **Rendered Blocks**: lists all rendered blocks involved in current page build process, including performance profiling.
- **Blocks**: outlines all the Magento blocks used on the page, including information on their template and class.

![ScreenShot](/doc/screenshots/blocks_tab.png)

- **Plugins**: lists all plugins created during the request processing with mapping to intercepted class.

![ScreenShot](/doc/screenshots/plugins_tab.png)

- **Intercepted methods**: full information on intercepted class/method with list of its plugins and time on their execution.

![ScreenShot](/doc/screenshots/intercepted_methods_tab.png)

- **Created objects**: lists all objects created on the page with time spent in constructor.
- **Observers**: all observers registered in configuration with ability to filter by area.
- **Modules**: lists Magento installed modules.

More Information
------------

- [Z-Ray on Zend.com](http://www.zend.com/en/products/server/z-ray)
- [Z-Ray documentation](http://files.zend.com/help/Zend-Server/zend-server.htm#z-ray_concept.htm)
- [Z-Ray extension API](https://github.com/zend-server-plugins/Documentation)
- [Z-Ray plugin for Magento1](https://github.com/zend-server-extensions/Z-Ray-Magento)
- [Up And Running With Z-Ray For Magento2 (Complete installation guide)](http://mageclass.com/up-and-running-with-zray-for-magento2/)
