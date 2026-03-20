<?php

/**
 * Part of CWM ScriptureLinks Plugin
 *
 * @package    CWM.Plugin.Content.ScriptureLinks
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Plugin\Content\ScriptureLinks\Extension;

use CWM\Library\Scripture\Bible\AbstractBibleProvider;
use CWM\Library\Scripture\Bible\BibleProviderFactory;
use CWM\Library\Scripture\Helper\ScriptureHelper;
use CWM\Library\Scripture\Helper\ScriptureParamsHelper;
use CWM\Library\Scripture\Importer\BibleImporter;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * ScriptureLinks Content Plugin.
 *
 * Replaces {bible}...{/bible} and {scripture}...{/scripture} tags in article
 * content with scripture passages from the CWM Scripture Library.
 *
 * @since  1.0.0
 */
class ScriptureLinks extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare'     => 'onContentPrepare',
            'onAjaxScripturelinks' => 'onAjaxScripturelinks',
        ];
    }

    /**
     * AJAX dispatcher for scripture management actions.
     *
     * Routed via `com_ajax`: `index.php?option=com_ajax&plugin=scripturelinks
     * &group=content&format=json&action={action}`
     *
     * @param   \Joomla\Event\Event  $event  The event object
     *
     * @return  void
     *
     * @since  1.1.0
     */
    public function onAjaxScripturelinks(\Joomla\Event\Event $event): void
    {
        $app    = Factory::getApplication();
        $action = $app->getInput()->getCmd('action', '');

        // Actions used by the plugin's own Translations Manager tab.
        // These use com_ajax's event result pattern (POST + addResult).
        $pluginActions = ['download', 'refresh', 'remove'];

        if (\in_array($action, $pluginActions, true)) {
            Session::checkToken('post') || throw new \RuntimeException('Invalid token', 403);

            $abbr = $app->getInput()->getCmd('abbreviation', '');

            if (empty($abbr)) {
                throw new \RuntimeException('Missing translation abbreviation', 400);
            }

            AbstractBibleProvider::registerLogger();

            $result = match ($action) {
                'download' => $this->handlePluginDownload($abbr, false),
                'refresh'  => $this->handlePluginDownload($abbr, true),
                'remove'   => $this->handlePluginRemove($abbr),
            };

            $event->addResult($result);

            return;
        }

        // Actions used by Proclaim's Admin Center Scripture tab.
        // These use raw JSON output (GET token, echo + $app->close()).
        header('Content-Type: application/json; charset=utf-8');

        $dispatchers = [
            'getStatus'             => 'ajaxGetStatus',
            'getTranslations'       => 'ajaxGetTranslations',
            'downloadTranslation'   => 'ajaxDownloadTranslation',
            'removeTranslation'     => 'ajaxRemoveTranslation',
            'removeAllTranslations' => 'ajaxRemoveAllTranslations',
            'updateAllTranslations' => 'ajaxUpdateAllTranslations',
            'syncApiBible'          => 'ajaxSyncApiBible',
            'cleanupProvider'       => 'ajaxCleanupProvider',
            'saveParams'            => 'ajaxSaveParams',
        ];

        if (!isset($dispatchers[$action])) {
            echo json_encode(['success' => false, 'message' => 'Unknown action'], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        $this->{$dispatchers[$action]}($app);
    }

    /**
     * Handle download/refresh for the plugin's translations manager tab.
     *
     * @param   string  $abbreviation  Translation abbreviation
     * @param   bool    $force         Force re-download
     *
     * @return  array  Result data for com_ajax response
     *
     * @since  1.1.0
     */
    private function handlePluginDownload(string $abbreviation, bool $force): array
    {
        $count = BibleImporter::downloadAndImport($abbreviation, $force);

        if ($count < 0) {
            throw new \RuntimeException(
                \sprintf('Failed to download translation "%s". Check the log file for details.', strtoupper($abbreviation)),
                500
            );
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['installed', 'verse_count', 'data_size']))
            ->from($db->quoteName('#__bsms_bible_translations'))
            ->where($db->quoteName('abbreviation') . ' = :abbr')
            ->bind(':abbr', $abbreviation);
        $db->setQuery($query);
        $row = $db->loadObject();

        return [
            'abbreviation' => $abbreviation,
            'installed'    => (int) ($row->installed ?? 1),
            'verse_count'  => (int) ($row->verse_count ?? $count),
            'data_size'    => (int) ($row->data_size ?? 0),
            'message'      => \sprintf(
                '%s: %s verses downloaded successfully.',
                strtoupper($abbreviation),
                number_format($count)
            ),
        ];
    }

    /**
     * Handle removal for the plugin's translations manager tab.
     *
     * @param   string  $abbreviation  Translation abbreviation
     *
     * @return  array  Result data for com_ajax response
     *
     * @since  1.1.0
     */
    private function handlePluginRemove(string $abbreviation): array
    {
        BibleImporter::removeTranslation($abbreviation);

        return [
            'abbreviation' => $abbreviation,
            'installed'    => 0,
            'verse_count'  => 0,
            'data_size'    => 0,
            'message'      => \sprintf('%s: translation removed.', strtoupper($abbreviation)),
        ];
    }

    /**
     * Plugin that replaces scripture tags with linked passages.
     *
     * @param   \Joomla\Event\Event  $event  The event object
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onContentPrepare(\Joomla\Event\Event $event): void
    {
        [$context, $row, $params, $page] = array_values($event->getArguments());

        if ($context === 'com_finder.indexer') {
            return;
        }

        if (empty($row->text)) {
            return;
        }

        // Check for tags first (fast bail-out)
        if (
            stripos($row->text, '{bible') === false
            && stripos($row->text, '{scripture') === false
        ) {
            // Auto-detect mode check
            $mode = $this->params->get('mode', 'tag');

            if ($mode !== 'auto') {
                return;
            }
        }

        AbstractBibleProvider::registerLogger();

        $mode = $this->params->get('mode', 'tag');

        // Always process explicit tags
        $row->text = $this->processTags($row->text);

        // Auto-detect mode: scan for untagged scripture references
        if ($mode === 'auto') {
            $row->text = $this->processAutoDetect($row->text);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // AJAX action handlers (called from onAjaxScripturelinks)
    // ──────────────────────────────────────────────────────────────

    /**
     * Get count of locally installed translations.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxGetStatus($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        session_write_close();

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__bsms_bible_translations'))
                ->where($db->quoteName('installed') . ' = 1');
            $db->setQuery($query);
            $localCount = (int) $db->loadResult();

            echo json_encode([
                'success'     => true,
                'local_count' => $localCount,
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success'     => true,
                'local_count' => 0,
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Get list of available translations with install status.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxGetTranslations($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        session_write_close();

        try {
            // Auto-seed GetBible catalog if provider is enabled but catalog is depleted
            $pluginParams    = $this->params;
            $getbibleEnabled = (int) $pluginParams->get('provider_getbible', 1) === 1
                && (int) $pluginParams->get('gdpr_mode', 0) !== 1;

            if ($getbibleEnabled) {
                BibleImporter::seedGetBibleCatalog();
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);

            // data_size and downloaded_at are columns added in 10.1.0 — may not
            // exist yet if the migrations haven't run.  Detect and fall back.
            $colCheck = $db->setQuery(
                'SHOW COLUMNS FROM ' . $db->quoteName('#__bsms_bible_translations')
                . ' WHERE ' . $db->quoteName('Field') . ' IN ('
                . $db->quote('data_size') . ', ' . $db->quote('downloaded_at') . ')'
            )->loadObjectList('Field');

            $hasDataSize   = isset($colCheck['data_size']);
            $hasDownloaded = isset($colCheck['downloaded_at']);

            $cols = ['t.abbreviation', 't.name', 't.language', 't.installed', 't.verse_count', 't.source', 't.bundled', 't.estimated_size'];

            if ($hasDataSize) {
                $cols[] = 't.data_size';
            }

            if ($hasDownloaded) {
                $cols[] = 't.downloaded_at';
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName($cols))
                ->from($db->quoteName('#__bsms_bible_translations', 't'))
                ->order($db->quoteName('t.name') . ' ASC');
            $db->setQuery($query);
            $translations = $db->loadObjectList();

            // Build usage counts from studies table (separate query, fail-safe)
            $usageCounts = [];

            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('bible_version') . ' AS ' . $db->quoteName('abbr'))
                    ->select('COUNT(*) AS ' . $db->quoteName('cnt'))
                    ->from($db->quoteName('#__bsms_studies'))
                    ->where($db->quoteName('bible_version') . ' IS NOT NULL')
                    ->where($db->quoteName('bible_version') . ' != ' . $db->quote(''))
                    ->group($db->quoteName('bible_version'));
                $db->setQuery($query);

                foreach ($db->loadObjectList() as $row) {
                    $usageCounts[$row->abbr] = (int) $row->cnt;
                }

                $query = $db->getQuery(true)
                    ->select($db->quoteName('bible_version2') . ' AS ' . $db->quoteName('abbr'))
                    ->select('COUNT(*) AS ' . $db->quoteName('cnt'))
                    ->from($db->quoteName('#__bsms_studies'))
                    ->where($db->quoteName('bible_version2') . ' IS NOT NULL')
                    ->where($db->quoteName('bible_version2') . ' != ' . $db->quote(''))
                    ->group($db->quoteName('bible_version2'));
                $db->setQuery($query);

                foreach ($db->loadObjectList() as $row) {
                    $usageCounts[$row->abbr] = ($usageCounts[$row->abbr] ?? 0) + (int) $row->cnt;
                }
            } catch (\Exception) {
                // bible_version columns may not exist yet — usage counts stay empty
            }

            // Quick reconciliation: if a bundled translation shows installed=0
            // but already has verses in the DB, update the flag
            foreach ($translations as $t) {
                if ((int) ($t->bundled ?? 0) === 1 && (int) ($t->installed ?? 0) === 0) {
                    $countQ = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__bsms_bible_verses'))
                        ->where($db->quoteName('translation') . ' = ' . $db->quote($t->abbreviation));
                    $db->setQuery($countQ);
                    $vcnt = (int) $db->loadResult();

                    if ($vcnt > 0) {
                        $upQ = $db->getQuery(true)
                            ->update($db->quoteName('#__bsms_bible_translations'))
                            ->set($db->quoteName('installed') . ' = 1')
                            ->set($db->quoteName('verse_count') . ' = ' . $vcnt)
                            ->where($db->quoteName('abbreviation') . ' = ' . $db->quote($t->abbreviation));
                        $db->setQuery($upQ);
                        $db->execute();

                        $t->installed   = 1;
                        $t->verse_count = $vcnt;
                    }
                }
            }

            // Sum total installed size; attach usage counts
            $totalSize = 0;

            foreach ($translations as $t) {
                $totalSize      += (int) ($t->data_size ?? 0);
                $t->usage_count  = $usageCounts[$t->abbreviation] ?? 0;
            }

            echo json_encode([
                'success'      => true,
                'translations' => $translations,
                'total_size'   => $totalSize,
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Download and install a Bible translation locally.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxDownloadTranslation($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        session_write_close();

        $abbreviation = $app->getInput()->getCmd('abbreviation', '');
        $force        = (bool) $app->getInput()->getInt('force', 0);

        if (empty($abbreviation)) {
            echo json_encode(['success' => false, 'message' => 'No abbreviation provided'], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        try {
            @set_time_limit(600);

            $count = BibleImporter::downloadAndImport($abbreviation, $force);

            if ($count < 0) {
                echo json_encode([
                    'success' => false,
                    'message' => Text::sprintf('JBS_ADM_BIBLE_DOWNLOAD_FAILED', strtoupper($abbreviation)),
                ], JSON_THROW_ON_ERROR);
            } else {
                echo json_encode([
                    'success'     => true,
                    'verse_count' => $count,
                    'message'     => Text::sprintf('JBS_ADM_BIBLE_DOWNLOAD_SUCCESS', strtoupper($abbreviation), $count),
                ], JSON_THROW_ON_ERROR);
            }
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Remove a locally installed Bible translation.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxRemoveTranslation($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        session_write_close();

        $abbreviation = $app->getInput()->getCmd('abbreviation', '');

        if (empty($abbreviation)) {
            echo json_encode(['success' => false, 'message' => 'No abbreviation provided'], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        try {
            BibleImporter::removeTranslation($abbreviation);

            echo json_encode([
                'success' => true,
                'message' => Text::sprintf('JBS_ADM_BIBLE_REMOVED', strtoupper($abbreviation)),
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Remove all installed translations and their verses.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxRemoveAllTranslations($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        session_write_close();

        try {
            $count = BibleImporter::removeAllTranslations();

            echo json_encode([
                'success' => true,
                'message' => Text::sprintf('JBS_ADM_BIBLE_REMOVED_ALL', $count),
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Re-download all installed getbible translations from the API.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxUpdateAllTranslations($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        session_write_close();

        try {
            @set_time_limit(0);

            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('abbreviation'))
                ->from($db->quoteName('#__bsms_bible_translations'))
                ->where($db->quoteName('installed') . ' = 1')
                ->where($db->quoteName('source') . ' = ' . $db->quote('getbible'));
            $db->setQuery($query);
            $rows = $db->loadColumn();

            $updated = 0;
            $failed  = 0;
            $total   = \count($rows);

            foreach ($rows as $abbr) {
                $count = BibleImporter::downloadAndImport($abbr, true);

                if ($count > 0) {
                    $updated++;
                } else {
                    $failed++;
                }
            }

            echo json_encode([
                'success' => true,
                'updated' => $updated,
                'failed'  => $failed,
                'total'   => $total,
                'message' => Text::sprintf('JBS_ADM_BIBLE_UPDATE_ALL_COMPLETE', $updated, $failed),
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Sync translations from API.Bible using the configured API key.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxSyncApiBible($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        session_write_close();

        try {
            // Prefer the live key from the form (user may not have saved yet)
            $liveKey = $app->getInput()->getString('api_key', '');
            $apiKey  = !empty($liveKey) ? $liveKey : (string) $this->params->get('api_bible_api_key', '');

            if (empty($apiKey)) {
                echo json_encode([
                    'success' => false,
                    'message' => Text::_('JBS_ADM_API_BIBLE_KEY_DESC'),
                ], JSON_THROW_ON_ERROR);
                $app->close();

                return;
            }

            $http     = (new HttpFactory())->getHttp();
            $response = $http->get(
                'https://rest.api.bible/v1/bibles',
                ['api-key' => $apiKey],
                30
            );

            $httpCode = $response->getStatusCode();
            $httpBody = (string) $response->getBody();

            if ($httpCode !== 200) {
                $apiError = '';

                try {
                    $decoded = json_decode($httpBody, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $decoded = null;
                }

                if (\is_array($decoded) && isset($decoded['message'])) {
                    $apiError = $decoded['message'];
                } elseif (\is_array($decoded) && isset($decoded['error'])) {
                    $apiError = $decoded['error'];
                }

                $detail = $apiError
                    ? Text::sprintf('JBS_ADM_SYNC_FAILED_DETAIL', $httpCode, $apiError)
                    : Text::sprintf('JBS_ADM_SYNC_FAILED_CODE', $httpCode);

                echo json_encode([
                    'success' => false,
                    'message' => $detail,
                ], JSON_THROW_ON_ERROR);
                $app->close();

                return;
            }

            try {
                $data = json_decode($httpBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $data = null;
            }

            if (!\is_array($data) || !isset($data['data'])) {
                $snippet = substr($httpBody, 0, 200);

                echo json_encode([
                    'success' => false,
                    'message' => Text::sprintf(
                        'JBS_ADM_SYNC_FAILED_DETAIL',
                        $httpCode,
                        'Unexpected response format: ' . $snippet
                    ),
                ], JSON_THROW_ON_ERROR);
                $app->close();

                return;
            }

            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $count = 0;

            foreach ($data['data'] as $bible) {
                $bibleId  = $bible['id'] ?? '';
                $name     = $bible['name'] ?? ($bible['nameLocal'] ?? '');
                $abbr     = strtolower($bible['abbreviation'] ?? $bible['abbreviationLocal'] ?? '');
                $language = $bible['language']['id'] ?? 'en';

                if (empty($bibleId) || empty($abbr) || empty($name)) {
                    continue;
                }

                $abbr = substr($abbr, 0, 20);

                $query = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'source']))
                    ->from($db->quoteName('#__bsms_bible_translations'))
                    ->where($db->quoteName('abbreviation') . ' = :abbr')
                    ->bind(':abbr', $abbr);
                $db->setQuery($query);
                $existing = $db->loadObject();

                if ($existing && $existing->source !== 'api_bible') {
                    continue;
                }

                if ($existing) {
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__bsms_bible_translations'))
                        ->set($db->quoteName('name') . ' = :name')
                        ->set($db->quoteName('language') . ' = :lang')
                        ->set($db->quoteName('provider_id') . ' = :pid')
                        ->where($db->quoteName('id') . ' = ' . (int) $existing->id)
                        ->bind(':name', $name)
                        ->bind(':lang', $language)
                        ->bind(':pid', $bibleId);
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    $source = 'api_bible';
                    $query  = $db->getQuery(true)
                        ->insert($db->quoteName('#__bsms_bible_translations'))
                        ->columns($db->quoteName(['abbreviation', 'name', 'language', 'source', 'provider_id']))
                        ->values(':abbr2, :name2, :lang2, :source2, :pid2')
                        ->bind(':abbr2', $abbr)
                        ->bind(':name2', $name)
                        ->bind(':lang2', $language)
                        ->bind(':source2', $source)
                        ->bind(':pid2', $bibleId);
                    $db->setQuery($query);
                    $db->execute();
                }

                $count++;
            }

            echo json_encode([
                'success' => true,
                'count'   => $count,
                'message' => Text::sprintf('JBS_ADM_SYNC_COMPLETE', $count),
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => Text::sprintf(
                    'JBS_ADM_SYNC_FAILED_DETAIL',
                    0,
                    $e->getMessage()
                ),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Remove non-installed translation records from a provider.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxCleanupProvider($app): void
    {
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        $source = $app->getInput()->getCmd('source', '');

        if (empty($source)) {
            echo json_encode(['success' => false, 'message' => 'No source provided'], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        try {
            $count = BibleImporter::removeProviderEntries($source);

            echo json_encode([
                'success' => true,
                'count'   => $count,
                'message' => Text::sprintf('JBS_ADM_PROVIDER_CLEANUP_DONE', $count),
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    /**
     * Save scripture params to the plugin's #__extensions row.
     *
     * @param   \Joomla\CMS\Application\CMSApplicationInterface  $app  Application
     *
     * @return  void
     *
     * @since  1.1.0
     */
    private function ajaxSaveParams($app): void
    {
        if (!Session::checkToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        if (!$app->getIdentity()->authorise('core.admin')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')], JSON_THROW_ON_ERROR);
            $app->close();

            return;
        }

        try {
            $input  = $app->getInput();
            $params = ScriptureParamsHelper::getParams();

            // Update only known scripture keys
            $keys = ['provider_getbible', 'gdpr_mode', 'provider_api_bible', 'api_bible_api_key', 'cache_days', 'default_version'];

            foreach ($keys as $key) {
                $value = $input->getString($key, null);

                if ($value !== null) {
                    $params->set($key, $value);
                }
            }

            ScriptureParamsHelper::save($params);

            echo json_encode(['success' => true], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $app->close();
    }

    // ──────────────────────────────────────────────────────────────
    // Content processing methods
    // ──────────────────────────────────────────────────────────────

    /**
     * Process {bible} and {scripture} tags in content.
     *
     * Supports:
     * - {scripture}John 3:16{/scripture}
     * - {scripture kjv}John 3:16{/scripture}
     * - {bible}John 3:16{/bible}
     * - {bible 9}John 3:16{/bible} (legacy numeric version)
     *
     * @param   string  $text  Article content
     *
     * @return  string  Content with tags replaced
     *
     * @since  1.0.0
     */
    private function processTags(string $text): string
    {
        $pattern = '#\{(?:bible|scripture)\s*([a-zA-Z0-9]*)\}(.+?)\{/(?:bible|scripture)\}#is';

        return preg_replace_callback($pattern, [$this, 'replaceTag'], $text);
    }

    /**
     * Callback for tag replacement.
     *
     * @param   array  $matches  Regex matches
     *
     * @return  string  Replacement HTML
     *
     * @since  1.0.0
     */
    private function replaceTag(array $matches): string
    {
        $versionOverride = trim($matches[1]);
        $referenceText   = trim($matches[2]);

        if (empty($referenceText)) {
            return '';
        }

        $version = !empty($versionOverride)
            ? $versionOverride
            : $this->params->get('default_version', 'kjv');

        return $this->buildScriptureLink($referenceText, $version);
    }

    /**
     * Auto-detect scripture references in content and replace them.
     *
     * @param   string  $text  Article content
     *
     * @return  string  Content with detected references replaced
     *
     * @since  1.0.0
     */
    private function processAutoDetect(string $text): string
    {
        // Build regex from known book names and abbreviations
        $abbreviations = ScriptureHelper::getAbbreviations();
        $bookNames     = array_keys($abbreviations);

        // Sort longest first to avoid partial matches
        usort($bookNames, static fn ($a, $b) => strlen($b) - strlen($a));

        $escapedBooks = array_map(static fn ($name) => preg_quote($name, '#'), $bookNames);
        $booksPattern = implode('|', $escapedBooks);

        // Match: "Book Chapter:Verse[-Verse]" patterns not already inside tags
        $pattern = '#(?<!\{(?:bible|scripture)[^}]*\})(?<!\w)\b(' . $booksPattern . ')\s+(\d{1,3})(?::(\d{1,3})(?:\s*-\s*(?:(\d{1,3}):)?(\d{1,3}))?)?\b(?!\s*\{/(?:bible|scripture)\})#i';

        $version = $this->params->get('default_version', 'kjv');

        return preg_replace_callback($pattern, function (array $matches) use ($version) {
            $fullMatch = $matches[0];

            // Verify it's a valid scripture reference
            $ref = ScriptureHelper::parseReference($fullMatch);

            if ($ref === null) {
                return $fullMatch;
            }

            return $this->buildScriptureLink($fullMatch, $version);
        }, $text);
    }

    /**
     * Build the HTML output for a scripture reference.
     *
     * @param   string  $referenceText  Human-readable reference (e.g. "John 3:16")
     * @param   string  $version        Bible version abbreviation
     *
     * @return  string  HTML output
     *
     * @since  1.0.0
     */
    private function buildScriptureLink(string $referenceText, string $version): string
    {
        $display = $this->params->get('display', 'link');

        // Parse the reference to build the API query
        $ref = ScriptureHelper::parseReference($referenceText);

        if ($ref === null) {
            return $referenceText;
        }

        // Build a + separated reference for the provider
        $formattedRef = ScriptureHelper::formatReference(
            $ref->booknumber,
            $ref->chapterBegin,
            $ref->verseBegin,
            $ref->chapterEnd,
            $ref->verseEnd
        );

        $queryRef = str_replace(' ', '+', $formattedRef);

        // Fetch the passage text from a provider
        $providerParams = new Registry([
            'provider_getbible'  => $this->params->get('provider_getbible', 1),
            'provider_api_bible' => $this->params->get('provider_api_bible', 0),
            'api_bible_api_key'  => $this->params->get('api_bible_api_key', ''),
            'gdpr_mode'          => $this->params->get('gdpr_mode', 0),
        ]);

        try {
            $provider = BibleProviderFactory::getProviderForTranslation($version, $providerParams);

            $cacheDays = (int) $this->params->get('cache_days', 30);

            if ($cacheDays > 0) {
                $provider->setCacheTtl($cacheDays * 86400);
            }

            $result = $provider->getPassage($queryRef, $version);

            if ($result->hasText()) {
                return $this->renderPassage($referenceText, $result->text, $result->copyright, $display);
            }
        } catch (\Throwable $e) {
            Log::add('ScriptureLinks: Error fetching "' . $referenceText . '": ' . $e->getMessage(), Log::ERROR, 'cwmscripture.bible');
        }

        // Fallback: return the reference as styled plain text
        return '<span class="scripture-ref scripture-unavailable">'
            . htmlspecialchars($referenceText)
            . '</span>';
    }

    /**
     * Render a scripture passage in the configured display mode.
     *
     * @param   string  $reference  Human-readable reference
     * @param   string  $text       Passage text (may contain HTML)
     * @param   string  $copyright  Copyright notice
     * @param   string  $display    Display mode: 'tooltip', 'inline', 'popup'
     *
     * @return  string  HTML output
     *
     * @since  1.0.0
     */
    private function renderPassage(string $reference, string $text, string $copyright, string $display): string
    {
        $copyrightHtml = '';

        if (!empty($copyright)) {
            $copyrightHtml = '<div class="scripture-copyright">' . htmlspecialchars($copyright) . '</div>';
        }

        switch ($display) {
            case 'tooltip':
                $tooltipText = strip_tags($text);

                if (strlen($tooltipText) > 500) {
                    $tooltipText = substr($tooltipText, 0, 497) . '...';
                }

                return '<span class="scripture-tooltip" title="' . htmlspecialchars($tooltipText) . '">'
                    . htmlspecialchars($reference)
                    . '</span>';

            case 'inline':
                return '<div class="scripture-container scripture-inline">'
                    . '<strong class="scripture-ref">' . htmlspecialchars($reference) . '</strong> '
                    . '<div class="scripture-text">'
                    . '<div class="scripture-body">' . $text . '</div>'
                    . $copyrightHtml
                    . '</div></div>';

            case 'popup':
                $id = 'scripture_' . md5($reference . $text);

                return '<a href="#" class="scripture-link scripture-popup-trigger" '
                    . 'onclick="var w=window.open(\'\',\'scripture\',\'width=700,height=500,scrollbars=yes\');'
                    . 'var t=document.getElementById(\'' . $id . '\');'
                    . 'if(w){w.document.open();w.document.write(t.innerHTML);w.document.close();}return false;">'
                    . htmlspecialchars($reference)
                    . '</a>'
                    . '<template id="' . $id . '">'
                    . '<!DOCTYPE html><html><head><meta charset="utf-8">'
                    . '<title>' . htmlspecialchars($reference) . '</title>'
                    . '<style>body{font-family:Georgia,serif;line-height:1.8;padding:2em;max-width:700px;margin:0 auto;color:#333;}'
                    . 'sup{font-size:0.65em;font-weight:700;color:#8b4513;margin-right:2px;}'
                    . '.scripture-copyright{margin-top:1em;padding-top:0.75em;border-top:1px solid #e0ddd5;font-size:0.8em;color:#888;font-style:italic;}</style>'
                    . '</head><body>'
                    . '<h3>' . htmlspecialchars($reference) . '</h3>'
                    . $text . $copyrightHtml
                    . '</body></html></template>';

            default:
                return htmlspecialchars($reference);
        }
    }
}
