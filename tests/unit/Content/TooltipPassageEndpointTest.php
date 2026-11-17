<?php

/**
 * The scripture tooltip's endpoint, ajaxGetPassage().
 *
 * The tooltip's URL named `task=getPassage`, which the dispatcher never read, so
 * every tooltip on a site without Proclaim answered "Unknown action" (#62).
 * These pin the endpoint's own contract:
 * - It answers site visitors, who carry no token.
 * - It always answers with JSON.
 * - It never hands a provider a reference it can't parse, or a version that
 *   doesn't look like a translation abbreviation or that the site doesn't
 *   offer.
 *
 * There is no database here, so a request that gets as far as a provider makes
 * the provider factory log a DB error. That log is how these tests tell "asked
 * a provider" from "rejected at the door". For the same reason the translation
 * catalog can't be read, so only the default version (kjv) is offered. GDPR
 * mode keeps the factory on the local provider, so no test reaches the network.
 *
 * @package    CWM.ScriptureLinks.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @since  1.2.20
 */

namespace CWM\ScriptureLinks\Tests\Content;

use CWM\Plugin\Content\ScriptureLinks\Extension\ScriptureLinks;
use Joomla\CMS\Log\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @since 1.2.20
 */
final class TooltipPassageEndpointTest extends TestCase
{
    /**
     * @return  void
     * @since 1.2.20
     */
    protected function setUp(): void
    {
        Log::$entries = [];
    }

    /**
     * Send ajaxGetPassage() a request and return the JSON it printed.
     *
     * The application stub's close() throws, standing in for the exit that ends
     * a real request, so a handler that forgot to close would fail here too.
     *
     * @param   array<string, string>  $query  The request's query values
     *
     * @return  array<string, mixed>  The decoded reply
     * @since 1.2.20
     */
    private static function request(array $query): array
    {
        $closed = new class ('closed') extends \RuntimeException {};

        $app = new class ($query, $closed) {
            public function __construct(private readonly array $query, private readonly \RuntimeException $closed)
            {
            }

            public function getInput(): object
            {
                return new class ($this->query) {
                    public function __construct(private readonly array $query)
                    {
                    }

                    public function getString(string $name, string $default = ''): string
                    {
                        return (string) ($this->query[$name] ?? $default);
                    }
                };
            }

            public function close($code = 0): void
            {
                throw $this->closed;
            }
        };

        $class  = new \ReflectionClass(ScriptureLinks::class);
        $plugin = $class->newInstance(null, ['params' => ['default_version' => 'kjv', 'gdpr_mode' => 1]]);

        ob_start();

        try {
            $class->getMethod('ajaxGetPassage')->invoke($plugin, $app);
            $output = (string) ob_get_clean();
            self::fail('ajaxGetPassage() must end the request with $app->close()');
        } catch (\RuntimeException $e) {
            $output = (string) ob_get_clean();
            self::assertSame($closed, $e, 'Only close() may end the request: ' . $e->getMessage());
        }

        $reply = json_decode($output, true);

        self::assertIsArray($reply, 'The reply must be JSON, or the tooltip reads it as a login wall: ' . $output);
        self::assertArrayHasKey('success', $reply);

        return $reply;
    }

    /**
     * @return  void
     * @since 1.2.20
     */
    #[TestDox('⚠️ A request with no token is answered, and reaches a provider')]
    public function testRequestWithoutATokenReachesAProvider(): void
    {
        // A token would break tooltips on cached pages and on pages left open
        // past the session lifetime, and the endpoint changes nothing.
        $reply = self::request(['reference' => 'John 3:16', 'version' => 'kjv']);

        self::assertNotEmpty(Log::$entries, 'A valid request must get as far as a provider');
        self::assertFalse($reply['success'], 'There is no database here, so no provider has text');
        self::assertSame('Passage unavailable', $reply['message']);
    }

    /**
     * @return  array<string, array{0: string}>
     * @since 1.2.20
     */
    public static function unparseableReferences(): array
    {
        return [
            'empty'               => [''],
            'not a reference'     => ['nonsense'],
            'trailing statement'  => ['John 3:16; DROP TABLE x'],
            'unknown book'        => ['Hezekiah 3:16'],
            'markup'              => ['<script>alert(1)</script>'],
            'over the length cap' => [str_repeat('John 3:16 ', 11)],
        ];
    }

