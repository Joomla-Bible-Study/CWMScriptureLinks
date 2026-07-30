<?php

/**
 * Guards the disarm detection in plg_system_cwmscripture.
 *
 * The plugin blanks lib_cwmscripture's legacy uninstall SQL before an
 * administrator runs a library-only update, because Joomla executes the
 * *installed* version's uninstall SQL during an update — and up to 1.1.4 that
 * meant DROP TABLE on every locally downloaded Bible.
 *
 * The subtle part is telling an executable statement from a mention inside a
 * comment: the already-disarmed 1.1.5 and 1.1.6 files describe the old
 * behaviour in prose and so contain the words "DROP TABLE" themselves. Getting
 * that wrong means either rewriting safe files or, far worse, leaving a live
 * one alone.
 *
 * @package    CWM.ScriptureLinks.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\ScriptureLinks\Tests\System;

use CWM\Plugin\System\Cwmscripture\Extension\Cwmscripture;
use PHPUnit\Framework\TestCase;

class CwmscriptureSystemPluginTest extends TestCase
{
    /**
     * The real v1.1.4 file — the one that destroyed people's translations.
     */
    private const ARMED_1_1_4 = <<<'SQL'
--
-- CWM Scripture Library - Uninstall SQL
-- Only runs when the library is uninstalled standalone (not locked by Proclaim).
--

DROP TABLE IF EXISTS `#__bsms_scripture_cache`;
DROP TABLE IF EXISTS `#__bsms_bible_verses`;
DROP TABLE IF EXISTS `#__bsms_bible_translations`;
SQL;

    /**
     * The v1.1.5 / v1.1.6 shape: statements removed, but the prose explaining
     * why still names them.
     */
    private const DISARMED_WITH_PROSE = <<<'SQL'
--
-- CWM Scripture Library - Uninstall SQL (intentionally empty)
--
-- This file used to DROP the three bible tables. It must never do that again:
-- Joomla's LibraryAdapter uninstalls the installed library before writing the
-- new one, so this file ran on every UPDATE.
--
-- Do not reintroduce DROP TABLE statements here.
--
SQL;

    public function testRealPreFixFileIsArmed(): void
    {
        $this->assertTrue(
            Cwmscripture::sqlIsArmed(self::ARMED_1_1_4),
            'The v1.1.4 file has three executable DROP TABLE statements and must be disarmed.'
        );
    }

    public function testDisarmedFileMentioningDropTableInCommentsIsNotArmed(): void
    {
        $this->assertFalse(
            Cwmscripture::sqlIsArmed(self::DISARMED_WITH_PROSE),
            'The 1.1.5/1.1.6 files describe DROP TABLE in prose. Treating that as armed '
            . 'would rewrite a file that is already safe.'
        );
    }

    public function testEmptyAndCommentOnlyFilesAreNotArmed(): void
    {
        $this->assertFalse(Cwmscripture::sqlIsArmed(''));
        $this->assertFalse(Cwmscripture::sqlIsArmed("-- nothing to see here\n"));
    }

    public function testUnrelatedStatementsAreNotArmed(): void
    {
        $this->assertFalse(
            Cwmscripture::sqlIsArmed("DELETE FROM `#__bsms_scripture_cache`;\n"),
            'Only DROP TABLE is the hazard — other statements must not trigger a rewrite.'
        );
    }

    public function testIndentedAndLowercaseStatementsAreArmed(): void
    {
        $this->assertTrue(
            Cwmscripture::sqlIsArmed("    drop table if exists `#__bsms_bible_verses`;\n"),
            'Detection must not depend on case or leading whitespace.'
        );
    }

    public function testStatementAfterCommentBlockIsArmed(): void
    {
        $sql = self::DISARMED_WITH_PROSE . "\nDROP TABLE IF EXISTS `#__bsms_bible_verses`;\n";

        $this->assertTrue(
            Cwmscripture::sqlIsArmed($sql),
            'A live statement appended below the reassuring comment block must still be caught.'
        );
    }

    public function testCarriageReturnLineEndingsAreHandled(): void
    {
        $sql = "--\r\n-- header\r\n--\r\nDROP TABLE IF EXISTS `#__bsms_bible_verses`;\r\n";

        $this->assertTrue(
            Cwmscripture::sqlIsArmed($sql),
            'A file saved with CRLF endings is just as dangerous.'
        );
    }
}
