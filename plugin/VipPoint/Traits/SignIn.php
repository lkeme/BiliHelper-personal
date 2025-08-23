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


use Bhp\Api\Api\Pgc\Activity\Score\ApiTask;
use Bhp\Api\Api\X\VipPoint\ApiTask as VipPointApiTask;
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
        // {"code":xxxx,"message":"xxxxx"}
        // {"code":0,"message":"success"}
        // {"code":6007000,"message":"签到失败，请刷新重试"}  判断为重复签到
        $response = ApiTask::sign();
        if ($response['code']) {
            if (isset($response['message']) && $response['message'] == '签到失败，请刷新重试') {
                Log::warning("大会员积分@{$name}: 今日已签到，重复签到");
                return true;
            }
            Log::warning("大会员积分@{$name}: 签到失败，错误码 {$response['code']} " . json_encode($response, JSON_UNESCAPED_UNICODE));
            return false;
        }
        //
        Log::info("大会员积分@{$name}: 签到成功");
        return true;
    }


    /**
     * 是否已经签到
     * @param array $data
     * @param string $name
     * @return bool
     */
    protected function _isSignIn(array $data, string $name): bool
    {
        $response = VipPointApiTask::homepageCombine();
        if ($response['code']) {
            Log::warning('大会员积分: 获取签到信息失败');
            return false;
        }
        //
        $data = $response['data'];
        if ($data['task']['signed']) {
            Log::info("大会员积分@{$name}: 今日已完成签到，已有积分 {$data['point_info']['point']}分，剩余 {$data['task']['task_count']} 项积分任务待完成");
            return true;
        }
        //
        Log::info("大会员积分@{$name}: 今日未完成签到，已有积分 {$data['point_info']['point']}分，剩余 {$data['task']['task_count']} 项积分任务待完成");
        return false;
    }


    /**
     * 是否已经签到
     * @param array $data
     * @param string $name
     * @return bool
     */
    protected function isSignIn(array $data, string $name): bool
    {
        return $this->_isSignIn($data, $name);
    }

}
