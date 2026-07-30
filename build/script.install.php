<?php

/**
 * Package installer script for pkg_cwmscripture.
 *
 * @package    CWM.ScriptureLinks
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @since      __DEPLOY_VERSION__
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use CWM\Library\Scripture\Installer\ConsumerRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * Returns an anonymous class implementing InstallerScriptInterface.
 *
 * Joomla 5+ expects the script file to return an InstallerScriptInterface
 * instance directly (not define a named class).
 *
 * @since  __DEPLOY_VERSION__
 */
return new class () implements InstallerScriptInterface {
    /**
     * Runs before the bundled extensions install.
     *
     * @param   string            $type     Install type (install, update, discover_install)
     * @param   InstallerAdapter  $adapter  The installer adapter
     *
     * @return  bool  True to continue, false to abort
     *
     * @since  __DEPLOY_VERSION__
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        // Must happen before the child extensions install — see the method docblock.
        $this->disarmLegacyScriptureUninstallSql();

        return true;
    }

    /**
     * Runs after install/update completes.
     *
     * @param   string            $type     Install type (install, update, discover_install)
     * @param   InstallerAdapter  $adapter  The installer adapter
     *
     * @return  bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        // The protection plugin is useless disabled, and Joomla installs plugins
        // disabled by default — see the method docblock.
        $this->enablePlugin('cwmscripture', 'system');

        return true;
    }

    /**
     * Enable a bundled plugin.
     *
     * Joomla installs plugins disabled, which is wrong for
     * plg_system_cwmscripture: its whole job is to blank the scripture library's
     * legacy uninstall SQL before an admin runs a library-only update, and a
     * disabled plugin never fires. Leaving it to the administrator would mean the
     * protection is off exactly on the sites that have not been touched recently
     * — the ones most likely to still be on a destructive 1.1.4 library.
     *
     * Only flips a plugin that is currently disabled, so an administrator who
     * deliberately turned it off is not overridden on the next update.
     *
     * @param   string  $element  Plugin element
     * @param   string  $group    Plugin group
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function enablePlugin(string $element, string $group): void
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($group))
                ->where($db->quoteName('enabled') . ' = 0');
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            Log::add(
                'pkg_cwmscripture: could not enable plg_' . $group . '_' . $element . ' — ' . $e->getMessage(),
                Log::WARNING,
                'jerror'
            );
        }
    }

    /**
     * Runs on install.
     *
     * @param   InstallerAdapter  $adapter  The installer adapter
     *
     * @return  bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Runs on update.
     *
     * @param   InstallerAdapter  $adapter  The installer adapter
     *
     * @return  bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Runs on uninstall.
     *
     * @param   InstallerAdapter  $adapter  The installer adapter
     *
     * @return  bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        $this->dropScriptureTablesIfLastConsumer();

        return true;
    }

    /**
     * Remove the shared scripture tables when this package is the last consumer.
     *
     * The library deliberately will not do this itself during a package removal:
     * PackageAdapter::removeExtensionFiles() uninstalls each child with
     * setPackageUninstall(true), and lib_cwmscripture treats that flag as "an
     * upgrade cycle may be in progress, keep the data". Correct for the library,
     * but it means nothing cleans up when the whole package genuinely goes away.
     *
     * Doing it here is safe because PackageAdapter does NOT uninstall-then-install
     * on update — checkExtensionInFilesystem() only sets the route — so a package
     * manifest script's uninstall() runs on genuine removal and never on upgrade.
     * It also runs before removeExtensionFiles(), so the children are still
     * present and the drop happens exactly once.
     *
     * The "is anything else using it" question is delegated to the library's
     * ConsumerRegistry rather than answered with a hardcoded list. Joomla tracks
     * no library dependencies, so a third-party extension is invisible unless it
     * registered itself; asking the registry means such a consumer is seen and
     * its data left alone, instead of being silently dropped.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function dropScriptureTablesIfLastConsumer(): void
    {
        try {
            $registry = JPATH_LIBRARIES . '/cwmscripture/src/Installer/ConsumerRegistry.php';

            if (!class_exists(ConsumerRegistry::class) && is_file($registry)) {
                require_once $registry;
            }

            if (!class_exists(ConsumerRegistry::class)) {
                Log::add(
                    'pkg_cwmscripture: ConsumerRegistry unavailable — keeping the scripture tables.',
                    Log::WARNING,
                    'jerror'
                );

                return;
            }

            // Everything this package removes; anything left is someone else's.
            $remaining = ConsumerRegistry::installedExcluding([
                ['element' => 'scripturelinks', 'type' => 'plugin', 'folder' => 'content'],
                ['element' => 'cwmscripture',   'type' => 'plugin', 'folder' => 'task'],
            ]);

            if ($remaining !== []) {
                Log::add(
                    'pkg_cwmscripture: scripture tables still used by ' . implode(', ', $remaining) . ' — keeping them.',
                    Log::INFO,
                    'jerror'
                );

                return;
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);

            foreach (['#__bsms_scripture_consumers', '#__bsms_scripture_cache', '#__bsms_bible_verses', '#__bsms_bible_translations'] as $table) {
                $db->setQuery('DROP TABLE IF EXISTS ' . $db->quoteName($table));
                $db->execute();
            }

            Log::add(
                'pkg_cwmscripture: removed the scripture tables (last consumer uninstalled).',
                Log::INFO,
                'jerror'
            );
        } catch (\Throwable $e) {
            Log::add(
                'pkg_cwmscripture: scripture table cleanup skipped — ' . $e->getMessage(),
                Log::WARNING,
                'jerror'
            );
        }
    }

    /**
     * Stop the installed scripture library from dropping its own tables.
     *
     * lib_cwmscripture up to 1.1.4 shipped an <uninstall><sql> block pointing at
     * DROP TABLE statements. Joomla's LibraryAdapter uninstalls the installed
     * library before writing the new one (checkExtensionInFilesystem() calls
     * uninstall()), so that SQL ran on every UPDATE — taking #__bsms_bible_verses
     * and #__bsms_bible_translations with it. Every locally downloaded
     * translation disappeared and the Local Translations panel came back empty.
     *
     * The library no longer declares uninstall SQL, but that only helps the
     * upgrade *after* this one: during this install Joomla reads the manifest and
     * the SQL file already sitting in JPATH_LIBRARIES — the old, destructive
     * ones. Installer::parseSQLFiles() resolves the file against the installed
     * extension root, so blanking it here, before the library child installs, is
     * what actually saves the data.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function disarmLegacyScriptureUninstallSql(): void
    {
        $sqlFile = JPATH_LIBRARIES . '/cwmscripture/sql/uninstall.mysql.utf8.sql';

        if (!is_file($sqlFile)) {
            return;
        }

        $buffer = @file_get_contents($sqlFile);

        if ($buffer === false || stripos($buffer, 'DROP TABLE') === false) {
            return;
        }

        $replacement = "--\n"
            . "-- Neutralised by the pkg_cwmscripture installer.\n"
            . "--\n"
            . "-- Joomla runs a library's uninstall SQL on every update, so the DROP TABLE\n"
            . "-- statements this file used to hold wiped every downloaded Bible translation\n"
            . "-- each time lib_cwmscripture was upgraded. Table removal now lives in the\n"
            . "-- library's script.php, which can tell an upgrade from a real uninstall.\n"
            . "--\n";

        if (@file_put_contents($sqlFile, $replacement) === false) {
            Factory::getApplication()->enqueueMessage(
                'The scripture package could not rewrite ' . $sqlFile . '. Joomla may drop your '
                . 'locally downloaded Bible translations during this update; re-download them '
                . 'from the Scripture Links plugin settings if the Local Translations list comes '
                . 'back empty.',
                'warning'
            );

            Log::add(
                'pkg_cwmscripture: could not disarm legacy scripture uninstall SQL at ' . $sqlFile,
                Log::WARNING,
                'cwmscripture.install'
            );

            return;
        }

        Log::add(
            'pkg_cwmscripture: disarmed legacy scripture uninstall SQL (bible tables preserved).',
            Log::INFO,
            'cwmscripture.install'
        );
    }
};
