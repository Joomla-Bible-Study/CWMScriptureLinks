<?php

/**
 * Unit tests for BibleProviderFactory
 *
 * @package    CWM.Library.Scripture.Tests
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CWM\Library\Scripture\Tests\Bible;

use CWM\Library\Scripture\Bible\BibleProviderFactory;
use CWM\Library\Scripture\Bible\BibleProviderInterface;
use CWM\Library\Scripture\Bible\Provider\ApiBibleProvider;
use CWM\Library\Scripture\Bible\Provider\GetBibleProvider;
use CWM\Library\Scripture\Bible\Provider\LocalProvider;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

class BibleProviderFactoryTest extends TestCase
{
    public function testGetLocalProvider(): void
    {
        BibleProviderFactory::reset();
        $provider = BibleProviderFactory::getProvider('local');

        $this->assertInstanceOf(BibleProviderInterface::class, $provider);
        $this->assertInstanceOf(LocalProvider::class, $provider);
        $this->assertSame('local', $provider->getName());
        $this->assertTrue($provider->returnsText());
        $this->assertTrue($provider->isOfflineCapable());
    }

    public function testGetGetBibleProvider(): void
    {
        BibleProviderFactory::reset();
        $provider = BibleProviderFactory::getProvider('getbible');

        $this->assertInstanceOf(BibleProviderInterface::class, $provider);
        $this->assertInstanceOf(GetBibleProvider::class, $provider);
        $this->assertSame('getbible', $provider->getName());
        $this->assertTrue($provider->returnsText());
        $this->assertFalse($provider->isOfflineCapable());
    }

    public function testGetApiBibleProvider(): void
    {
        BibleProviderFactory::reset();
        $provider = BibleProviderFactory::getProvider('api_bible', 'test-key');

        $this->assertInstanceOf(BibleProviderInterface::class, $provider);
        $this->assertInstanceOf(ApiBibleProvider::class, $provider);
        $this->assertSame('api_bible', $provider->getName());
        $this->assertTrue($provider->returnsText());
        $this->assertFalse($provider->isOfflineCapable());
    }

    public function testGetUnknownProviderThrows(): void
    {
        BibleProviderFactory::reset();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Bible provider: fakeprovider');
        BibleProviderFactory::getProvider('fakeprovider');
    }

    public function testProviderInstanceCaching(): void
    {
        BibleProviderFactory::reset();
        $provider1 = BibleProviderFactory::getProvider('getbible');
        $provider2 = BibleProviderFactory::getProvider('getbible');

        $this->assertSame($provider1, $provider2);
    }

    public function testGetProviderNames(): void
    {
        $names = BibleProviderFactory::getProviderNames();

        $this->assertContains('local', $names);
        $this->assertContains('getbible', $names);
        $this->assertContains('api_bible', $names);
        $this->assertCount(3, $names);
    }

    public function testResetClearsCache(): void
    {
        $provider1 = BibleProviderFactory::getProvider('getbible');
        BibleProviderFactory::reset();
        $provider2 = BibleProviderFactory::getProvider('getbible');

        $this->assertNotSame($provider1, $provider2);
    }

    public function testGetProviderForTranslationLocalFallback(): void
    {
        BibleProviderFactory::reset();

        $params = new Registry([
            'provider_getbible' => 0,
        ]);

        $provider = BibleProviderFactory::getProviderForTranslation('kjv', $params);
        $this->assertInstanceOf(LocalProvider::class, $provider);
    }

    public function testGetProviderForTranslationGdprMode(): void
    {
        BibleProviderFactory::reset();

        $params = new Registry([
            'gdpr_mode'          => 1,
            'provider_getbible'  => 1,
            'provider_api_bible' => 1,
            'api_bible_api_key'  => 'test-key',
        ]);

        $provider = BibleProviderFactory::getProviderForTranslation('kjv', $params);
        $this->assertInstanceOf(LocalProvider::class, $provider);
    }
}
