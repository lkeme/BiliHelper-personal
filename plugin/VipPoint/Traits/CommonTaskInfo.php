<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
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
        sleep(mt_rand(10, 20));
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
        sleep(mt_rand(10, 20));
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
