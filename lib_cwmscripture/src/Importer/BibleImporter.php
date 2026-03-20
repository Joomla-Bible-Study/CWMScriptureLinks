<?php

/**
 * Part of CWM Scripture Library
 *
 * @package    CWM.Library.Scripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Library\Scripture\Importer;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Http\HttpFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Bible data importer.
 *
 * Downloads translation data from the GetBible.net v2 API (book-by-book)
 * and batch-inserts verses into #__bsms_bible_verses.
 *
 * @since  1.0.0
 */
class BibleImporter
{
    /**
     * GetBible.net v2 API base URL.
     *
     * @var  string
     * @since  1.0.0
     */
    private const API_BASE_URL = 'https://api.getbible.net/v2/';

    /**
     * Batch insert size.
     *
     * @var  int
     * @since  1.0.0
     */
    private const BATCH_SIZE = 500;

    /**
     * HTTP request timeout in seconds.
     *
     * @var  int
     * @since  1.0.0
     */
    private const HTTP_TIMEOUT = 120;

    /**
     * Download and import a translation from GetBible.net API.
     *
     * @param   string  $abbreviation  Translation abbreviation (e.g. "kjv", "web")
     * @param   bool    $force         Re-download even if verses already exist locally
     *
     * @return  int  Number of verses imported, or -1 on failure
     *
     * @since  1.0.0
     */
    public static function downloadAndImport(string $abbreviation, bool $force = false): int
    {
        Log::addLogger(
            ['text_file' => 'cwmscripture.bible.php'],
            Log::ALL,
            ['cwmscripture.bible']
        );

        if (!$force) {
            $db     = Factory::getContainer()->get(DatabaseInterface::class);
            $checkQ = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__bsms_bible_verses'))
                ->where($db->quoteName('translation') . ' = :abbr')
                ->bind(':abbr', $abbreviation);
            $db->setQuery($checkQ);
            $existing = (int) $db->loadResult();

            if ($existing > 0) {
                self::updateTranslationRecord($abbreviation, $existing, [], 0);
                Log::add(
                    'BibleImporter: skipped download for "' . $abbreviation . '" — ' . $existing . ' verses already present',
                    Log::INFO,
                    'cwmscripture.bible'
                );

                return $existing;
            }
        }

        $factory = new HttpFactory();
        $http    = $factory->getHttp();
        $headers = ['Accept' => 'application/json'];

        $booksUrl = self::API_BASE_URL . $abbreviation . '/books.json';

        try {
            $response = $http->get($booksUrl, $headers, self::HTTP_TIMEOUT);

            if ($response->getStatusCode() !== 200) {
                Log::add('BibleImporter: HTTP ' . $response->getStatusCode() . ' fetching book list for "' . $abbreviation . '"', Log::ERROR, 'cwmscripture.bible');

                return -1;
            }

            try {
                $books = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $books = null;
            }

            if (!\is_array($books) || empty($books)) {
                Log::add('BibleImporter: Invalid or empty book list for "' . $abbreviation . '"', Log::ERROR, 'cwmscripture.bible');

                return -1;
            }
        } catch (\Exception $e) {
            Log::add('BibleImporter: Error fetching book list: ' . $e->getMessage(), Log::ERROR, 'cwmscripture.bible');

            return -1;
        }

        $db         = Factory::getContainer()->get(DatabaseInterface::class);
        $totalCount = 0;
        $totalSize  = 0;
        $batch      = [];
        $metadata   = [];

        self::removeTranslationVerses($abbreviation);

        foreach ($books as $bookInfo) {
            if (!\is_array($bookInfo) || !isset($bookInfo['nr'])) {
                continue;
            }

            $bookNr  = (int) $bookInfo['nr'];
            $bookUrl = self::API_BASE_URL . $abbreviation . '/' . $bookNr . '.json';

            try {
                $bookResponse = $http->get($bookUrl, [], self::HTTP_TIMEOUT);

                if ($bookResponse->getStatusCode() !== 200) {
                    continue;
                }

                try {
                    $bookData = json_decode((string) $bookResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    continue;
                }

                if (!\is_array($bookData) || !isset($bookData['chapters'])) {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }

            if (empty($metadata)) {
                $metadata = [
                    'translation' => $bookData['translation'] ?? strtoupper($abbreviation),
                    'lang'        => $bookData['lang'] ?? 'en',
                ];
            }

            foreach ($bookData['chapters'] as $chapterData) {
                if (!\is_array($chapterData) || !isset($chapterData['verses'])) {
                    continue;
                }

                $chapterNumber = (int) ($chapterData['chapter'] ?? 0);

                foreach ($chapterData['verses'] as $verseData) {
                    if (!\is_array($verseData) || !isset($verseData['verse'], $verseData['text'])) {
                        continue;
                    }

                    $totalSize += \strlen($verseData['text']);
                    $batch[]    = [
                        'translation' => $abbreviation,
                        'book'        => $bookNr,
                        'chapter'     => $chapterNumber,
                        'verse'       => (int) $verseData['verse'],
                        'text'        => $verseData['text'],
                    ];

                    if (\count($batch) >= self::BATCH_SIZE) {
                        self::insertBatch($db, $batch);
                        $totalCount += \count($batch);
                        $batch = [];
                    }
                }
            }
        }

        if (!empty($batch)) {
            self::insertBatch($db, $batch);
            $totalCount += \count($batch);
        }

        self::updateTranslationRecord($abbreviation, $totalCount, $metadata, $totalSize);

        return $totalCount;
    }

    /**
     * Import verses from a JSON string (single book format).
     *
     * @param   string  $json          Raw JSON string (book-level from GetBible v2 API)
     * @param   string  $abbreviation  Translation abbreviation
     *
     * @return  int  Number of verses imported, or -1 on failure
     *
     * @since  1.0.0
     */
    public static function importFromJson(string $json, string $abbreviation): int
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return -1;
        }

        if (!\is_array($data)) {
            return -1;
        }

        $db         = Factory::getContainer()->get(DatabaseInterface::class);
        $totalCount = 0;
        $totalSize  = 0;
        $batch      = [];

        self::removeTranslationVerses($abbreviation);

        if (isset($data['chapters'])) {
            $bookNr = (int) ($data['nr'] ?? $data['book_nr'] ?? 1);

            foreach ($data['chapters'] as $chapterData) {
                if (!\is_array($chapterData) || !isset($chapterData['verses'])) {
                    continue;
                }

                $chapterNumber = (int) ($chapterData['chapter'] ?? 0);

                foreach ($chapterData['verses'] as $verseData) {
                    if (!\is_array($verseData) || !isset($verseData['verse'], $verseData['text'])) {
                        continue;
                    }

                    $totalSize += \strlen($verseData['text']);
                    $batch[]    = [
                        'translation' => $abbreviation,
                        'book'        => $bookNr,
                        'chapter'     => $chapterNumber,
                        'verse'       => (int) $verseData['verse'],
                        'text'        => $verseData['text'],
                    ];

                    if (\count($batch) >= self::BATCH_SIZE) {
                        self::insertBatch($db, $batch);
                        $totalCount += \count($batch);
                        $batch = [];
                    }
                }
            }
        }

        if (!empty($batch)) {
            self::insertBatch($db, $batch);
            $totalCount += \count($batch);
        }

        $metadata = [
            'translation' => $data['translation'] ?? strtoupper($abbreviation),
            'lang'        => $data['lang'] ?? 'en',
        ];
        self::updateTranslationRecord($abbreviation, $totalCount, $metadata, $totalSize);

        return $totalCount;
    }

