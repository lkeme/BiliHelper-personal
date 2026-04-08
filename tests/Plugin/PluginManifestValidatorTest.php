<?php declare(strict_types=1);

namespace Bhp\Tests\Plugin;

use Bhp\Plugin\PluginManifestValidator;
use PHPUnit\Framework\TestCase;

final class PluginManifestValidatorTest extends TestCase
{
    public function testAcceptsEmptyLinkMetadataDefaults(): void
    {
        $validator = new PluginManifestValidator();

        $error = $validator->validateManifest('DemoPlugin', $this->baseManifest([
            'activity_url' => '',
            'reference_links' => [],
        ]));

        self::assertNull($error);
    }

    public function testRejectsMissingActivityUrl(): void
    {
        $validator = new PluginManifestValidator();
        $manifest = $this->baseManifest([
            'reference_links' => [],
        ]);
        unset($manifest['activity_url']);

        $error = $validator->validateManifest('DemoPlugin', $manifest);

        self::assertSame('插件 DemoPlugin manifest 缺少关键字段 activity_url', $error);
    }

    public function testRejectsMissingReferenceLinks(): void
    {
        $validator = new PluginManifestValidator();
        $manifest = $this->baseManifest([
            'activity_url' => '',
        ]);
        unset($manifest['reference_links']);

        $error = $validator->validateManifest('DemoPlugin', $manifest);

        self::assertSame('插件 DemoPlugin manifest 缺少关键字段 reference_links', $error);
    }

    public function testRejectsNonStringActivityUrl(): void
    {
        $validator = new PluginManifestValidator();

        $error = $validator->validateManifest('DemoPlugin', $this->baseManifest([
            'activity_url' => ['bad'],
            'reference_links' => [],
        ]));

        self::assertSame('插件 DemoPlugin manifest.activity_url 必须为字符串', $error);
    }

    public function testRejectsNonArrayReferenceLinks(): void
    {
        $validator = new PluginManifestValidator();

        $error = $validator->validateManifest('DemoPlugin', $this->baseManifest([
            'activity_url' => '',
            'reference_links' => 'bad',
        ]));

        self::assertSame('插件 DemoPlugin manifest.reference_links 必须为数组', $error);
    }

    public function testRejectsReferenceLinkWithoutRequiredKeys(): void
    {
        $validator = new PluginManifestValidator();

        $error = $validator->validateManifest('DemoPlugin', $this->baseManifest([
            'activity_url' => '',
            'reference_links' => [
                ['url' => 'https://example.com'],
            ],
        ]));

        self::assertSame('插件 DemoPlugin manifest.reference_links[0] 缺少字段 comment', $error);
    }

    public function testRejectsReferenceLinkWithEmptyUrl(): void
    {
        $validator = new PluginManifestValidator();

        $error = $validator->validateManifest('DemoPlugin', $this->baseManifest([
            'activity_url' => '',
            'reference_links' => [
                ['url' => '', 'comment' => '备注'],
            ],
        ]));

        self::assertSame('插件 DemoPlugin manifest.reference_links[0].url 不能为空', $error);
    }

    public function testAcceptsReferenceLinkWithEmptyComment(): void
    {
        $validator = new PluginManifestValidator();

        $error = $validator->validateManifest('DemoPlugin', $this->baseManifest([
            'activity_url' => '',
            'reference_links' => [
                ['url' => 'https://example.com/*.html', 'comment' => ''],
            ],
        ]));

        self::assertNull($error);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function baseManifest(array $overrides = []): array
    {
        return array_merge([
            'hook' => 'DemoPlugin',
            'name' => 'DemoPlugin',
            'version' => '0.0.1',
            'desc' => '示例插件',
            'priority' => 2000,
            'cycle' => '5(分钟)',
            'valid_until' => '2099-12-31 23:59:59',
            'activity_url' => '',
            'reference_links' => [],
        ], $overrides);
    }
}
