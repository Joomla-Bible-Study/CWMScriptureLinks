<?php

/**
 * Unit tests for ScriptureHelper
 *
 * @package    CWM.Library.Scripture.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\Library\Scripture\Tests\Helper;

use CWM\Library\Scripture\Helper\ScriptureHelper;
use CWM\Library\Scripture\Helper\ScriptureReference;
use PHPUnit\Framework\TestCase;

class ScriptureHelperTest extends TestCase
{
    public function testParseSimpleReference(): void
    {
        $ref = ScriptureHelper::parseReference('Genesis 1:1');

        $this->assertNotNull($ref);
        $this->assertSame(101, $ref->booknumber);
        $this->assertSame(1, $ref->chapterBegin);
        $this->assertSame(1, $ref->verseBegin);
        $this->assertSame(1, $ref->chapterEnd);
        $this->assertSame(1, $ref->verseEnd);
    }

    public function testParseVerseRange(): void
    {
        $ref = ScriptureHelper::parseReference('Luke 7:36-38');

        $this->assertNotNull($ref);
        $this->assertSame(142, $ref->booknumber);
        $this->assertSame(7, $ref->chapterBegin);
        $this->assertSame(36, $ref->verseBegin);
        $this->assertSame(7, $ref->chapterEnd);
        $this->assertSame(38, $ref->verseEnd);
    }

    public function testParseCrossChapterRange(): void
    {
        $ref = ScriptureHelper::parseReference('Luke 1:20-2:5');

        $this->assertNotNull($ref);
        $this->assertSame(142, $ref->booknumber);
        $this->assertSame(1, $ref->chapterBegin);
        $this->assertSame(20, $ref->verseBegin);
        $this->assertSame(2, $ref->chapterEnd);
        $this->assertSame(5, $ref->verseEnd);
    }

    public function testParseChapterOnly(): void
    {
        $ref = ScriptureHelper::parseReference('Psalm 23');

        $this->assertNotNull($ref);
        $this->assertSame(119, $ref->booknumber);
        $this->assertSame(23, $ref->chapterBegin);
        $this->assertSame(0, $ref->verseBegin);
        $this->assertSame(23, $ref->chapterEnd);
        $this->assertSame(0, $ref->verseEnd);
    }

    public function testParseAbbreviation(): void
    {
        $ref = ScriptureHelper::parseReference('Gen 1:1');
        $this->assertNotNull($ref);
        $this->assertSame(101, $ref->booknumber);
    }

    public function testParseNumberedBook(): void
    {
        $ref = ScriptureHelper::parseReference('1 Cor 13:4-7');

        $this->assertNotNull($ref);
        $this->assertSame(146, $ref->booknumber);
        $this->assertSame(13, $ref->chapterBegin);
        $this->assertSame(4, $ref->verseBegin);
        $this->assertSame(13, $ref->chapterEnd);
        $this->assertSame(7, $ref->verseEnd);
    }

    public function testParseLongCrossChapter(): void
    {
        $ref = ScriptureHelper::parseReference('Rev 21:1-22:5');

        $this->assertNotNull($ref);
        $this->assertSame(166, $ref->booknumber);
        $this->assertSame(21, $ref->chapterBegin);
        $this->assertSame(1, $ref->verseBegin);
        $this->assertSame(22, $ref->chapterEnd);
        $this->assertSame(5, $ref->verseEnd);
    }

    public function testParseInvalidInput(): void
    {
        $this->assertNull(ScriptureHelper::parseReference('NotABook 1:1'));
        $this->assertNull(ScriptureHelper::parseReference(''));
        $this->assertNull(ScriptureHelper::parseReference('just some text'));
    }

    public function testFormatSingleVerse(): void
    {
        // In test context, Text::_() returns the language key as-is
        $this->assertSame('JBS_BBK_GENESIS 1:1', ScriptureHelper::formatReference(101, 1, 1, 1, 1));
    }

    public function testFormatVerseRange(): void
    {
        $this->assertSame('JBS_BBK_LUKE 7:36-38', ScriptureHelper::formatReference(142, 7, 36, 7, 38));
    }

    public function testFormatCrossChapterRange(): void
    {
        $this->assertSame('JBS_BBK_LUKE 1:20-2:5', ScriptureHelper::formatReference(142, 1, 20, 2, 5));
    }

    public function testFormatChapterOnly(): void
    {
        $this->assertSame('JBS_BBK_PSALM 23', ScriptureHelper::formatReference(119, 23, 0, 23, 0));
    }

    public function testRoundTrip(): void
    {
        $inputs = [
            'Genesis 1:1',
            'Luke 7:36-38',
            'Luke 1:20-2:5',
            'Psalm 23',
            'Revelation 21:1-22:5',
        ];

        foreach ($inputs as $input) {
            $ref = ScriptureHelper::parseReference($input);
            $this->assertNotNull($ref, 'Failed to parse: ' . $input);

            $formatted = ScriptureHelper::formatReference(
                $ref->booknumber,
                $ref->chapterBegin,
                $ref->verseBegin,
                $ref->chapterEnd,
                $ref->verseEnd
            );

            $ref2 = ScriptureHelper::parseReference($formatted);
            $this->assertNotNull($ref2, 'Failed to re-parse formatted: ' . $formatted);
            $this->assertSame($ref->booknumber, $ref2->booknumber, 'Booknumber mismatch for: ' . $input);
            $this->assertSame($ref->chapterBegin, $ref2->chapterBegin, 'ChapterBegin mismatch for: ' . $input);
            $this->assertSame($ref->verseBegin, $ref2->verseBegin, 'VerseBegin mismatch for: ' . $input);
        }
    }

    public function testGetBookNumber(): void
    {
        $this->assertSame(101, ScriptureHelper::getBookNumber('Gen'));
        $this->assertSame(101, ScriptureHelper::getBookNumber('genesis'));
        $this->assertSame(142, ScriptureHelper::getBookNumber('Luke'));
        $this->assertSame(142, ScriptureHelper::getBookNumber('lk'));
        $this->assertSame(146, ScriptureHelper::getBookNumber('1 Cor'));
        $this->assertSame(166, ScriptureHelper::getBookNumber('Rev'));
        $this->assertSame(0, ScriptureHelper::getBookNumber('NotABook'));
    }

    public function testGetBookName(): void
    {
        $name = ScriptureHelper::getBookName(101);
        $this->assertNotEmpty($name);
        $this->assertSame('', ScriptureHelper::getBookName(0));
        $this->assertSame('', ScriptureHelper::getBookName(999));
    }

    public function testGetAllBooks(): void
    {
        $books = ScriptureHelper::getAllBooks();
        $this->assertCount(73, $books);

        $first = $books[0];
        $this->assertArrayHasKey('booknumber', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('key', $first);
        $this->assertSame(101, $first['booknumber']);
    }

    public function testScriptureReferenceToArray(): void
    {
        $ref = new ScriptureReference(
            booknumber: 142,
            chapterBegin: 7,
            verseBegin: 36,
            chapterEnd: 7,
            verseEnd: 38,
            bibleVersion: 'kjv',
            referenceText: 'Luke 7:36-38',
            ordering: 0,
        );

        $array = $ref->toArray();
        $this->assertSame(142, $array['booknumber']);
        $this->assertSame(7, $array['chapter_begin']);
        $this->assertSame(36, $array['verse_begin']);
        $this->assertSame('kjv', $array['bible_version']);
        $this->assertSame('Luke 7:36-38', $array['reference_text']);
    }

    public function testScriptureReferenceFromRow(): void
    {
        $row = (object) [
            'booknumber'     => 101,
            'chapter_begin'  => 1,
            'verse_begin'    => 1,
            'chapter_end'    => 1,
            'verse_end'      => 1,
            'bible_version'  => 'esv',
            'reference_text' => 'Genesis 1:1',
            'ordering'       => 0,
        ];

        $ref = ScriptureReference::fromRow($row);
        $this->assertSame(101, $ref->booknumber);
        $this->assertSame('esv', $ref->bibleVersion);
        $this->assertSame('Genesis 1:1', $ref->referenceText);
    }

    public function testParseVariousAbbreviations(): void
    {
        $ref = ScriptureHelper::parseReference('Matt 5:3-12');
        $this->assertNotNull($ref);
        $this->assertSame(140, $ref->booknumber);

        $ref = ScriptureHelper::parseReference('Mk 1:1');
        $this->assertNotNull($ref);
        $this->assertSame(141, $ref->booknumber);

        $ref = ScriptureHelper::parseReference('2 Tim 3:16-17');
        $this->assertNotNull($ref);
        $this->assertSame(155, $ref->booknumber);

        $ref = ScriptureHelper::parseReference('Phil 4:13');
        $this->assertNotNull($ref);
        $this->assertSame(150, $ref->booknumber);
    }

    public function testGetAbbreviations(): void
    {
        $abbrs = ScriptureHelper::getAbbreviations();
        $this->assertIsArray($abbrs);
        $this->assertArrayHasKey('gen', $abbrs);
        $this->assertArrayHasKey('rev', $abbrs);
        $this->assertSame(101, $abbrs['gen']);
        $this->assertSame(166, $abbrs['rev']);
    }
}
