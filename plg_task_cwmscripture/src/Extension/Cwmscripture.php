<?php

/**
 * @package    CWM.Plugin.Task.Cwmscripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\Plugin\Task\Cwmscripture\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Task plugin for background scripture library setup.
 *
 * Runs deferred one-shot jobs scheduled during extension install — notably
 * downloading the core public-domain Bible translations (KJV, WEB, ASV)
 * that consumers like LivingWord rely on.  Keeps the install request
 * short by moving the ~40-second GetBible downloads out of postflight.
 *
 * @since  1.1.0
 */
class Cwmscripture extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;
    use DatabaseAwareTrait;

    /**
     * Map of task types this plugin handles.
     *
     * @var  array<string, array<string, string>>
     * @since  1.1.0
     */
    protected const TASKS_MAP = [
        'cwmscripture.downloadCoreTranslations' => [
            'langConstPrefix' => 'PLG_TASK_CWMSCRIPTURE_DOWNLOAD_CORE',
            'method'          => 'downloadCoreTranslations',
        ],
    ];

    /**
     * Default translations to download when no explicit params are provided.
     *
     * @var  string[]
     * @since  1.1.0
     */
    private const DEFAULT_CORE_TRANSLATIONS = ['kjv', 'web', 'asv'];

    /**
     * @return  array
     *
     * @since   1.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask'     => 'standardRoutineHandler',
        ];
    }

    /**
     * Download core public-domain translations via BibleImporter.
     *
     * Called by the scheduler when the one-shot task scheduled by the
     * plugin's install script fires.  Deletes its own scheduler task row
     * on successful completion so it runs exactly once.
     *
     * @param   ExecuteTaskEvent  $event  The task event
     *
     * @return  int  Task status code
     *
     * @since   1.1.0
     */
    private function downloadCoreTranslations(ExecuteTaskEvent $event): int
    {
        // Scheduler tasks have no default execution time limit; just make
        // sure slow downloads don't hit a hardcoded web timeout.
        @set_time_limit(0);

        if (!$this->loadImporter()) {
            $this->logTask('BibleImporter not loadable — cannot run core translation downloads', 'error');

            return Status::KNOCKOUT;
        }

        $params       = $event->getArgument('params');
        $translations = self::DEFAULT_CORE_TRANSLATIONS;

        if (\is_object($params) && !empty($params->translations)) {
            $translations = array_values(array_filter(array_map('strval', (array) $params->translations)));
        }

        $failures = 0;

        foreach ($translations as $abbr) {
            try {
                $count = \CWM\Library\Scripture\Importer\BibleImporter::downloadAndImport($abbr);

                if ($count > 0) {
                    $this->logTask(
                        \sprintf('Imported %d verses for "%s"', $count, $abbr),
                        'info'
                    );
                } else {
                    $failures++;
                    $this->logTask(
                        \sprintf('Import returned no verses for "%s" — remote fetch may have failed', $abbr),
                        'warning'
                    );
                }
            } catch (\Throwable $e) {
                $failures++;
                $this->logTask(
                    \sprintf('Failed to import "%s": %s', $abbr, $e->getMessage()),
                    'error'
                );
            }
        }

        // Delete the one-shot task row so it doesn't appear in Task Manager
        // after its job is done.  Only remove it if nothing failed — a
        // failure leaves the row behind for manual retry.
        if ($failures === 0) {
            $this->removeOwnTask($event);
        }

        return $failures === 0 ? Status::OK : Status::KNOCKOUT;
    }

    /**
     * Ensure CWM\Library\Scripture\Importer\BibleImporter is loadable.
     *
     * Joomla's PSR-4 autoload cache usually resolves the library namespace
     * by the time the scheduler runs (it's rebuilt between requests), but
     * we defensively require_once the importer + its direct dependencies
     * by absolute path as a belt-and-braces fallback.
     *
     * @since 1.1.0
     */
    private function loadImporter(): bool
    {
        if (class_exists(\CWM\Library\Scripture\Importer\BibleImporter::class, true)) {
            return true;
        }

        $libSrc = JPATH_LIBRARIES . '/cwmscripture/src';

        foreach (
            [
                '/Bible/BibleProviderInterface.php',
                '/Bible/BibleProviderFactory.php',
                '/Bible/BiblePassageResult.php',
                '/Importer/BibleImporter.php',
            ] as $relative
        ) {
            $path = $libSrc . $relative;

            if (file_exists($path)) {
                require_once $path;
            }
        }

        return class_exists(\CWM\Library\Scripture\Importer\BibleImporter::class, false);
    }

    /**
     * Delete the scheduler task row that triggered this execution.
     *
     * @since 1.1.0
     */
    private function removeOwnTask(ExecuteTaskEvent $event): void
    {
        try {
            $taskId = (int) $event->getArgument('subject')?->get('id', 0);

            if ($taskId <= 0) {
                return;
            }

            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $taskId, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            // Non-fatal — leave the row for manual cleanup if delete fails.
        }
    }

    /**
     * Write to the cwmscripture install log.
     *
     * @since 1.1.0
     */
    private function logTask(string $message, string $level): void
    {
        $priority = match ($level) {
            'error'   => Log::ERROR,
            'warning' => Log::WARNING,
            default   => Log::INFO,
        };

        Log::add('plg_task_cwmscripture: ' . $message, $priority, 'cwmscripture.install');
    }
}
