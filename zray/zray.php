<?php
/**
 * Magento2 Z-Ray Extension
 * Version: 1.0
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2;

/**
 * Full magento project path
 */
define('MAGENTO_PATH', 'C:\web\Zend\Apache2\htdocs\magento2');

use Magento\Framework\App\Filesystem\DirectoryList;


/**
 * Class Magento
 */
class Magento
{

    /**
     * @var array
     */
    private $pluginsPrefix = [1 => 'before', 2 => 'around', 4 => 'after'];

    /**
     * @var array
     */
    private $registeredEvents = [];

    /**
     * @var array
     */
    private $pluginsInfo = [];

    /**
     * @var array
     */
    private $interceptedMethods = [];

    /**
     * @var \ZRayExtension
     */
    private $zray = null;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager = null;

    /**
     * @var array
     */
    private $observersProfiles = [];

    /**
     * @var array
     */
    private $cacheIndexPlugins = [];

    /**
     * @param \ZRayExtension $zray
     * @return void
     */
    public function setZRay($zray)
    {
        $this->zray = $zray;
    }

    /**
     * @return \ZRayExtension
     */
    public function getZRay()
    {
        return $this->zray;
    }

    /**
     * Collect info about blocks
     *
     * @param array $context
     * @param array $storage
     * @return void
     */
    public function collectLayoutBlocks($context, &$storage)
    {
        $layout = $this->getNotPublicProperty($context['this'], 'layout');

        $blocks_count = 0;
        $blocks = [];
        $ptrs = [];
        $layoutBlocks = $layout->getAllBlocks();

        foreach ($layoutBlocks as $key => $block) {
            $blockStruct = [];
            $blockStruct['class'] = get_class($block);

            $blockStruct['classFile'] = $this->getClassFile($blockStruct['class']);

            $blockStruct['layout_name'] = $block->getNameInLayout();
            $blockStruct['blocks'] = [];
            if (method_exists($block, 'getTemplateFile')) {
                $blockStruct['template'] = $block->getTemplate();
                $blockStruct['templateFile'] = $block->getTemplateFile();
                if (!is_file($blockStruct['templateFile'])) {
                    unset($blockStruct['templateFile']);
                }
            } else {
                $blockStruct['template'] = '';
            }

            if (method_exists($block, 'getViewVars')) {
                $blockStruct['context'] = $block->getViewVars();
            } else {
                $blockStruct['context'] = null;
            }

            if (!$block->getParentBlock()) {
                $blocks[] = $blockStruct;
                end($blocks);
                $key = key($blocks);
                $ptrs[$blockStruct['layout_name']] = &$blocks[$key];
            } else {
                $parentKey = $block->getParentBlock()->getNameInLayout();
                $ptrs[$parentKey]['blocks'][] = $blockStruct;
                end($ptrs[$parentKey]['blocks']);
                $key = key($ptrs[$parentKey]['blocks']);
                $ptrs[$blockStruct['layout_name']] = &$ptrs[$parentKey]['blocks'][$key];
            }
            $blocks_count++;
        }

        $storage['blocks'][] = json_decode(json_encode(['blocks' => $blocks, 'count' => $blocks_count]), true);
    }

    /**
     * Collect info about rendered blocks
     *
     * @param array $context
     * @param array $storage
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function collectBlockRender($context, & $storage)
    {
        $block = $context['this'];

        $blockStruct = [];
        $blockStruct['class'] = get_class($block);
        $blockStruct['classFile'] = $this->getClassFile($blockStruct['class']);
        $blockStruct['name_in_layout'] = $block->getNameInLayout();
        $blockStruct['render_time'] = microtime(true);

        if (method_exists($block, 'getTemplateFile')) {
            $blockStruct['template'] = $block->getTemplate();
            $blockStruct['templateFile'] = $block->getTemplateFile();
            if (!is_file($blockStruct['templateFile'])) {
                unset($blockStruct['templateFile']);
            }
        } else {
            $blockStruct['template'] = '';
        }
        if (method_exists($block, 'getViewVars')) {
            $blockStruct['view_variables'] = $block->getViewVars();
        } else {
            $blockStruct['view_variables'] = null;
        }

        $blockStruct['inCache'] = $this->getObjectManager()
            ->get('Magento\Framework\App\CacheInterface')
            ->load($block->getCacheKey())
            ? true : false;

        $this->blocks[$block->getNameInLayout()] = $blockStruct;
    }

    /**
     * Save block render time
     *
     * @param array $context
     * @param array $storage
     * @return void
     */
    public function processBlockRender($context, &$storage)
    {
        $block = $context['this'];
        $this->blocks[$block->getNameInLayout()]['render_time'] =
            number_format(microtime(true) - $this->blocks[$block->getNameInLayout()]['render_time'], 3);
        $storage['renderedBlocks'][] = $this->blocks[$block->getNameInLayout()];
        unset($this->blocks[$block->getNameInLayout()]);
    }

