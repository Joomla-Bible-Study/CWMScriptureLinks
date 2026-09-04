<?php

/**
 * Every AJAX action this plugin advertises must resolve to a method that exists.
 *
 * The dispatcher looks its target up in a string map and calls it dynamically,
 * so a typo or a renamed handler is not a compile error — it is an Error at
 * click time. Two actions forwarded to BibleImporter methods that were never
 * written (CWMScriptureLinks#46): "Remove all translations" and "Clean up
 * provider" both answered a bodyless 500 and the admin UI simply hung, because
 * the handlers caught \Exception and an Error is not one.
 *
 * @package    CWM.ScriptureLinks.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @since  __DEPLOY_VERSION__
 */

namespace CWM\ScriptureLinks\Tests\Content;

use CWM\Plugin\Content\ScriptureLinks\Extension\ScriptureLinks;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @since __DEPLOY_VERSION__
 */
final class AjaxDispatcherContractTest extends TestCase
{
    /**
     * Read the dispatcher map out of the source.
     *
     * It is a local variable inside the AJAX entry point, so it cannot be
     * reached by reflection; parsing the array literal is what keeps this test
     * honest about the map the running code actually uses.
     *
     * @return  array<string, string>  action => handler method
     * @since __DEPLOY_VERSION__
     */
    private static function dispatchers(): array
    {
        $source = file_get_contents(\dirname(__DIR__, 3) . '/src/Extension/ScriptureLinks.php');

        self::assertIsString($source, 'The plugin source must be readable');

        $start = strpos($source, '$dispatchers = [');
        self::assertNotFalse($start, 'The dispatcher map must still exist');

        $end = strpos($source, '];', $start);
        self::assertNotFalse($end, 'The dispatcher map must be a closed array literal');

        preg_match_all(
            "/'([a-zA-Z]+)'\s*=>\s*'([a-zA-Z]+)'/",
            substr($source, $start, $end - $start),
            $matches,
            \PREG_SET_ORDER
        );

        $map = [];

        foreach ($matches as $match) {
            $map[$match[1]] = $match[2];
        }

        return $map;
    }

    /**
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[TestDox('⚠️ Every advertised AJAX action resolves to a handler that exists')]
    public function testEveryDispatcherTargetExists(): void
    {
        $missing = [];

        foreach (self::dispatchers() as $action => $handler) {
            if (!method_exists(ScriptureLinks::class, $handler)) {
                $missing[] = $action . ' => ' . $handler . '()';
            }
        }

        self::assertSame(
            [],
            $missing,
            "The dispatcher calls its target dynamically, so a missing handler is not a compile error.\n"
            . 'It is an Error at click time, and the browser gets a 500 with no JSON body.'
        );
    }

    /**
     * A scan that finds nothing proves nothing.
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[TestDox('The dispatcher map was actually read, and covers the two actions from #46')]
    public function testScanIsNotVacuous(): void
    {
        $map = self::dispatchers();

        self::assertGreaterThanOrEqual(8, \count($map), 'The dispatcher map should have been parsed');
        self::assertArrayHasKey('removeAllTranslations', $map);
        self::assertArrayHasKey('cleanupProvider', $map);
    }

    /**
     * The handlers answer with JSON, so they must catch \Throwable — an Error
     * from a missing or renamed library method is not an \Exception, and a
     * handler that only catches \Exception lets it escape as a bodyless 500.
     *
     * @return  void
     * @since __DEPLOY_VERSION__
     */
    #[TestDox('AJAX handlers catch Throwable, so a fatal becomes a JSON error')]
    public function testAjaxHandlersCatchThrowable(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 3) . '/src/Extension/ScriptureLinks.php');

        self::assertIsString($source);

        foreach (self::dispatchers() as $action => $handler) {
            $start = strpos($source, 'function ' . $handler . '(');

            if ($start === false) {
                continue;
            }

            $body = substr($source, $start, 4000);

            // Only assert on handlers that actually catch something; a handler
            // with no try/catch is a separate concern from catching too narrowly.
            if (!str_contains($body, 'catch (')) {
                continue;
            }

            self::assertStringNotContainsString(
                'catch (\Exception $e)',
                $body,
                $handler . '() answers with JSON, so it must catch \\Throwable: an Error from a missing '
                . 'library method is not an \\Exception and would escape as a 500 with no body.'
            );
        }
    }
}
