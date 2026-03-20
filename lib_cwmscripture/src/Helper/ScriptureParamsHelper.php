<?php

/**
 * Part of CWM Scripture Library
 *
 * @package    CWM.Library.Scripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Library\Scripture\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper to read and write scripture plugin params from #__extensions.
 *
 * Centralises access to `plg_content_scripturelinks` configuration so that
 * both the plugin itself and any host component (e.g. Proclaim) can share
 * the same settings without coupling.
 *
 * @since  1.1.0
 */
class ScriptureParamsHelper
{
    /**
     * Request-level cache of the loaded Registry.
     *
     * @var  Registry|null
     * @since  1.1.0
     */
    private static ?Registry $cache = null;

    /**
     * Default values for all scripture settings.
     *
     * @var  array<string, mixed>
     * @since  1.1.0
     */
    private const DEFAULTS = [
        'mode'               => 'tag',
        'display'            => 'link',
        'default_version'    => 'kjv',
        'provider_getbible'  => 1,
        'provider_api_bible' => 0,
        'api_bible_api_key'  => '',
        'gdpr_mode'          => 0,
        'cache_days'         => 30,
    ];

    /**
     * Get scripture plugin params as a Registry.
     *
     * Falls back to sensible defaults if the plugin is not installed.
     *
     * @return  Registry
     *
     * @since  1.1.0
     */
    public static function getParams(): Registry
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('scripturelinks'));
            $db->setQuery($query);
            $json = $db->loadResult();

            if ($json) {
                self::$cache = new Registry($json);
            } else {
                self::$cache = new Registry(self::DEFAULTS);
            }
        } catch (\Exception $e) {
            self::$cache = new Registry(self::DEFAULTS);
        }

        return self::$cache;
    }

    /**
     * Save scripture params to the plugin's #__extensions row.
     *
     * @param   Registry  $params  The params to save
     *
     * @return  void
     *
     * @throws  \RuntimeException  If the plugin row does not exist
     *
     * @since  1.1.0
     */
    public static function save(Registry $params): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('scripturelinks'));
        $db->setQuery($query);
        $db->execute();

        // Sync gdpr_mode to Proclaim's component params if installed.
        // Proclaim keeps its own copy for analytics/privacy beyond scripture.
        try {
            $gdprMode = $params->get('gdpr_mode');

            if ($gdprMode !== null) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__bsms_admin'))
                    ->where($db->quoteName('id') . ' = 1');
                $db->setQuery($query);
                $adminJson = $db->loadResult();

                if ($adminJson !== null) {
                    $adminParams = new Registry($adminJson);
                    $adminParams->set('gdpr_mode', $gdprMode);

                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__bsms_admin'))
                        ->set($db->quoteName('params') . ' = ' . $db->quote($adminParams->toString()))
                        ->where($db->quoteName('id') . ' = 1');
                    $db->setQuery($query);
                    $db->execute();
                }
            }
        } catch (\Exception $e) {
            // Proclaim not installed — skip sync
        }

        // Invalidate cache so next getParams() reads fresh data
        self::$cache = null;
    }

    /**
     * Clear the request-level cache.
     *
     * Useful in tests or after external writes.
     *
     * @return  void
     *
     * @since  1.1.0
     */
    public static function reset(): void
    {
        self::$cache = null;
    }
}
