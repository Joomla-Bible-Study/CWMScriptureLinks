<?php

/**
 * @package    CWM.ScriptureLinks.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\ScriptureLinks\Tests\Content;

use CWM\Plugin\Content\ScriptureLinks\Extension\ScriptureLinks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for plg_content_scripturelinks.
 *
 * Runs against the pure PHP pieces of the plugin that don't require a
 * live database or HTTP fetch — the event registration, the dispatch
 * map, and the tag-attribute parser.
 */
#[CoversClass(ScriptureLinks::class)]
final class ScriptureLinksTest extends TestCase
{
    public function testGetSubscribedEventsRegistersContentAndAjaxHandlers(): void
    {
        $events = ScriptureLinks::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey('onContentPrepare', $events);
        $this->assertArrayHasKey('onAjaxScripturelinks', $events);
        $this->assertSame('onContentPrepare', $events['onContentPrepare']);
        $this->assertSame('onAjaxScripturelinks', $events['onAjaxScripturelinks']);
    }

    public function testParseTagAttributesExtractsQuotedValues(): void
    {
        $method = $this->privateMethod('parseTagAttributes');
        $plugin = $this->stubPlugin();

        $result = $method->invoke($plugin, 'version="kjv" display="inline"');

        $this->assertSame(['version' => 'kjv', 'display' => 'inline'], $result);
    }

    public function testParseTagAttributesLowercasesKeys(): void
    {
        $method = $this->privateMethod('parseTagAttributes');
        $plugin = $this->stubPlugin();

        $result = $method->invoke($plugin, 'VERSION="web" Display="popup"');

        $this->assertSame(['version' => 'web', 'display' => 'popup'], $result);
    }

    public function testParseTagAttributesReturnsEmptyArrayForNoMatches(): void
    {
        $method = $this->privateMethod('parseTagAttributes');
        $plugin = $this->stubPlugin();

        $this->assertSame([], $method->invoke($plugin, ''));
        $this->assertSame([], $method->invoke($plugin, 'malformed attrs without quotes'));
    }

    public function testParseTagAttributesIgnoresUnquotedValues(): void
    {
        $method = $this->privateMethod('parseTagAttributes');
        $plugin = $this->stubPlugin();

        // Only the quoted attribute survives — unquoted values are rejected
        // by the regex and simply dropped.
        $result = $method->invoke($plugin, 'version=kjv display="inline"');

        $this->assertSame(['display' => 'inline'], $result);
    }

    private function privateMethod(string $name): \ReflectionMethod
    {
        $reflection = new \ReflectionClass(ScriptureLinks::class);
        $method     = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    private function stubPlugin(): ScriptureLinks
    {
        // ReflectionMethod::invoke() on a private method needs a real
        // instance as the bound object.  ScriptureLinks extends CMSPlugin
        // whose stub constructor (in tests/bootstrap.php) accepts an
        // optional $subject + $config array.
        return (new \ReflectionClass(ScriptureLinks::class))
            ->newInstance(null, []);
    }
}
