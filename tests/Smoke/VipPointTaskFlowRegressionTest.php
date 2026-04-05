<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class VipPointTaskFlowRegressionTest extends TestCase
{
    public function testVipPointPassiveTasksUseCompleteEndpointInsteadOfReceiveEndpoint(): void
    {
        $commonTaskInfo = $this->readSource('plugins/VipPoint/src/Traits/CommonTaskInfo.php');

        self::assertStringContainsString('if ($item[\'state\'] == 0 && $c !== 4)', $commonTaskInfo);
        self::assertStringContainsString('4 => $this->complete($item[\'task_code\'], $name),', $commonTaskInfo);
        self::assertStringNotContainsString('4 => $this->completeReceive(),', $commonTaskInfo);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
