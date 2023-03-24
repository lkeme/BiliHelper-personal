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


use Bhp\Api\Api\Pgc\Activity\Score\ApiTask;
use Bhp\Log\Log;

trait SignIn
{
    /**
     * 签到
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function signIn(array $data, string $name): bool
    {
        if ($this->isSignIn($data, $name)) {
            return true;
        }
        //
        $this->_signIn($name);
        return false;
    }

    /**
     * 签到
     * @param string $name
     * @return bool
     */
    protected function _signIn(string $name): bool
    {
        // {"code":0,"message":"success"}
        $response = ApiTask::sign();
        //
        if ($response['code']) {
            Log::warning("大会员积分@{$name}: 签到失败" . json_encode($response));
            return false;
        }
        return true;
    }


    /**
     * 是否已经签到
     * @param array $data
     * @param string $now
     * @return int
     */
    protected function _isSignIn(array $data, string $now): int
    {
        $histories = $data['data']['task_info']['sing_task_item']['histories'] ?? [];
        //
        foreach ($histories as $h) {
            // day: "2022-09-10" is_today: true score: 5 signed: true
            if ($h['day'] == $now && isset($h['is_today']) && $h['signed'] && $h['is_today']) {
                return $h['score'];
            }
        }
        return 0;
    }


    /**
     * 是否已经签到
     * @param array $data
     * @param string $name
     * @return bool
     */
    protected function isSignIn(array $data, string $name): bool
    {
        $now = date("Y-m-d");
        //
        if ($score = $this->_isSignIn($data, $now)) {
            Log::info("大会员积分@{$name}: 今日完成签到，获得积分 {$score}分 已累计签到 {$data['data']['task_info']['sing_task_item']['count']}天");
            return true;
        }

        return false;
    }

}
