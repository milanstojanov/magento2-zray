<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2;

/**
 * Class MagentoPlugin
 */
class MagentoPlugin extends \ZAppsPlugin
{

    /**
     * @var bool
     */
    private $resolved = false;

    /**
     * @param array $context
     */
    public function resolveRouteLeave($context)
    {
        $request = $context['functionArgs'][0];

        if (!$this->resolved) {
            $this->resolved = true;
            $mvc = [
                "module" => $request->getModuleName(),
                "controller" => $request->getControllerName(),
                "action" => $request->getActionName()
            ];
            $this->setRequestRoute($mvc);
        }
    }
}

$magentoPlugin = new MagentoPlugin();
$magentoPlugin->setWatchedFunction(
    'Magento\Framework\App\FrontController::dispatch',
    function () {},
    [$magentoPlugin, "resolveRouteLeave"]
);
