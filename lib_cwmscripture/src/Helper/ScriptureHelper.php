<?php

/**
 * Part of CWM Scripture Library
 *
 * @package    CWM.Library.Scripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Library\Scripture\Helper;

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Scripture helper for parsing, formatting, and managing scripture references.
 *
 * Contains only the generic parsing/formatting logic. Study-specific methods
 * (getScripturesForStudy, saveScriptures, etc.) remain in Proclaim's own helper.
 *
 * @since  1.0.0
 */
class ScriptureHelper
{
    /**
     * Regex for parsing scripture references.
     *
     * @var string
     * @since 1.0.0
     */
    private const REFERENCE_REGEX = '/^(.+?)\s+(\d+)(?::(\d+)(?:\s*-\s*(?:(\d+):)?(\d+))?)?$/';

    /**
     * English abbreviation to booknumber map.
     * Book numbering: 101-166 canonical, 167-173 deuterocanonical.
     *
     * @var array<string, int>
     * @since 1.0.0
     */
    private const array ABBREVIATIONS = [
        // Old Testament
        'gen'          => 101, 'genesis' => 101,
        'ex'           => 102, 'exod' => 102, 'exodus' => 102,
        'lev'          => 103, 'leviticus' => 103,
        'num'          => 104, 'numbers' => 104,
        'deut'         => 105, 'deuteronomy' => 105,
        'josh'         => 106, 'joshua' => 106,
        'judg'         => 107, 'judges' => 107,
        'ruth'         => 108,
        '1 sam'        => 109, '1sam' => 109, '1 samuel' => 109, '1samuel' => 109,
        '2 sam'        => 110, '2sam' => 110, '2 samuel' => 110, '2samuel' => 110,
        '1 kgs'        => 111, '1kgs' => 111, '1 kings' => 111, '1kings' => 111,
        '2 kgs'        => 112, '2kgs' => 112, '2 kings' => 112, '2kings' => 112,
        '1 chr'        => 113, '1chr' => 113, '1 chron' => 113, '1chron' => 113,
        '1 chronicles' => 113, '1chronicles' => 113,
        '2 chr'        => 114, '2chr' => 114, '2 chron' => 114, '2chron' => 114,
        '2 chronicles' => 114, '2chronicles' => 114,
        'ezra'         => 115,
        'neh'          => 116, 'nehemiah' => 116,
        'esth'         => 117, 'esther' => 117,
        'job'          => 118,
        'ps'           => 119, 'psa' => 119, 'psalm' => 119, 'psalms' => 119,
        'prov'         => 120, 'proverbs' => 120,
        'eccl'         => 121, 'ecclesiastes' => 121,
        'song'         => 122, 'song of solomon' => 122, 'sos' => 122,
        'isa'          => 123, 'isaiah' => 123,
        'jer'          => 124, 'jeremiah' => 124,
        'lam'          => 125, 'lamentations' => 125,
        'ezek'         => 126, 'ezekiel' => 126,
        'dan'          => 127, 'daniel' => 127,
        'hos'          => 128, 'hosea' => 128,
        'joel'         => 129,
        'amos'         => 130,
        'obad'         => 131, 'obadiah' => 131,
        'jonah'        => 132,
        'mic'          => 133, 'micah' => 133,
        'nah'          => 134, 'nahum' => 134,
        'hab'          => 135, 'habakkuk' => 135,
        'zeph'         => 136, 'zephaniah' => 136,
        'hag'          => 137, 'haggai' => 137,
        'zech'         => 138, 'zechariah' => 138,
        'mal'          => 139, 'malachi' => 139,

        // New Testament
        'matt'    => 140, 'mt' => 140, 'matthew' => 140,
        'mark'    => 141, 'mk' => 141,
        'luke'    => 142, 'lk' => 142,
        'john'    => 143, 'jn' => 143,
        'acts'    => 144,
        'rom'     => 145, 'romans' => 145,
        '1 cor'   => 146, '1cor' => 146, '1 corinthians' => 146, '1corinthians' => 146,
        '2 cor'   => 147, '2cor' => 147, '2 corinthians' => 147, '2corinthians' => 147,
        'gal'     => 148, 'galatians' => 148,
        'eph'     => 149, 'ephesians' => 149,
        'phil'    => 150, 'philippians' => 150,
        'col'     => 151, 'colossians' => 151,
        '1 thess' => 152, '1thess' => 152, '1 thessalonians' => 152, '1thessalonians' => 152,
        '2 thess' => 153, '2thess' => 153, '2 thessalonians' => 153, '2thessalonians' => 153,
        '1 tim'   => 154, '1tim' => 154, '1 timothy' => 154, '1timothy' => 154,
        '2 tim'   => 155, '2tim' => 155, '2 timothy' => 155, '2timothy' => 155,
        'titus'   => 156, 'tit' => 156,
        'phlm'    => 157, 'philemon' => 157, 'phm' => 157,
        'heb'     => 158, 'hebrews' => 158,
        'jas'     => 159, 'james' => 159,
        '1 pet'   => 160, '1pet' => 160, '1 peter' => 160, '1peter' => 160,
        '2 pet'   => 161, '2pet' => 161, '2 peter' => 161, '2peter' => 161,
        '1 john'  => 162, '1john' => 162, '1 jn' => 162, '1jn' => 162,
        '2 john'  => 163, '2john' => 163, '2 jn' => 163, '2jn' => 163,
        '3 john'  => 164, '3john' => 164, '3 jn' => 164, '3jn' => 164,
        'jude'    => 165,
        'rev'     => 166, 'revelation' => 166, 'revelations' => 166,

        // Deuterocanonical
        'tobit'       => 167, 'tob' => 167,
        'judith'      => 168, 'jdt' => 168,
        '1 maccabees' => 169, '1maccabees' => 169, '1 macc' => 169, '1macc' => 169,
        '2 maccabees' => 170, '2maccabees' => 170, '2 macc' => 170, '2macc' => 170,
        'wisdom'      => 171, 'wis' => 171,
        'sirach'      => 172, 'sir' => 172,
        'baruch'      => 173, 'bar' => 173,
    ];

