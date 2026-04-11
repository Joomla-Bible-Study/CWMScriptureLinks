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
        return true;
    }
};
