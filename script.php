<?php

/**
 * CWM ScriptureLinks — Installation Script
 *
 * @package    CWM.Plugin.Content.ScriptureLinks
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface {
    /**
     * Called after install/update.
     *
     * Auto-enables the plugin so the library's Translations Manager admin UI
     * (which dispatches via com_ajax → onAjaxScripturelinks) has a live event
     * subscriber.  Joomla only routes plugin events to enabled plugins, and
     * without this the admin catalog list appears empty on fresh installs.
     *
     * This does NOT activate any runtime behaviour against existing content —
     * onContentPrepare only acts on articles that explicitly contain
     * {scripture} / {bible} tags.
     *
     * @param   string            $type     Install type
     * @param   InstallerAdapter  $adapter  The installer adapter
     *
     * @return  bool
     *
     * @since   1.1.0
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('scripturelinks'));
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable) {
            // Silent — plugin row may not exist yet during discover install.
        }

        // Declare our dependency on lib_cwmscripture
        $this->consumer('register');

        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        $this->consumer('unregister');

        return true;
    }

    /**
     * Declare, or withdraw, this plugin's dependency on lib_cwmscripture.
     *
     * Joomla tracks no dependencies between extensions, so the library cannot
     * discover who relies on it. Registering means the library refuses to be
     * uninstalled while we are installed, and the shared #__bsms_bible_* tables
     * are not dropped while we still read them.
     *
     * The library's own entry point handles autoloading, version tolerance and
     * error handling, so this stays two lines. Note the group is part of the
     * registry key — a plugin registered without it never matches on lookup.
     *
     * @param   string  $action  'register' on install/update, 'unregister' on removal
     *
     * @return  void
     *
     * @since   1.2.0
     */
    private function consumer(string $action): void
    {
        if (!is_file($helper = JPATH_LIBRARIES . '/cwmscripture/src/consumer.php')) {
            return;
        }

        $registry = require $helper;

        // Not one dynamic call: unregister() takes no display name.
        if ($action === 'register') {
            $registry->register('scripturelinks', 'plugin', 'content', 'Scripture Links (plg_content_scripturelinks)');

            return;
        }

        $registry->unregister('scripturelinks', 'plugin', 'content');
    }
};
