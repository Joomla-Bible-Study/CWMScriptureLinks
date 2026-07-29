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
 * @since  __DEPLOY_VERSION__
 */
final class Cwmscripture extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns the events this subscriber will listen to.
     *
     * @return  array<string, string>
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRoute' => 'onAfterRoute',
        ];
    }

    /**
     * Disarm the legacy uninstall SQL while the admin is in the installer.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
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
     * @since  __DEPLOY_VERSION__
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
     * @since  __DEPLOY_VERSION__
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