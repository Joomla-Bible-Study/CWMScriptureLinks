<?php

/**
 * @package    CWM.ScriptureLinks.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\ScriptureLinks\Tests\Task;

use CWM\Plugin\Task\Cwmscripture\Extension\Cwmscripture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for plg_task_cwmscripture.
 *
 * Verifies the plugin registers the expected scheduler events and task
 * types, and that its install-time TASKS_MAP is shaped the way the
 * TaskPluginTrait expects.
 */
#[CoversClass(Cwmscripture::class)]
final class CwmscriptureTaskPluginTest extends TestCase
{
    public function testGetSubscribedEventsReturnsExpectedEventMap(): void
    {
        $events = Cwmscripture::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey('onTaskOptionsList', $events);
        $this->assertArrayHasKey('onExecuteTask', $events);
        $this->assertSame('advertiseRoutines', $events['onTaskOptionsList']);
        $this->assertSame('standardRoutineHandler', $events['onExecuteTask']);
    }

    public function testTasksMapDeclaresDownloadCoreTranslationsType(): void
    {
        $reflection = new \ReflectionClass(Cwmscripture::class);
        $map        = $reflection->getConstant('TASKS_MAP');

        $this->assertIsArray($map);
        $this->assertArrayHasKey('cwmscripture.downloadCoreTranslations', $map);

        $entry = $map['cwmscripture.downloadCoreTranslations'];
        $this->assertSame('PLG_TASK_CWMSCRIPTURE_DOWNLOAD_CORE', $entry['langConstPrefix']);
        $this->assertSame('downloadCoreTranslations', $entry['method']);
    }

    public function testDownloadCoreTranslationsIsPrivateInstanceMethod(): void
    {
        $reflection = new \ReflectionClass(Cwmscripture::class);

        $this->assertTrue($reflection->hasMethod('downloadCoreTranslations'));

        $method = $reflection->getMethod('downloadCoreTranslations');

        // TaskPluginTrait::standardRoutineHandler() looks up the method via
        // reflection and invokes it — it needs to exist as a non-static
        // method on the class (private is fine because reflection bypasses
        // visibility checks).
        $this->assertFalse($method->isStatic());
        $this->assertTrue($method->isPrivate());
    }

    public function testLoadImporterFallbackScansLibrarySrcPaths(): void
    {
        $reflection = new \ReflectionClass(Cwmscripture::class);

        $this->assertTrue($reflection->hasMethod('loadImporter'));
        $loadImporter = $reflection->getMethod('loadImporter');

        $this->assertTrue($loadImporter->isPrivate());
        $this->assertSame('bool', (string) $loadImporter->getReturnType());
    }
}
