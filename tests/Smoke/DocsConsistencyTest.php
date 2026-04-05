<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class DocsConsistencyTest extends TestCase
{
    /**
     * @var string[]
     */
    private const PRIMARY_DOCS = [
        'README.md',
        'docs/ARCHITECTURE.md',
        'docs/DOC.md',
        'docs/MIGRATION.md',
    ];

    public function testPrimaryRuntimeDocsDoNotMentionRemovedConsoleModes(): void
    {
        foreach (['README.md', 'docs/ARCHITECTURE.md', 'docs/DOC.md'] as $path) {
            $contents = $this->readDoc($path);

            self::assertStringNotContainsString('mode:doctor', $contents, $path);
            self::assertStringNotContainsString('mode:profiles', $contents, $path);
            self::assertStringNotContainsString('mode:restore', $contents, $path);
        }
    }

    public function testMigrationDocTreatsRemovedModesAsHistoricalOnly(): void
    {
        $contents = $this->readDoc('docs/MIGRATION.md');

        self::assertStringContainsString('已不再提供', $contents);
        self::assertStringNotContainsString('m:o', $contents);
        self::assertStringNotContainsString('m:p', $contents);
        self::assertStringNotContainsString('m:r', $contents);
        self::assertStringNotContainsString('--profiles', $contents);
    }

    public function testDocsReferenceCurrentRuntimeAnchors(): void
    {
        $readme = $this->readDoc('README.md');
        $architecture = $this->readDoc('docs/ARCHITECTURE.md');
        $usage = $this->readDoc('docs/DOC.md');
        $migration = $this->readDoc('docs/MIGRATION.md');

        self::assertStringContainsString('AppKernel', $architecture);
        self::assertStringContainsString('ServiceContainer', $architecture);
        self::assertStringContainsString('ExternalPluginRegistry', $architecture . $migration);
        self::assertStringContainsString('plugins/*', $readme . $architecture . $migration);
        self::assertStringContainsString('ActivityFlowStore', $architecture . $migration);
        self::assertStringContainsString('cache.sqlite3', $architecture . $migration);

        foreach (['mode:app', 'mode:debug', 'mode:script', '--reset-cache', '--purge-auth'] as $token) {
            self::assertStringContainsString($token, $readme . $usage . $migration, $token);
        }

        self::assertStringContainsString('docker compose pull', $readme . $usage);
        self::assertStringContainsString('docker compose up -d', $readme . $usage);
    }

    public function testArchitectureDocDoesNotDescribeLegacyPluginDirectoryFallback(): void
    {
        $architecture = $this->readDoc('docs/ARCHITECTURE.md');

        self::assertStringNotContainsString('plugin/*', $architecture);
        self::assertStringNotContainsString('现有入口文件', $architecture);
    }

    private function readDoc(string $path): string
    {
        $root = dirname(__DIR__, 2);
        $contents = file_get_contents($root . DIRECTORY_SEPARATOR . $path);

        self::assertIsString($contents, 'Failed to read doc: ' . $path);

        return $contents;
    }
}