    /**
     * Check whether a translation is a protected core translation.
     *
     * @param   string  $abbreviation  Translation abbreviation
     *
     * @return  bool
     *
     * @since  1.0.0
     */
    public static function isCoreTranslation(string $abbreviation): bool
    {
        return \in_array(strtolower($abbreviation), ['kjv', 'web'], true);
    }

    /**
     * Check if a translation is installed (has verses).
     *
     * @param   string  $abbreviation  Translation abbreviation
     *
     * @return  bool
     *
     * @since  1.0.0
     */
    public static function isInstalled(string $abbreviation): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('installed'))
            ->from($db->quoteName('#__bsms_bible_translations'))
            ->where($db->quoteName('abbreviation') . ' = :abbr')
            ->bind(':abbr', $abbreviation);
        $db->setQuery($query);

        return (int) $db->loadResult() === 1;
    }

    /**
     * Remove a translation and its verses (non-core only).
     *
     * @param   string  $abbreviation  Translation abbreviation
     *
     * @return  void
     *
     * @throws  \RuntimeException  If the translation is a core translation
     *
     * @since  1.0.0
     */
    public static function removeTranslation(string $abbreviation): void
    {
        if (self::isCoreTranslation($abbreviation)) {
            throw new \RuntimeException(
                \sprintf('Cannot remove core translation %s', strtoupper($abbreviation))
            );
        }

        self::removeTranslationVerses($abbreviation);

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__bsms_bible_translations'))
            ->set($db->quoteName('installed') . ' = 0')
            ->set($db->quoteName('verse_count') . ' = 0')
            ->set($db->quoteName('data_size') . ' = 0')
            ->where($db->quoteName('abbreviation') . ' = :abbr')
            ->bind(':abbr', $abbreviation);
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Seed the GetBible translation catalog if depleted.
     *
     * @return  int  Number of rows inserted
     *
     * @since  1.0.0
     */
    public static function seedGetBibleCatalog(): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bsms_bible_translations'))
            ->where($db->quoteName('source') . ' = ' . $db->quote('getbible'));
        $db->setQuery($query);
        $existing = (int) $db->loadResult();

        if ($existing >= \count(self::GETBIBLE_SEED)) {
            return 0;
        }

        $inserted = 0;

        foreach (self::GETBIBLE_SEED as [$abbr, $name, $lang, $bundled, $size]) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__bsms_bible_translations'))
                ->where($db->quoteName('abbreviation') . ' = :abbr')
                ->bind(':abbr', $abbr);
            $db->setQuery($query);

            if ((int) $db->loadResult() > 0) {
                continue;
            }

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__bsms_bible_translations'))
                ->columns($db->quoteName(['abbreviation', 'name', 'language', 'source', 'installed', 'bundled', 'estimated_size']))
                ->values(':abbr, :name, :lang, ' . $db->quote('getbible') . ', 0, :bundled, :size')
                ->bind(':abbr', $abbr)
                ->bind(':name', $name)
                ->bind(':lang', $lang)
                ->bind(':bundled', $bundled, ParameterType::INTEGER)
                ->bind(':size', $size, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Default GetBible translation catalog entries.
     *
     * @var  array
     * @since  1.0.0
     */
    private const GETBIBLE_SEED = [
        ['kjv',          'King James Version',          'en', 1, 4000000],
        ['akjv',         'American King James Version', 'en', 0, 4000000],
        ['web',          'World English Bible',         'en', 1, 4300000],
        ['asv',          'American Standard Version',   'en', 0, 4100000],
        ['ylt',          "Young's Literal Translation", 'en', 0, 4000000],
        ['basicenglish', 'Bible in Basic English',      'en', 0, 3500000],
        ['douayrheims',  'Douay-Rheims Bible',          'en', 0, 4200000],
        ['wb',           'Webster Bible',               'en', 0, 4000000],
        ['darby',        'Darby Translation',           'en', 0, 4000000],
        ['vulgate',      'Vulgata Clementina',          'la', 0, 3800000],
        ['almeida',      'Almeida Atualizada',          'pt', 0, 4000000],
        ['luther1545',   'Luther (1545)',               'de', 0, 4200000],
        ['ls1910',       'Louis Segond 1910',           'fr', 0, 4100000],
        ['synodal',      'Synodal Translation',         'ru', 0, 4500000],
        ['valera',       'Reina Valera (1909)',         'es', 0, 4100000],
        ['karoli',       'Karoli Bible',                'hu', 0, 4000000],
        ['giovanni',     'Giovanni Diodati Bible',      'it', 0, 4100000],
        ['cornilescu',   'Cornilescu Bible',            'ro', 0, 3900000],
        ['korean',       'Korean Bible',                'ko', 0, 3800000],
        ['cus',          'Chinese Union Simplified',    'zh', 0, 2500000],
    ];

    /**
     * Delete all verses for a translation.
     *
     * @param   string  $abbreviation  Translation abbreviation
     *
     * @return  void
     *
     * @since  1.0.0
     */
    private static function removeTranslationVerses(string $abbreviation): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__bsms_bible_verses'))
            ->where($db->quoteName('translation') . ' = :abbr')
            ->bind(':abbr', $abbreviation);
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Batch-insert verse rows.
     *
     * @param   DatabaseInterface  $db     Database driver
     * @param   array              $batch  Array of verse row data
     *
     * @return  void
     *
     * @since  1.0.0
     */
    private static function insertBatch(DatabaseInterface $db, array $batch): void
    {
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__bsms_bible_verses'))
            ->columns($db->quoteName(['translation', 'book', 'chapter', 'verse', 'text']));

        foreach ($batch as $row) {
            $query->values(
                $db->quote($row['translation']) . ', '
                . (int) $row['book'] . ', '
                . (int) $row['chapter'] . ', '
                . (int) $row['verse'] . ', '
                . $db->quote($row['text'])
            );
        }

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Update or create the translation record in #__bsms_bible_translations.
     *
     * @param   string  $abbreviation  Translation abbreviation
     * @param   int     $verseCount    Number of verses imported
     * @param   array   $metadata      Metadata with 'translation' and 'lang' keys
     * @param   int     $dataSize      Total stored text size in bytes
     *
     * @return  void
     *
     * @since  1.0.0
     */
    private static function updateTranslationRecord(string $abbreviation, int $verseCount, array $metadata, int $dataSize = 0): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $name      = $metadata['translation'] ?? strtoupper($abbreviation);
        $language  = $metadata['lang'] ?? 'en';
        $copyright = $metadata['translation_note'] ?? '';

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__bsms_bible_translations'))
            ->where($db->quoteName('abbreviation') . ' = :abbr')
            ->bind(':abbr', $abbreviation);
        $db->setQuery($query);
        $exists = (int) $db->loadResult() > 0;

        if ($exists) {
            $now   = Factory::getDate()->toSql();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__bsms_bible_translations'))
                ->set($db->quoteName('installed') . ' = 1')
                ->set($db->quoteName('verse_count') . ' = :count')
                ->set($db->quoteName('name') . ' = :name')
                ->set($db->quoteName('language') . ' = :lang')
                ->set($db->quoteName('copyright') . ' = :copy')
                ->set($db->quoteName('data_size') . ' = :size')
                ->set($db->quoteName('downloaded_at') . ' = :dlat')
                ->where($db->quoteName('abbreviation') . ' = :abbr')
                ->bind(':count', $verseCount, ParameterType::INTEGER)
                ->bind(':name', $name)
                ->bind(':lang', $language)
                ->bind(':copy', $copyright)
                ->bind(':size', $dataSize, ParameterType::INTEGER)
                ->bind(':dlat', $now)
                ->bind(':abbr', $abbreviation);
        } else {
            $now    = Factory::getDate()->toSql();
            $query  = $db->getQuery(true)
                ->insert($db->quoteName('#__bsms_bible_translations'))
                ->columns($db->quoteName([
                    'abbreviation', 'name', 'language', 'source', 'installed',
                    'verse_count', 'copyright', 'data_size', 'downloaded_at',
                ]))
                ->values(
                    $db->quote($abbreviation) . ', '
                    . $db->quote($name) . ', '
                    . $db->quote($language) . ', '
                    . $db->quote('getbible') . ', '
                    . '1, '
                    . (int) $verseCount . ', '
                    . $db->quote($copyright) . ', '
                    . (int) $dataSize . ', '
                    . $db->quote($now)
                );
        }

        $db->setQuery($query);
        $db->execute();
    }
}
