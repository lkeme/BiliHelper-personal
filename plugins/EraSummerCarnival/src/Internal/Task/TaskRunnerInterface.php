<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

interface TaskRunnerInterface
{
    /**
     * 标识，用于日志与编排
     */
    public function key(): string;

    /**
     * 执行一步
     */
    public function run(CarnivalContext $context): CarnivalStepResult;
}
