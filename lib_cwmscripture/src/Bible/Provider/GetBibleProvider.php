<?php

/**
 * Part of CWM Scripture Library
 *
 * @package    CWM.Library.Scripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Library\Scripture\Bible\Provider;

use CWM\Library\Scripture\Bible\AbstractBibleProvider;
use CWM\Library\Scripture\Bible\BiblePassageResult;
use Joomla\CMS\Log\Log;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * GetBible.net API provider.
 *
 * Calls the GetBible.net v2 API to retrieve scripture passages.
 * Results are cached in #__bsms_scripture_cache.
 *
 * API endpoint: https://query.getbible.net/v2/{translation}/{reference}
 *
 * @since  1.0.0
 */
class GetBibleProvider extends AbstractBibleProvider
{
    /**
     * API base URL.
     *
     * @var  string
     * @since  1.0.0
     */
    private const API_BASE = 'https://query.getbible.net/v2/';

    /**
     * @inheritDoc
     */
    public function getPassage(string $reference, string $translation): BiblePassageResult
    {
        $cached = $this->readCache('getbible', $translation, $reference);

        if ($cached) {
            return $cached;
        }

        $apiRef = str_replace('+', ' ', $reference);

        // Fix same-chapter redundant prefix: "Luke 12:54-12:56" -> "Luke 12:54-56"
        $apiRef = preg_replace_callback(
            '/(\d+):(\d+)-(\d+):(\d+)$/',
            static function (array $m): string {
                if ($m[1] === $m[3]) {
                    return $m[1] . ':' . $m[2] . '-' . $m[4];
                }

                return $m[0];
            },
            $apiRef
        );

        $encodedRef = str_replace('%3A', ':', rawurlencode($apiRef));
        $url        = self::API_BASE . rawurlencode($translation) . '/' . $encodedRef;
        $body       = $this->httpGet($url, 15);

        if ($body === null) {
            Log::add('GetBible: API returned no data for "' . $apiRef . '" (' . $translation . ')', Log::WARNING, 'cwmscripture.bible');

            return new BiblePassageResult(
                reference: $reference,
                translation: $translation
            );
        }

        if (self::isHtmlResponse($body)) {
            Log::add('GetBible: HTML gatekeeper response for "' . $apiRef . '" (' . $translation . ')', Log::WARNING, 'cwmscripture.bible');

            return new BiblePassageResult(
                reference: $reference,
                translation: $translation
            );
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        if (!\is_array($data) || empty($data)) {
            Log::add('GetBible: Invalid JSON response for "' . $apiRef . '" (' . $translation . ')', Log::ERROR, 'cwmscripture.bible');

            return new BiblePassageResult(
                reference: $reference,
                translation: $translation
            );
        }

        $text      = '';
        $copyright = '';

        foreach ($data as $passage) {
            if (!\is_array($passage) || !isset($passage['verses'])) {
                continue;
            }

            if (!empty($passage['name'])) {
                if ($text !== '') {
                    $text .= ' ';
                }
            }

            foreach ($passage['verses'] as $verse) {
                $verseNum  = $verse['verse'] ?? '';
                $verseText = $verse['text'] ?? '';
                $text .= '<sup>' . htmlspecialchars((string) $verseNum) . '</sup>'
                    . htmlspecialchars(trim($verseText)) . ' ';
            }

            if (empty($copyright) && !empty($passage['translation_note'])) {
                $copyright = $passage['translation_note'];
            }
        }

        $text = trim($text);

        if ($text === '') {
            return new BiblePassageResult(
                reference: $reference,
                translation: $translation
            );
        }

        $this->writeCache('getbible', $translation, $reference, $text, $copyright);

        return new BiblePassageResult(
            text: $text,
            reference: $reference,
            translation: $translation,
            copyright: $copyright,
            isHtml: true
        );
    }

    /**
     * @inheritDoc
     */
    public function getAvailableTranslations(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['abbreviation', 'name', 'language']))
            ->from($db->quoteName('#__bsms_bible_translations'))
            ->where($db->quoteName('source') . ' = ' . $db->quote('getbible'))
            ->order($db->quoteName('name') . ' ASC');

        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
    }

    /**
     * @inheritDoc
     */
    public function returnsText(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isOfflineCapable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'getbible';
    }
}
