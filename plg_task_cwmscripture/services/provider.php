<?php

/**
 * @package    CWM.Plugin.Task.Cwmscripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use CWM\Plugin\Task\Cwmscripture\Extension\Cwmscripture;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        // CWM Scripture needs PHP 8.3. Below it, register nothing: Joomla then boots a do-nothing plugin in this one's place,
        // so none of CWM Scripture's code loads and the site keeps working. Keep 8.3-only
        // syntax out of this file, or the guard itself cannot run.
        if (PHP_VERSION_ID < 80300) {
            return;
        }

        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Cwmscripture(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('task', 'cwmscripture')
                );

                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