    /**
     * Language key to booknumber map.
     *
     * @var array<string, int>
     * @since 1.0.0
     */
    private const array BOOK_KEYS = [
        'JBS_BBK_GENESIS'         => 101, 'JBS_BBK_EXODUS' => 102, 'JBS_BBK_LEVITICUS' => 103,
        'JBS_BBK_NUMBERS'         => 104, 'JBS_BBK_DEUTERONOMY' => 105, 'JBS_BBK_JOSHUA' => 106,
        'JBS_BBK_JUDGES'          => 107, 'JBS_BBK_RUTH' => 108, 'JBS_BBK_1SAMUEL' => 109,
        'JBS_BBK_2SAMUEL'         => 110, 'JBS_BBK_1KINGS' => 111, 'JBS_BBK_2KINGS' => 112,
        'JBS_BBK_1CHRONICLES'     => 113, 'JBS_BBK_2CHRONICLES' => 114, 'JBS_BBK_EZRA' => 115,
        'JBS_BBK_NEHEMIAH'        => 116, 'JBS_BBK_ESTHER' => 117, 'JBS_BBK_JOB' => 118,
        'JBS_BBK_PSALM'           => 119, 'JBS_BBK_PROVERBS' => 120, 'JBS_BBK_ECCLESIASTES' => 121,
        'JBS_BBK_SONG_OF_SOLOMON' => 122, 'JBS_BBK_ISAIAH' => 123, 'JBS_BBK_JEREMIAH' => 124,
        'JBS_BBK_LAMENTATIONS'    => 125, 'JBS_BBK_EZEKIEL' => 126, 'JBS_BBK_DANIEL' => 127,
        'JBS_BBK_HOSEA'           => 128, 'JBS_BBK_JOEL' => 129, 'JBS_BBK_AMOS' => 130,
        'JBS_BBK_OBADIAH'         => 131, 'JBS_BBK_JONAH' => 132, 'JBS_BBK_MICAH' => 133,
        'JBS_BBK_NAHUM'           => 134, 'JBS_BBK_HABAKKUK' => 135, 'JBS_BBK_ZEPHANIAH' => 136,
        'JBS_BBK_HAGGAI'          => 137, 'JBS_BBK_ZECHARIAH' => 138, 'JBS_BBK_MALACHI' => 139,
        'JBS_BBK_MATTHEW'         => 140, 'JBS_BBK_MARK' => 141, 'JBS_BBK_LUKE' => 142,
        'JBS_BBK_JOHN'            => 143, 'JBS_BBK_ACTS' => 144, 'JBS_BBK_ROMANS' => 145,
        'JBS_BBK_1CORINTHIANS'    => 146, 'JBS_BBK_2CORINTHIANS' => 147, 'JBS_BBK_GALATIANS' => 148,
        'JBS_BBK_EPHESIANS'       => 149, 'JBS_BBK_PHILIPPIANS' => 150, 'JBS_BBK_COLOSSIANS' => 151,
        'JBS_BBK_1THESSALONIANS'  => 152, 'JBS_BBK_2THESSALONIANS' => 153, 'JBS_BBK_1TIMOTHY' => 154,
        'JBS_BBK_2TIMOTHY'        => 155, 'JBS_BBK_TITUS' => 156, 'JBS_BBK_PHILEMON' => 157,
        'JBS_BBK_HEBREWS'         => 158, 'JBS_BBK_JAMES' => 159, 'JBS_BBK_1PETER' => 160,
        'JBS_BBK_2PETER'          => 161, 'JBS_BBK_1JOHN' => 162, 'JBS_BBK_2JOHN' => 163,
        'JBS_BBK_3JOHN'           => 164, 'JBS_BBK_JUDE' => 165, 'JBS_BBK_REVELATION' => 166,
        'JBS_BBK_TOBIT'           => 167, 'JBS_BBK_JUDITH' => 168, 'JBS_BBK_1MACCABEES' => 169,
        'JBS_BBK_2MACCABEES'      => 170, 'JBS_BBK_WISDOM' => 171, 'JBS_BBK_SIRACH' => 172,
        'JBS_BBK_BARUCH'          => 173,
    ];

