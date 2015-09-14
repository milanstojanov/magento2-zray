<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2;

/**
 * Class Module
 */
class Module extends \ZRay\ZRayModule
{
    /**
     * Configuration of the widget panel
     *
     * @return array
     */
    public function config()
    {
        return [
            'extension' => [
                'name' => 'Magento2',
            ],
            // Configure custom panels
            'defaultPanels' => [
                'mevents' => false
            ],
            'panels' => [
                'observers' => [
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Observers',
                    'panelTitle' => 'Observers',
                    'searchId' => 'magento-observers-search',
                    'pagerId' => 'magento-observers-pager',
                ],
                'objects' => [
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Created Objects',
                    'panelTitle' => 'Created Objects',
                    'searchId' => 'magento-objects-search',
                    'pagerId' => 'magento-objects-pager',
                ],
                'intercepted' => [
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Intercepted methods',
                    'panelTitle' => 'Intercepted methods',
                    'searchId' => 'magento-intercepted-search',
                    'pagerId' => 'magento-intercepted-pager',
                ],
                'plugins' => [
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Plugins',
                    'panelTitle' => 'Plugins',
                    'searchId' => 'magento-plugins-search',
                    'pagerId' => 'magento-plugins-pager',
                ],
                'blocks' => [
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Blocks',
                    'panelTitle' => 'Blocks',
                ],
                'renderedBlocks' => [
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Rendered Blocks',
                    'panelTitle' => 'Rendered Blocks',
                    'searchId' => 'magento-rblocks-search',
                    'pagerId' => 'magento-rblocks-pager',
                ],
                'events' => [
                    'display' => true,
                    'logo' => 'logo.png',
                    'menuTitle' => 'Events',
                    'panelTitle' => 'Events',
                    'searchId' => 'magento-events-search',
                    'pagerId' => 'magento-events-pager',
                ],
            ]
        ];
    }
}