    /**
     * Collect info about modules
     *
     * @param array $storage
     * @return void
     */
    private function storeModules(& $storage)
    {
        $moduleList = $this->getObjectManager()->get('Magento\Framework\Module\ModuleList');
        $storage = array_map(function ($value) {
            return [
                'Name' => $value['name'],
                'Version' => $value['setup_version'],
                'Sequence' => is_array($value['sequence']) ? implode(', ', $value['sequence']) : $value['sequence']
            ];
        }, $moduleList->getAll());
    }

    /**
     * Get Magento ObjectManager
     *
     * @return \Magento\Framework\ObjectManagerInterface
     */
    private function getObjectManager()
    {
        if (is_null($this->objectManager)) {
            $this->objectManager = $GLOBALS['bootstrap']->getObjectManager();
        }

        return $this->objectManager;
    }

    /**
     * Save info about fired events and their observers
     *
     * @param array $context
     * @param array $storage
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createEvents($context, & $storage)
    {
        $eventName = $context['functionArgs'][0];

        $this->registeredEvents[$eventName]['observers'] =
            is_array($context['returnValue']) ? $context['returnValue'] : [];
    }

    /**
     * Collect observer area, args and start execution time
     *
     * @param array $context
     * @param array $storage
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function callObserverMethodStart($context, & $storage)
    {
        $method = $context['functionArgs'][1];
        $observerData = $context['functionArgs'][2]->getData();
        $eventName = $observerData['event']->getName();
        $className = get_class($context['functionArgs'][0]);
        $key = $this->getObserverKey($eventName, $className, $method);

        $this->observersProfiles[$key] = [
            'duration' => microtime(true),
            'area' => $this->getNotPublicProperty($context['this'], '_appState')->getAreaCode(),
            'args' => implode(', ', array_keys($observerData))
        ];
    }

    /**
     * Save duration of observer execution
     *
     * @param array $context
     * @param array $storage
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function callObserverMethodEnd($context, & $storage)
    {
        $method = $context['functionArgs'][1];
        $observerData = $context['functionArgs'][2]->getData();
        $eventName = $observerData['event']->getName();
        $className = get_class($context['functionArgs'][0]);
        $key = $this->getObserverKey($eventName, $className, $method);

        $this->observersProfiles[$key]['duration'] = microtime(true) - $this->observersProfiles[$key]['duration'];
    }

    /**
     * Generate cache key for observers info
     *
     * @param string $eventName
     * @param string $className
     * @param string $method
     * @return string
     */
    private function getObserverKey($eventName, $className, $method)
    {
        return $eventName . ':' . $className . ':' .$method;
    }

    /**
     * Generate cache key for plugins info
     *
     * @param string $code
     * @param string $interceptedClass
     * @return string
     */
    private function getPluginKey($code, $interceptedClass)
    {
        return $code . '|' . $interceptedClass;
    }

    /**
     * Collect observers info
     *
     * @param array $storage
     * @return void
     */
    private function storeObservers(& $storage)
    {
        $count = 0;
        $collectedEventsNames = [];
        foreach (['global', 'frontend', 'adminhtml' ] as $eventArea) {
            $this->getObjectManager()->get('Magento\Framework\Config\ScopeInterface')->setCurrentScope($eventArea);
            $eventConfig = $this->getObjectManager()->get('Magento\Framework\Event\Config');
            $events = $eventConfig->getObservers(null);
            foreach ($events as $eventsName => $eventVal) {
                if (in_array($eventsName, $collectedEventsNames)) {
                    unset($events[$eventsName]);
                }
            }
            $count += count($events);
            $this->processEventObservers($events, $eventArea, $storage['observers']);
            $collectedEventsNames = array_merge($collectedEventsNames, array_keys($events));
        }
        $storage['count'] = $count;
    }

    /**
     * Collect observers of concrete area
     *
     * @param array $areaEvents
     * @param string $eventArea
     * @param array $storage
     * @return void
     */
    private function processEventObservers($areaEvents, $eventArea, &$storage)
    {
        foreach ($areaEvents as $eventName => $observers) {
            foreach ($observers as $observerName => $observer) {
                $class = $observer['instance'];
                $method = $observer['method'];

                $observerData = [
                    'area' => $eventArea,
                    'event' => $eventName,
                    'observer' => $observerName,
                    'class' => $class,
                    'classFile' => $this->getClassFile($class),
                    'methodLine' => $this->getMethodLine($class, $method),
                    'method' => $method
                ];

                $storage[] = $observerData;
            }
        }
    }

