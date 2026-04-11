<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;


trait CommonTaskInfo
{
    protected const VIEW_DELAY_TASK_KEY = 'DelayedAction';

    protected function isComplete(array $data, string $name, mixed $title, mixed $code): bool
    {
        $item = $this->getTaskInfo($data, (string)$title, (string)$code);
        if ($item === []) {
            $this->warning("大会员积分@{$name}: 任务异常，暂定完成，通知开发者修复");

            return true;
        }

        if ($item['state'] == 3) {
            $this->notice("大会员积分@{$name}: {$item['task_code']} 任务已经完成");

            return true;
        }

        if (in_array($item['state'], [0, 1], true)) {
            return false;
        }

        $this->warning("大会员积分@{$name}: {$item['task_code']} 任务出现额外情况，暂定完成，通知开发者修复");

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTaskInfo(array $data, string $title, string $code): array
    {
        $modules = $data['data']['task_info']['modules'] ?? [];
        if ($modules === []) {
            return [];
        }

        foreach ($modules as $module) {
            if (($module['module_title'] ?? null) != $title) {
                continue;
            }

            foreach ($module['common_task_item'] as $item) {
                if (($item['task_code'] ?? null) != $code) {
                    continue;
                }

                return $item;
            }
        }

        return [];
    }

    protected function worker(array $data, string $name, string $title, string $code, int $c, string $arg = ''): bool
    {
        $item = $this->getTaskInfo($data, $title, $code);

        if ($item['state'] == 0 && $c !== 4) {
            $response = $this->vipPointScoreTaskApi()->receive($item['task_code']);
            $this->assertVipPointAuthFailure($response, "大会员积分@{$name}: {$item['task_code']} 任务领取时账号未登录");
            if ($response['code']) {
                $this->warning("大会员积分@{$name}: {$item['task_code']} 任务领取失败 " . json_encode($response));

                return false;
            }
            $this->notice("大会员积分@{$name}: {$item['task_code']} 任务领取成功");
        }

        return match ($c) {
            1 => $this->complete($item['task_code'], $name),
            2 => $this->completeView($item['task_code'], $name, $arg),
            3 => $this->completeVipMallView($item['task_code'], $name),
            4 => $this->complete($item['task_code'], $name),
            default => false,
        };
    }

    protected function complete(string $task_code, string $name): bool
    {
        $response = $this->vipPointScoreTaskApi()->complete($task_code);
        $this->assertVipPointAuthFailure($response, "大会员积分@{$name}: {$task_code} 任务执行时账号未登录");
        if ($response['code']) {
            $this->warning("大会员积分@{$name}: {$task_code} 任务执行失败 " . json_encode($response));

            return false;
        }
        $this->notice("大会员积分@{$name}: {$task_code} 任务执行成功");

        return true;
    }

    protected function completeView(string $task_code, string $name, string $channel): bool
    {
        $action = $this->getTask(self::VIEW_DELAY_TASK_KEY);
        if (is_array($action) && ($action['task_code'] ?? null) === $task_code && ($action['type'] ?? null) === 'deliver') {
            $remaining = max(0, (int)(($action['execute_at'] ?? time()) - time()));
            if ($remaining > 0) {
                $this->scheduleAfter((float)$remaining);

                return false;
            }

            $channel = (string)($action['context']['channel'] ?? $channel);
            $this->setTask(self::VIEW_DELAY_TASK_KEY, null);
        } else {
            $delaySeconds = mt_rand(10, 20);
            $this->setTask(self::VIEW_DELAY_TASK_KEY, [
                'task_code' => $task_code,
                'type' => 'deliver',
                'context' => [
                    'channel' => $channel,
                ],
                'execute_at' => time() + $delaySeconds,
            ]);
            $this->info("大会员积分@{$name}: {$task_code} 任务等待 {$delaySeconds}s 后继续执行");
            $this->scheduleAfter((float)$delaySeconds);

            return false;
        }

        $response = $this->vipPointDeliverTaskApi()->complete($channel);
        $this->assertVipPointAuthFailure($response, "大会员积分@{$name}: {$task_code} 任务执行时账号未登录");
        if ($response['code']) {
            $this->warning("大会员积分@{$name}: {$task_code} 任务执行失败 " . json_encode($response));

            return false;
        }
        $this->notice("大会员积分@{$name}: {$task_code} 任务执行成功");

        return true;
    }

    protected function completeVipMallView(string $task_code, string $name): bool
    {
        $action = $this->getTask(self::VIEW_DELAY_TASK_KEY);
        if (is_array($action) && ($action['task_code'] ?? null) === $task_code && ($action['type'] ?? null) === 'vip_mall_view') {
            $remaining = max(0, (int)(($action['execute_at'] ?? time()) - time()));
            if ($remaining > 0) {
                $this->scheduleAfter((float)$remaining);

                return false;
            }

            $this->setTask(self::VIEW_DELAY_TASK_KEY, null);
        } else {
            $delaySeconds = mt_rand(10, 20);
            $this->setTask(self::VIEW_DELAY_TASK_KEY, [
                'task_code' => $task_code,
                'type' => 'vip_mall_view',
                'context' => [],
                'execute_at' => time() + $delaySeconds,
            ]);
            $this->info("大会员积分@{$name}: {$task_code} 任务等待 {$delaySeconds}s 后继续执行");
            $this->scheduleAfter((float)$delaySeconds);

            return false;
        }

        $response = $this->vipPointEventApi()->dispatch();
        $this->assertVipPointAuthFailure($response, "大会员积分@{$name}: {$task_code} 任务执行时账号未登录");
        if ($response['code']) {
            $this->warning("大会员积分@{$name}: {$task_code} 任务执行失败 " . json_encode($response));

            return false;
        }
        $this->notice("大会员积分@{$name}: {$task_code} 任务执行成功");

        return true;
    }
}
