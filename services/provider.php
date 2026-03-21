<?php

/**
 * Part of CWM ScriptureLinks Plugin
 *
 * @package    CWM.Plugin.Content.ScriptureLinks
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

\defined('_JEXEC') or die;

use CWM\Plugin\Content\ScriptureLinks\Extension\ScriptureLinks;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with the DI container.
     *
     * @param   Container  $container  The DI container
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new ScriptureLinks(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('content', 'scripturelinks')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