    /**
     * Store info about events
     *
     * @param array $storage
     * @return void
     */
    private function storeEvents(& $storage)
    {
        $counter = 1;
        foreach ($this->registeredEvents as $eventName => $eventData) {
            $observers = [];
            foreach ($eventData['observers'] as $name => &$observer) {

                $key = $this->getObserverKey($eventName, $observer['instance'], $observer['method']);
                if (isset($this->observersProfiles[$key])) {
                    $observer['duration'] = number_format($this->observersProfiles[$key]['duration'], 3);
                    $observer['args'] = $this->observersProfiles[$key]['args'];
                    $observer['area'] = $this->observersProfiles[$key]['area'];
                }

                $observers[] = [
                    'name' => $name,
                    'class' => $observer['instance'],
                    'classFile' => $this->getClassFile($observer['instance']),
                    'method' => $observer['method'],
                    'methodLine' => $this->getMethodLine($observer['instance'], $observer['method']),
                    'args' => $observer['args'],
                    'area' => isset($observer['area']) ? $observer['area'] : '',
                    'duration' => $observer['duration']
                ];
            }

            $storage[] = [
                'id' => $counter,
                'name' => $eventName,
                'area' => '',
                'observers' => $observers
            ];
            $counter++;
        }
    }

    /**
     * Collect plugins info
     *
     * @param array $context
     * @param array $storage
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function collectPlugins($context, &$storage)
    {
        $code = $context['functionArgs'][1];
        $type = $context['functionArgs'][0];
        $key = $this->getPluginKey($code, $type);

        if (!isset($this->pluginsInfo[$key])) {
            $inherited = $this->getNotPublicProperty($context['this'], '_inherited');

            $this->pluginsInfo[$key] = [
                'code' => $code,
                'type' => $type,
                'instance' => $inherited[$type][$code]['instance'],
                'typeFile' => $this->getClassFile($type),
                'instanceFile' => $this->getClassFile($inherited[$type][$code]['instance']),
            ];
            $storage['plugins'][]  = $this->pluginsInfo[$key];
        }
    }

    /**
     * Collect intercepted methods
     *
     * @param array $context
     * @param array $storage
     */
    public function callPluginsEnd($context, &$storage)
    {
        if (!preg_match('/___callPlugins/', $context['functionName'])) return;

        $pluginInfo = $context['functionArgs'][2];
        $method = $context['functionArgs'][0];
        $capMethod = ucfirst($method);
        $class = $this->getNotPublicProperty($context['this'], 'subjectType');
        $key = $class . '::' . $method;

        $this->interceptedMethods[$key]['class'] = $class;
        $this->interceptedMethods[$key]['count'] =
            isset($this->interceptedMethods[$key]['count']) ? $this->interceptedMethods[$key]['count'] + 1 : 1;
        $this->interceptedMethods[$key]['classFile'] = $this->getClassFile($class);
        $this->interceptedMethods[$key]['methodLine'] = $this->getMethodLine($class, $method);
        $this->interceptedMethods[$key]['method'] = $method;
        $this->interceptedMethods[$key]['duration'] =
            isset($this->interceptedMethods[$key]['duration'])
                ?
                $this->interceptedMethods[$key]['duration'] + $context['durationInclusive']
                :
                $context['durationInclusive'];

        foreach($pluginInfo as $listenerCode => $pluginData) {
            $pluginData = is_array($pluginData) ? $pluginData : [$pluginData];
            foreach($pluginData as $code) {
                $plugin = $this->getPlugin($code, $listenerCode, $class, $capMethod);
                $keyPlugin = $key . $plugin['plugin'] . $plugin['pluginMethod'];
                if (array_key_exists($keyPlugin, $this->cacheIndexPlugins)) {
                    $this->interceptedMethods[$key]['plugins'][$this->cacheIndexPlugins[$keyPlugin]]['count'] =
                        $this->interceptedMethods[$key]['count'] ;
                } else {
                    $this->interceptedMethods[$key]['plugins'][] = $plugin;
                    end($this->interceptedMethods[$key]['plugins']);
                    $this->cacheIndexPlugins[$keyPlugin] = key($this->interceptedMethods[$key]['plugins']);
                }
            }
        }
    }