    /**
     * Cache for translated book names to booknumber.
     *
     * @var array<string, int>|null
     * @since 1.0.0
     */
    private static ?array $translatedBookCache = null;

    /**
     * Parse a human-readable scripture reference into a ScriptureReference object.
     *
     * @param   string  $text  The reference text to parse
     *
     * @return  ScriptureReference|null  Parsed reference or null if unparsable
     *
     * @since  1.0.0
     */
    public static function parseReference(string $text): ?ScriptureReference
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (!preg_match(self::REFERENCE_REGEX, $text, $matches)) {
            return null;
        }

        $bookName     = trim($matches[1]);
        $chapterBegin = (int) $matches[2];
        $verseBegin   = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
        $chapterEnd   = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : $chapterBegin;
        $verseEnd     = isset($matches[5]) && $matches[5] !== '' ? (int) $matches[5] : $verseBegin;

        if ($verseEnd > 0 && (!isset($matches[4]) || $matches[4] === '')) {
            $chapterEnd = $chapterBegin;
        }

        if (!isset($matches[3]) || $matches[3] === '') {
            $verseBegin = 0;
            $verseEnd   = 0;
            $chapterEnd = $chapterBegin;
        }

        $booknumber = self::getBookNumber($bookName);

        if ($booknumber === 0) {
            return null;
        }

