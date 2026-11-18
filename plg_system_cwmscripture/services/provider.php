<?php

/**
 * @package    CWM.Plugin.System.Cwmscripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use CWM\Plugin\System\Cwmscripture\Extension\Cwmscripture;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        // CWM Scripture needs PHP 8.4. Below it, register nothing, so none of its code
        // loads and the site keeps working. This plugin boots on every request,
        // which makes it the one that tells administrators why. Keep 8.4-only
        // syntax out of this file, or the guard itself cannot run.
        if (PHP_VERSION_ID < 80400) {
            $this->postPhpBanner();

            return;
        }

        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Cwmscripture(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('system', 'cwmscripture')
                );

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }

    /**
     * Tell administrators that CWM Scripture is switched off, and why.
     *
     * Backend only: a visitor can do nothing about the server's PHP. Posted once
     * per request, by whichever of CWM Scripture's extensions boots first.
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    private function postPhpBanner(): void
    {
        try {
            $app = \Joomla\CMS\Factory::getApplication();
        } catch (\Throwable) {
            // No application yet, e.g. the CLI before it has booted.
            return;
        }

        if (!$app->isClient('administrator')) {
            return;
        }

        $message = \sprintf(
            '%1$s needs PHP 8.4.0 or later. This site runs PHP %2$s, so %1$s is switched off until PHP is upgraded.',
            'CWM Scripture',
            PHP_VERSION
        );

        foreach ($app->getMessageQueue() as $queued) {
            if (($queued['message'] ?? null) === $message) {
                return;
            }
        }

        $app->enqueueMessage($message, 'error');
    }
};
