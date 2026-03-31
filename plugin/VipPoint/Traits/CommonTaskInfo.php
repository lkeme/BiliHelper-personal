<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Log\Log;

trait CommonTaskInfo
{
    protected const VIEW_DELAY_TASK_KEY = 'DelayedAction';

    /**
     * 是否已经完成
     * @param array $data
     * @param string $name
     * @param $title
     * @param $code
     * @return bool
     */
    protected function isComplete(array $data, string $name, $title, $code): bool
    {
        $item = $this->getTaskInfo($data, $title, $code);
        // 异常情况1
        if (empty($item)) {
            Log::warning("大会员积分@{$name}: 任务异常，暂定完成，通知开发者修复");
            return true;
        }
        // 完成情况1
        if ($item['state'] == 3) {
            Log::notice("大会员积分@{$name}: {$item['task_code']} 任务已经完成");
            return true;
        }
        // 需要领取或者需要完成
        if (in_array($item['state'], [0, 1])) {
            return false;
        }
        // 额外情况
        Log::warning("大会员积分@{$name}: {$item['task_code']} 任务出现额外情况，暂定完成，通知开发者修复");

        return true;
    }

    /**
     * 获取任务
     * @param array $data
     * @param string $title
     * @param string $code
     * @return array
     */
    protected function getTaskInfo(array $data, string $title, string $code): array
    {
        $modules = $data['data']['task_info']['modules'] ?? [];
        if (empty($modules)) return [];
        //
        foreach ($modules as $module) {
            if ($module['module_title'] != $title) continue;
            //
            foreach ($module['common_task_item'] as $item) {
                if ($item['task_code'] != $code) continue;
                //
                return $item;
            }
        }
        return [];
    }

    /**
     * @param array $data
     * @param string $name
     * @param string $title
     * @param string $code
     * @param int $c
     * @param string $arg
     * @return bool
     */
    protected function worker(array $data, string $name, string $title, string $code, int $c, string $arg = ''): bool
    {
        $item = $this->getTaskInfo($data, $title, $code);

        if ($item['state'] == 0) {
            // 领取任务
            $response = \Bhp\Api\Api\Pgc\Activity\Score\ApiTask::receive($item['task_code']);
            if ($response['code']) {
                Log::warning("大会员积分@{$name}: {$item['task_code']} 任务领取失败 " . json_encode($response));
                return false;
            }
            Log::notice("大会员积分@{$name}: {$item['task_code']} 任务领取成功");
        }
        // 做任务
        return match ($c) {
            1 => $this->complete($item['task_code'], $name),
            2 => $this->completeView($item['task_code'], $name, $arg),
            3 => $this->completeVipMallView($item['task_code'], $name),
            4 => $this->completeReceive(),
            default => false,
        };
    }

    /**
     * @param string $task_code
     * @param string $name
     * @return bool
     */
    protected function complete(string $task_code, string $name): bool
    {
        // 做任务
        $response = \Bhp\Api\Api\Pgc\Activity\Score\ApiTask::complete($task_code);
        if ($response['code']) {
            Log::warning("大会员积分@{$name}: {$task_code} 任务执行失败 " . json_encode($response));
            return false;
        }
        Log::notice("大会员积分@{$name}: {$task_code} 任务执行成功");
        return true;
    }

    /**
     * @param string $task_code
     * @param string $name
     * @param string $channel
     * @return bool
     */
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
            Log::info("大会员积分@{$name}: {$task_code} 任务等待 {$delaySeconds}s 后继续执行");
            $this->scheduleAfter((float)$delaySeconds);
            return false;
        }

        // 做任务
        $response = \Bhp\Api\Api\Pgc\Activity\Deliver\ApiTask::complete($channel);
        if ($response['code']) {
            Log::warning("大会员积分@{$name}: {$task_code} 任务执行失败 " . json_encode($response));
            return false;
        }
        Log::notice("大会员积分@{$name}: {$task_code} 任务执行成功");
        return true;
    }

    /**
     * @param string $task_code
     * @param string $name
     * @return bool
     */
    protected function completeVipMallView(string $task_code, string $name,): bool
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
            Log::info("大会员积分@{$name}: {$task_code} 任务等待 {$delaySeconds}s 后继续执行");
            $this->scheduleAfter((float)$delaySeconds);
            return false;
        }

        // 做任务
        $response = \Bhp\Api\Show\Api\Activity\Fire\Common\ApiEvent::dispatch();
        if ($response['code']) {
            Log::warning("大会员积分@{$name}: {$task_code} 任务执行失败 " . json_encode($response));
            return false;
        }
        Log::notice("大会员积分@{$name}: {$task_code} 任务执行成功");
        return true;
    }

    /**
     * @return bool
     */
    protected function completeReceive(): bool
    {
        return true;
    }
}