    /**
     * Get plugin info for intercepted class
     *
     * @param string $code
     * @param string $listenerCode
     * @param string $interceptedClass
     * @param string $capMethod
     * @return array
     */
    private function getPlugin($code, $listenerCode, $interceptedClass, $capMethod)
    {
        $key = $this->getPluginKey($code, $interceptedClass);
        $class = $this->pluginsInfo[$key]['instance'];
        $method = $this->pluginsPrefix[$listenerCode] . $capMethod;
        return [
            'count' => 1,
            'pluginMethod' => $method,
            'plugin' => $class,
            'pluginFile' => $this->pluginsInfo[$key]['instanceFile'],
            'methodLine' => $this->getMethodLine($class, $method),
        ];
    }

    /**
     * Collect info at the end of Magento\Framework\App\Bootstrap::run method call
     *
     * @param array $context
     * @param array $storage
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function bootstrapRunExit($context, &$storage)
    {
        //Modules
        $storage['modules'] = [];
        $this->storeModules($storage['modules']);

        //Observers / Events
        $storage['observers'] = [];
        $this->storeObservers($storage['observers'][]);

        //Events
        $storage['mevents'] = [];
        $this->storeEvents($storage['mevents']);

        //Intercepted methods
        $storage['intercepted'] = $this->interceptedMethods;
    }

    /**
     * Collect info about objects created by ObjectManager
     *
     * @param array $context
     * @param array $storage
     * @return void
     */
    public function createObjectEnd($context, &$storage)
    {
        $storage['objects'][] = [
            'class' => $context['functionArgs'][0],
            'classFile' => $this->getClassFile($context['functionArgs'][0]),
            'duration' => number_format($context['durationInclusive'] / 1000, 3),
        ];
    }

    /**
     * Get class filename
     *
     * @param string $class
     * @return bool|string
     */
    private function getClassFile($class)
    {
        try {
            $reflector = new \ReflectionClass($class);
            return $reflector->getFileName();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get class method line
     *
     * @param string $class
     * @param string $method
     * @return int
     */
    private function getMethodLine($class, $method)
    {
        try {
            $methodReflector = new \ReflectionMethod($class, $method);
            return $methodReflector->getStartLine();
        } catch (\Exception $e) {
            return 1;
        }
    }

    /**
     * Get private or protected object property
     *
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    private function getNotPublicProperty($object, $propertyName)
    {
        $mageReflect = new \ReflectionClass($object);
        $property = $mageReflect->getProperty($propertyName);
        $property->setAccessible(true);
        $result = $property->getValue($object);
        unset($mageReflect);

        return $result;
    }

}

$zrayMagento = new Magento();
$zrayMagento->setZRay(new \ZRayExtension('Magento2'));

$zrayMagento->getZRay()->setMetadata([
    'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
]);

$zrayMagento->getZRay()->setEnabledAfter('Magento\Framework\App\Bootstrap::run');

$zrayMagento->getZRay()->traceFunction(
    'Magento\Framework\App\Bootstrap::run',
    function () {},
    [$zrayMagento, 'bootstrapRunExit']
);
$zrayMagento->getZRay()->traceFunction(
    'Magento\Framework\View\Layout\Builder::generateLayoutBlocks',
    function () {},
    [$zrayMagento, 'collectLayoutBlocks']

);
$zrayMagento->getZRay()->traceFunction(
    'Magento\Framework\View\Element\AbstractBlock::toHtml',
    [$zrayMagento, 'collectBlockRender'],
    [$zrayMagento, 'processBlockRender']
);
$zrayMagento->getZRay()->traceFunction(
    'Magento\Framework\Event\Config::getObservers',
    function () {},
    [$zrayMagento, 'createEvents']
);
$zrayMagento->getZRay()->traceFunction(
    'Magento\Framework\Event\Invoker\InvokerDefault::_callObserverMethod',
    [$zrayMagento, 'callObserverMethodStart'],
    [$zrayMagento, 'callObserverMethodEnd']
);

$zrayMagento->getZRay()->traceFunction(
    'Magento\Framework\Interception\PluginList\PluginList::getPlugin',
    function () {},
    [$zrayMagento, 'collectPlugins']
);
$zrayMagento->getZRay()->traceFile(
    MAGENTO_PATH . '\lib\internal\Magento\Framework\Interception\Interceptor.php',
    function () {},
    [$zrayMagento, 'callPluginsEnd']
);
$zrayMagento->getZRay()->traceFunction(
    'Magento\Framework\ObjectManager\Factory\AbstractFactory::createObject',
    function () {},
    [$zrayMagento, 'createObjectEnd']
);
