<?php

/**
 * @package    CWM.Plugin.System.Cwmscripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\Plugin\System\Cwmscripture\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Protects locally downloaded Bibles from the scripture library's legacy
 * uninstall SQL.
 *
 * lib_cwmscripture up to 1.1.4 declared `<uninstall><sql>` pointing at DROP
 * TABLE statements. Joomla's LibraryAdapter uninstalls the installed library
 * before writing the new one, so that SQL ran on every UPDATE and wiped
 * `#__bsms_bible_verses` / `#__bsms_bible_translations` — every translation the
 * site owner had downloaded, gone, with the Local Translations panel coming
 * back empty.
 *
 * 1.1.6 removed the block, but that only protects the upgrade *after* this one:
 * the uninstall SQL Joomla executes during an update is the one already sitting
 * in JPATH_LIBRARIES, belonging to the installed version. So a site on 1.1.4 is
 * still destroyed by the very update that fixes it.
 *
 * The package installers disarm the file for package updates, but the library
 * carries its own update server and is listed separately in the Update Manager.
 * A library-only update runs no package code at all — and nothing inside the
 * incoming library can help either, because `InstallerAdapter::install()` calls
 * `checkExtensionInFilesystem()` (which triggers the old uninstall) *before*
 * `triggerManifestScript('preflight')` loads the new script file. By the time
 * any incoming code runs, the tables are already gone.
 *
 * The only remaining place to intervene is code already resident on the site,
 * running before the installer does. Hence this plugin: it blanks the file
 * while the administrator is on com_installer, before they click Update.
 *
 * plg_system_proclaim does the same thing for Proclaim sites. This exists so
 * stacks that carry the library without Proclaim — Living Word, or any
 * third-party consumer — are covered too.
 *
 * One-shot: once the file holds no statements the check short-circuits and this
 * costs a single is_file()/strpos() per admin installer page load.
 *
 * @since  1.2.0
 */
final class Cwmscripture extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns the events this subscriber will listen to.
     *
     * @return  array<string, string>
     *
     * @since  1.2.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // Fires while an administrator is on com_installer, before they
            // click Update. Covers the interactive path, including routes that
            // never reach Installer::install() at all.
            'onAfterRoute' => 'onAfterRoute',

            // ⚠️ The headless paths, which the gated handler above cannot
            // reach (Joomla-Bible-Study/Proclaim#1864).
            //
            // Both are dispatched by the Installer *library* rather than by
            // com_installer, so they fire under CLI, cron, Panopticon and any
            // remote update runner. ConsoleApplication imports the system
            // plugin group at boot, so this plugin is subscribed there too.
            //
            // Both are needed, and which one matters is not obvious:
            // the Update Manager calls Installer::update(), which dispatches
            // onExtensionBeforeUpdate *only* — then InstallerAdapter::update()
            // calls install(), which is where checkExtensionInFilesystem()
            // uninstalls the old library and runs its armed SQL. Subscribing to
            // onExtensionBeforeInstall alone would miss the exact path this is
            // for; a manual zip install over an existing library takes the
            // other.
            //
            // Both fire before $adapter->install(), which is the only window
            // that helps: the new library's own preflight() runs *after*
            // checkExtensionInFilesystem(), by which point the tables are gone.
            'onExtensionBeforeUpdate'  => 'onExtensionBeforeInstaller',
            'onExtensionBeforeInstall' => 'onExtensionBeforeInstaller',
        ];
    }

    /**
     * Disarm the legacy uninstall SQL while the admin is in the installer.
     *
     * @return  void
     *
     * @since  1.2.0
     */
    public function onAfterRoute(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        if ($app->getInput()->getCmd('option', '') !== 'com_installer') {
            return;
        }

        $this->disarmLegacyScriptureUninstallSql();
    }

    /**
     * Disarm before the installer touches the library, headlessly.
     *
     * onAfterRoute cannot cover a CLI or cron update: it is gated on an
     * administrator request to com_installer, and there is no request.
     *
     * Deliberately ungated. The sweep reads and writes one file under
     * JPATH_LIBRARIES and consults no application state, so there is nothing
     * here that needs a client, a session or an input. Running it once more
     * than necessary costs a file read: {@see sqlIsArmed()} returns false for
     * an already-disarmed file and the sweep returns early, so the interactive
     * path disarming first simply makes this a no-op.
     *
     * @return  void
     *
     * @since  1.2.11
     */
    public function onExtensionBeforeInstaller(): void
    {
        $this->disarmLegacyScriptureUninstallSql();
    }

    /**
     * Does this SQL contain an executable DROP TABLE?
     *
     * The distinction matters: the already-disarmed 1.1.5 and 1.1.6 files
     * *describe* the old behaviour in prose, so they contain the words "DROP
     * TABLE" inside `--` comments. A naive substring search flags them as
     * dangerous and rewrites files that are perfectly fine — so only lines that
     * are not comments count.
     *
     * Deliberately conservative: a trailing `-- DROP TABLE` comment on a real
     * statement line reads as armed. Rewriting an already-safe file costs
     * nothing; missing a live one costs the site its Bibles.
     *
     * @param   string  $sql  Contents of an uninstall SQL file
     *
     * @return  bool  True when at least one executable DROP TABLE is present
     *
     * @since  1.2.0
     */
    public static function sqlIsArmed(string $sql): bool
    {
        if (stripos($sql, 'DROP TABLE') === false) {
            return false;
        }

        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '--')) {
                continue;
            }

            if (stripos($line, 'DROP TABLE') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Blank the scripture library's legacy, destructive uninstall SQL.
     *
     * Rewrites rather than deletes: an older manifest still references the file
     * by path, and Installer::parseSQLFiles() resolves it against the installed
     * extension root, so removing it would make those sites fail to find it.
     *
     * @return  void
     *
     * @since  1.2.0
     */
    private function disarmLegacyScriptureUninstallSql(): void
    {
        $sqlFile = JPATH_LIBRARIES . '/cwmscripture/sql/uninstall.mysql.utf8.sql';

        if (!is_file($sqlFile) || !is_writable($sqlFile)) {
            return;
        }

        $contents = @file_get_contents($sqlFile);

        if ($contents === false || !self::sqlIsArmed($contents)) {
            return;
        }

        $replacement = <<<'SQL'
--
-- CWM Scripture Library - Uninstall SQL (neutralised by plg_system_cwmscripture)
--
-- This file held DROP TABLE statements for the shared bible tables. Joomla runs
-- a library's uninstall SQL on every UPDATE, not only on removal, so they were
-- destroying every locally downloaded translation on each upgrade.
--
-- Emptied in place rather than deleted: an older manifest still references this
-- path. Do not reintroduce DROP TABLE statements here.
--
SQL;

        @file_put_contents($sqlFile, $replacement . "\n");
    }
}