    /**
     * @param   string  $reference  The reference sent
     *
     * @return  void
     * @since 1.2.20
     */
    #[DataProvider('unparseableReferences')]
    #[TestDox('A reference that does not parse never reaches a provider ($_dataName)')]
    public function testUnparseableReferenceNeverReachesAProvider(string $reference): void
    {
        $reply = self::request(['reference' => $reference, 'version' => 'kjv']);

        self::assertFalse($reply['success']);
        self::assertSame('Not a scripture reference', $reply['message']);
        self::assertSame([], Log::$entries, 'Nothing may have been asked of a provider');
    }

    /**
     * @return  array<string, array{0: string}>
     * @since 1.2.20
     */
    public static function badVersions(): array
    {
        return [
            'path'          => ['../kjv'],
            'nested path'   => ['kjv/../web'],
            'query'         => ['kjv?x=1'],
            'newline'       => ["kjv\nweb"],
            'quote'         => ["kjv'"],
            'markup'        => ['<b>'],
            'leading dot'   => ['.kjv'],
            'over 32 chars' => [str_repeat('a', 33)],
        ];
    }

    /**
     * @param   string  $version  The version sent
     *
     * @return  void
     * @since 1.2.20
     */
    #[DataProvider('badVersions')]
    #[TestDox('A version that is not an abbreviation never reaches a provider ($_dataName)')]
    public function testBadVersionNeverReachesAProvider(string $version): void
    {
        $reply = self::request(['reference' => 'John 3:16', 'version' => $version]);

        self::assertFalse($reply['success']);
        self::assertSame('Not a Bible version', $reply['message']);
        self::assertSame([], Log::$entries, 'Nothing may have been asked of a provider');
    }

    /**
     * @return  void
     * @since 1.2.20
     */
    #[TestDox('⚠️ A version the site does not offer never reaches a provider')]
    public function testVersionTheSiteDoesNotOfferNeverReachesAProvider(): void
    {
        // For a version the catalog doesn't know, the provider factory falls
        // back to GetBible: without the check, anyone could make the site fetch
        // and cache translations it never shows. The catalog can't be read
        // here, and the check fails closed.
        $reply = self::request(['reference' => 'John 3:16', 'version' => 'web']);

        self::assertFalse($reply['success']);
        self::assertSame('Bible version not offered here', $reply['message']);
        self::assertCount(1, Log::$entries, 'Only the catalog lookup may have run');
        self::assertStringContainsString('translation catalog', Log::$entries[0]['message']);
    }

    /**
     * Abbreviations as GetBible and API.Bible mint them. API.Bible's are only
     * lowercased and cut to 20 characters, so they can carry a space, a dot or
     * a letter outside ASCII.
     *
     * @return  array<string, array{0: string}>
     * @since 1.2.20
     */
    public static function realVersions(): array
    {
        return [
            'long name'   => ['basicenglish'],
            'with digits' => ['luther1912'],
            'mixed case'  => ['engKJV'],
            'hyphen'      => ['nvi-s'],
            'space'       => ['rvr 1960'],
            'dot'         => ['nbv.2007'],
            'cyrillic'    => ['синод'],
            'exactly 32'  => [str_repeat('a', 32)],
        ];
    }

    /**
     * @param   string  $version  The version sent
     *
     * @return  void
     * @since 1.2.20
     */
    #[DataProvider('realVersions')]
    #[TestDox('A real translation abbreviation looks like one, and goes on to the catalog ($_dataName)')]
    public function testRealVersionPassesThePatternToTheCatalog(string $version): void
    {
        $reply = self::request(['reference' => 'John 3:16', 'version' => $version]);

        self::assertSame('Bible version not offered here', $reply['message'], 'The pattern must have accepted it');
    }

    /**
     * @return  void
     * @since 1.2.20
     */
    #[TestDox('A request with no version is asked in the default version')]
    public function testMissingVersionFallsBackToTheDefault(): void
    {
        $reply = self::request(['reference' => 'Psalm 23']);

        self::assertSame('Passage unavailable', $reply['message']);
        self::assertNotEmpty(Log::$entries);
    }
}
