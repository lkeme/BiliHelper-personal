<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class DockerEntrypointImmutabilityTest extends TestCase
{
    public function testEntrypointDoesNotPerformRuntimeGitSync(): void
    {
        $entrypoint = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'entrypoint.sh';
        $contents = file_get_contents($entrypoint);

        self::assertIsString($contents);
        self::assertStringNotContainsString('git fetch', $contents);
        self::assertStringNotContainsString('git checkout -B', $contents);
        self::assertStringNotContainsString('git reset --hard', $contents);
    }

    public function testDockerfileCopiesFlattenedPluginsDirectory(): void
    {
        $dockerfile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile';
        $contents = file_get_contents($dockerfile);

        self::assertIsString($contents);
        self::assertStringContainsString('COPY plugins /app/plugins', $contents);
        self::assertStringNotContainsString('COPY plugin /app/plugin', $contents);
    }

    public function testLegacyLocalDockerDevelopmentFilesAreRemoved(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . 'docker-compose.local.yml');
        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile.local');
    }

    public function testPrimaryDocsDoNotMentionRemovedLocalDockerWorkflow(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'README.md',
            'docs/ARCHITECTURE.md',
            'docs/DOC.md',
            'docs/MIGRATION.md',
        ] as $relativePath) {
            $contents = file_get_contents($root . DIRECTORY_SEPARATOR . $relativePath);
            self::assertIsString($contents, 'Failed to read ' . $relativePath);
            self::assertStringNotContainsString('docker-compose.local.yml', $contents, $relativePath);
            self::assertStringNotContainsString('Dockerfile.local', $contents, $relativePath);
        }
    }
}
