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

    /**
     * Both headless installer events must be subscribed.
     *
     * ⚠️ onExtensionBeforeUpdate is the one that matters and the one easiest to
     * leave out. The Update Manager calls Installer::update(), which dispatches
     * that event *only*; InstallerAdapter::update() then calls install(), where
     * checkExtensionInFilesystem() uninstalls the old library and runs its
     * armed SQL. A fix subscribing to onExtensionBeforeInstall alone passes
     * every other test here and covers none of Proclaim#1864.
     */
    public function testHeadlessInstallerEventsAreSubscribed(): void
    {
        $events = Cwmscripture::getSubscribedEvents();

        self::assertArrayHasKey(
            'onExtensionBeforeUpdate',
            $events,
            'The Update Manager and every headless update runner dispatch this one.'
        );
        self::assertArrayHasKey(
            'onExtensionBeforeInstall',
            $events,
            'A manual zip install over an existing library takes this one.'
        );

        self::assertSame(
            $events['onExtensionBeforeUpdate'],
            $events['onExtensionBeforeInstall'],
            'Both should reach the same ungated sweep.'
        );

        self::assertArrayHasKey(
            'onAfterRoute',
            $events,
            'The interactive sweep is additive, not replaced: it also covers routes '
            . 'that never reach Installer::install().'
        );
    }

    /**
     * The sweep must work with no application, session or input.
     *
     * This is what makes the headless subscription meaningful. If the sweep
     * reached for application state it would subscribe fine and then fatal
     * under CLI, which is the environment it exists for.
     *
     * The fixture is planted at the path the sweep actually reads --
     * JPATH_LIBRARIES, fixed by tests/bootstrap.php -- rather than at a
     * temporary directory the sweep would never look in. An earlier version of
     * this test used a temp path, watched the sweep return early because no
     * file was there, and reported a pass for the file it had not touched.
     */
    public function testSweepDisarmsAnArmedFileWithNoApplicationContext(): void
    {
        $dir  = JPATH_LIBRARIES . '/cwmscripture/sql';
        $file = $dir . '/uninstall.mysql.utf8.sql';

        // Never overwrite a real file: this repository does not ship one at
        // that path, and if that ever changes the test should say so rather
        // than quietly rewrite it.
        self::assertFileDoesNotExist(
            $file,
            'This test plants its own fixture and would otherwise overwrite a real file.'
        );

        if (!mkdir($dir, 0o777, true) && !is_dir($dir)) {
            self::fail('Could not create the fixture directory.');
        }

        try {
            file_put_contents($file, self::ARMED_1_1_4);

            self::assertTrue(
                Cwmscripture::sqlIsArmed((string) file_get_contents($file)),
                'The fixture must start armed, or this proves nothing.'
            );

            // No application, no session, no input — only the constant.
            $plugin = (new \ReflectionClass(Cwmscripture::class))->newInstanceWithoutConstructor();
            $plugin->onExtensionBeforeInstaller();

            $after = (string) file_get_contents($file);

            self::assertFalse(
                Cwmscripture::sqlIsArmed($after),
                'The armed file must be disarmed by the headless handler.'
            );
            self::assertStringNotContainsString(
                'DROP TABLE IF EXISTS `#__bsms_bible_verses`;',
                $after,
                'The executable statement must be gone, not merely commented around.'
            );
        } finally {
            @unlink($file);
            @rmdir($dir);
            @rmdir(\dirname($dir));
        }
    }
}
