<?php

/**
 * CWM Scripture Task Plugin - Installation Script
 *
 * On install: auto-enables the plugin and schedules a one-shot core
 * translation download task that runs on the next scheduler trigger.
 *
 * @package    CWM.Plugin.Task.Cwmscripture
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

    /**
     * Auto-enable the plugin and queue a one-shot core download task.
     *
     * Joomla ships task plugins disabled by default; the scheduler only
     * dispatches events to enabled plugins, so nothing would run without
     * this auto-enable.  The scheduled task itself carries an execution
     * time in the past so the next lazy trigger (any admin page visit)
     * will pick it up.
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
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('cwmscripture'));
            $db->setQuery($query);
            $db->execute();

            $this->scheduleCoreDownload($db);
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage(
                'plg_task_cwmscripture: postflight failed — ' . $e->getMessage(),
                'warning'
            );
        }

        return true;
    }

    /**
     * Insert a one-shot row in #__scheduler_tasks for the download routine.
     *
     * Uses interval-minutes=1 with next_execution set to NOW so the
     * scheduler fires it at the earliest opportunity.  The task itself
     * deletes its row on successful completion so it won't repeat.
     *
     * @since 1.1.0
     */
    private function scheduleCoreDownload(\Joomla\Database\DatabaseInterface $db): void
    {
        // Skip if Joomla scheduler tables aren't present (shouldn't happen
        // on J5+/J6+) or if a task of this type already exists.
        $tables = $db->getTableList();

        if (!\in_array($db->getPrefix() . 'scheduler_tasks', $tables, true)) {
            return;
        }

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('cwmscripture.downloadCoreTranslations'));
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return;
        }

        $executionRules = json_encode([
            'rule-type'        => 'interval-minutes',
            'interval-minutes' => 1,
            'exec-day'         => '*',
            'exec-time'        => '',
        ]);

        $cronRules = json_encode([
            'type' => 'interval',
            'exp'  => 'PT1M',
        ]);

        $params = json_encode([
            'individual_log' => 0,
            'notifications'  => ['success_mail' => 0, 'failure_mail' => 0, 'fatal_failure_mail' => 0, 'orphan_mail' => 0],
            'translations'   => ['kjv', 'web', 'asv'],
        ]);

        $columns = [
            'title', 'type', 'execution_rules', 'cron_rules', 'state',
            'last_exit_code', 'last_execution', 'next_execution', 'times_executed',
            'times_failed', 'locked', 'priority', 'note', 'created', 'created_by', 'params',
        ];

        $now  = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $past = (new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $values = [
            $db->quote('CWM Scripture: Download Core Translations'),
            $db->quote('cwmscripture.downloadCoreTranslations'),
            $db->quote($executionRules),
            $db->quote($cronRules),
            1,                                                // state = enabled
            0,                                                // last_exit_code
            'NULL',                                           // last_execution
            $db->quote($past),                                // next_execution (in past → runs ASAP)
            0,                                                // times_executed
            0,                                                // times_failed
            'NULL',                                           // locked
            0,                                                // priority
            $db->quote(''),                                   // note
            $db->quote($now),                                 // created
            0,                                                // created_by
            $db->quote($params),
        ];

        $insert = $db->getQuery(true)
            ->insert($db->quoteName('#__scheduler_tasks'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
        $db->setQuery($insert);
        $db->execute();
    }
};
