<?php declare(strict_types=1);

namespace Bhp\Tests\Plugin;

use Bhp\Plugin\CorePluginRegistry;
use Bhp\Plugin\PluginManifest;
use PHPUnit\Framework\TestCase;

final class PluginManifestTest extends TestCase
{
    public function testRoundTripsLinkMetadataFields(): void
    {
        $manifest = PluginManifest::fromArray([
            'hook' => 'DemoPlugin',
            'name' => 'DemoPlugin',
            'version' => '0.0.1',
            'desc' => '示例插件',
            'priority' => 2000,
            'cycle' => '5(分钟)',
            'valid_until' => '2099-12-31 23:59:59',
            'activity_url' => 'https://example.com/*.html',
            'reference_links' => [
                [
                    'url' => 'https://example.com/api',
                    'comment' => '接口',
                ],
            ],
        ]);

        self::assertSame('https://example.com/*.html', $manifest->activityUrl);
        self::assertSame([
            [
                'url' => 'https://example.com/api',
                'comment' => '接口',
            ],
        ], $manifest->referenceLinks);

        $serialized = $manifest->toArray();
        self::assertSame('https://example.com/*.html', $serialized['activity_url']);
        self::assertSame([
            [
                'url' => 'https://example.com/api',
                'comment' => '接口',
            ],
        ], $serialized['reference_links']);
    }

    public function testPreservesEmptyLinkMetadataDefaults(): void
    {
        $manifest = PluginManifest::fromArray([
            'hook' => 'DemoPlugin',
            'name' => 'DemoPlugin',
            'version' => '0.0.1',
            'desc' => '示例插件',
            'priority' => 2000,
            'cycle' => '5(分钟)',
            'valid_until' => '2099-12-31 23:59:59',
            'activity_url' => '',
            'reference_links' => [],
        ]);

        self::assertSame('', $manifest->activityUrl);
        self::assertSame([], $manifest->referenceLinks);
        self::assertTrue($manifest->activityUrlDeclared);
        self::assertTrue($manifest->referenceLinksDeclared);
    }

    public function testCoreLoginManifestIncludesEmptyLinkMetadataDefaults(): void
    {
        $registry = (new CorePluginRegistry())->all('D:/app');
        $manifest = $registry[0]['manifest'] ?? null;

        self::assertIsArray($manifest);
        self::assertArrayHasKey('activity_url', $manifest);
        self::assertArrayHasKey('reference_links', $manifest);
        self::assertSame('', $manifest['activity_url']);
        self::assertSame([], $manifest['reference_links']);
    }
}
