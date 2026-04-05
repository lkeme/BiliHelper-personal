<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\Config\ConfigTemplateSynchronizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTemplateSynchronizerRegressionTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_reverse($this->tempDirectories) as $directory) {
            if (is_dir($directory)) {
                $this->deleteDirectory($directory);
            }
        }
    }

    public function testSynchronizerPreservesPolishMedalValuesWhenLegacyFileHasCommentSectionOnSameLine(): void
    {
        $root = $this->createTempDirectory();
        $templatePath = $root . DIRECTORY_SEPARATOR . 'template.ini';
        $targetPath = $root . DIRECTORY_SEPARATOR . 'user.ini';

        $template = implode("\n", [
            '[daily_gold]',
            'enable = true',
            '',
            '; 擦亮徽章',
            '[polish_medal]',
            'enable = false',
            'everyday = false',
            'cleanup_invalid_medal = false',
            'reply_words =',
            '',
        ]) . "\n";

        $legacyTarget = implode("\n", [
            '[daily_gold]',
            'enable = true',
            '',
            '; 擦亮徽章[polish_medal]',
            'enable = true',
            'everyday = true',
            'cleanup_invalid_medal = true',
            'reply_words = hello,world',
            '',
        ]) . "\n";

        self::assertNotFalse(file_put_contents($templatePath, $template));
        self::assertNotFalse(file_put_contents($targetPath, $legacyTarget));

        $result = (new ConfigTemplateSynchronizer())->synchronize($templatePath, $targetPath);

        self::assertTrue($result->changed);
        $rendered = file_get_contents($targetPath);
        self::assertIsString($rendered);
        self::assertStringContainsString("[polish_medal]\n", $rendered);
        self::assertStringContainsString("enable = true\n", $rendered);
        self::assertStringContainsString("everyday = true\n", $rendered);
        self::assertStringContainsString("cleanup_invalid_medal = true\n", $rendered);
        self::assertMatchesRegularExpression('/^reply_words\s*=\s*hello,world$/m', $rendered);
    }

    public function testExampleConfigKeepsPolishMedalSectionOnOwnLine(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'user.ini');

        self::assertIsString($contents);
        self::assertStringContainsString("\n[polish_medal]\n", str_replace("\r\n", "\n", $contents));
    }

    private function createTempDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bilihelper-config-sync-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0777, true) || is_dir($directory));
        $this->tempDirectories[] = $directory;

        return $directory;
    }

    private function deleteDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException('Failed to read temp directory for cleanup: ' . $directory);
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException('Failed to remove temp file: ' . $path);
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException('Failed to remove temp directory: ' . $directory);
        }
    }
}
