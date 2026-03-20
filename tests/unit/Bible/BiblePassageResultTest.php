<?php

/**
 * Unit tests for BiblePassageResult
 *
 * @package    CWM.Library.Scripture.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\Library\Scripture\Tests\Bible;

use CWM\Library\Scripture\Bible\BiblePassageResult;
use PHPUnit\Framework\TestCase;

class BiblePassageResultTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $result = new BiblePassageResult();

        $this->assertSame('', $result->text);
        $this->assertSame('', $result->reference);
        $this->assertSame('', $result->translation);
        $this->assertSame('', $result->copyright);
        $this->assertFalse($result->isHtml);
    }

    public function testConstructorWithParams(): void
    {
        $result = new BiblePassageResult(
            text: 'In the beginning...',
            reference: 'Genesis+1:1',
            translation: 'kjv',
            copyright: 'Public Domain',
            isHtml: true
        );

        $this->assertSame('In the beginning...', $result->text);
        $this->assertSame('Genesis+1:1', $result->reference);
        $this->assertSame('kjv', $result->translation);
        $this->assertSame('Public Domain', $result->copyright);
        $this->assertTrue($result->isHtml);
    }

    public function testHasTextReturnsTrueWithText(): void
    {
        $result = new BiblePassageResult(text: 'Some text');
        $this->assertTrue($result->hasText());
    }

    public function testHasTextReturnsFalseWithoutText(): void
    {
        $result = new BiblePassageResult();
        $this->assertFalse($result->hasText());
    }
}