        return new ScriptureReference(
            booknumber:    $booknumber,
            chapterBegin:  $chapterBegin,
            verseBegin:    $verseBegin,
            chapterEnd:    $chapterEnd,
            verseEnd:      $verseEnd,
            referenceText: $text,
        );
    }

    /**
     * Format a scripture reference from structured data into a human-readable string.
     *
     * @param   int  $booknumber    Book number (101-173)
     * @param   int  $chapterBegin  Starting chapter
     * @param   int  $verseBegin    Starting verse (0 for chapter-only)
     * @param   int  $chapterEnd    Ending chapter
     * @param   int  $verseEnd      Ending verse (0 for chapter-only)
     *
     * @return  string  Formatted reference (e.g. "Luke 7:36-38")
     *
     * @since  1.0.0
     */
    public static function formatReference(
        int $booknumber,
        int $chapterBegin,
        int $verseBegin,
        int $chapterEnd,
        int $verseEnd
    ): string {
        $book = self::getBookName($booknumber);

        if ($book === '' || $chapterBegin === 0) {
            return '';
        }

        if ($verseBegin === 0) {
            return $chapterEnd > $chapterBegin
                ? $book . ' ' . $chapterBegin . '-' . $chapterEnd
                : $book . ' ' . $chapterBegin;
        }

        if (($verseEnd === 0 || $verseEnd === $verseBegin) && ($chapterEnd === 0 || $chapterEnd === $chapterBegin)) {
            return $book . ' ' . $chapterBegin . ':' . $verseBegin;
        }

        if ($chapterEnd === $chapterBegin || $chapterEnd === 0) {
            return $book . ' ' . $chapterBegin . ':' . $verseBegin . '-' . $verseEnd;
        }

        return $book . ' ' . $chapterBegin . ':' . $verseBegin . '-' . $chapterEnd . ':' . $verseEnd;
    }

    /**
     * Resolve a book name or abbreviation to its booknumber.
     *
     * @param   string  $name  Book name or abbreviation
     *
     * @return  int  Booknumber (101-173), or 0 if not found
     *
     * @since  1.0.0
     */
    public static function getBookNumber(string $name): int
    {
        $lower = strtolower(trim($name));

        if (isset(self::ABBREVIATIONS[$lower])) {
            return self::ABBREVIATIONS[$lower];
        }

        $translatedMap = self::getTranslatedBookMap();

        if (isset($translatedMap[$lower])) {
            return $translatedMap[$lower];
        }

        return 0;
    }

    /**
     * Get the translated book name for a booknumber.
     *
     * @param   int  $booknumber  Book number (101-173)
     *
     * @return  string  Translated book name, or empty string if not found
     *
     * @since  1.0.0
     */
    public static function getBookName(int $booknumber): string
    {
        $key = array_search($booknumber, self::BOOK_KEYS, true);

        if ($key === false) {
            return '';
        }

        return Text::_($key);
    }

    /**
     * Get all Bible books with their booknumber and translated names.
     *
     * @return  array  Array of ['booknumber' => int, 'name' => string, 'key' => string]
     *
     * @since  1.0.0
     */
    public static function getAllBooks(): array
    {
        $books = [];

        foreach (self::BOOK_KEYS as $key => $booknumber) {
            $books[] = [
                'booknumber' => $booknumber,
                'name'       => Text::_($key),
                'key'        => $key,
            ];
        }

        return $books;
    }

    /**
     * Get the ABBREVIATIONS constant for external use (e.g. regex building).
     *
     * @return  array<string, int>
     *
     * @since  1.0.0
     */
    public static function getAbbreviations(): array
    {
        return self::ABBREVIATIONS;
    }

    /**
     * Build the translated book name to booknumber lookup map.
     *
     * @return  array<string, int>
     *
     * @since  1.0.0
     */
    private static function getTranslatedBookMap(): array
    {
        if (self::$translatedBookCache !== null) {
            return self::$translatedBookCache;
        }

        self::$translatedBookCache = [];

        foreach (self::BOOK_KEYS as $key => $booknumber) {
            $translated                             = strtolower(Text::_($key));
            self::$translatedBookCache[$translated] = $booknumber;
        }

        return self::$translatedBookCache;
    }
}
