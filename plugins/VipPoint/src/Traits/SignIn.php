<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint\Traits;

trait SignIn
{
    /**
     * 处理签名In
     * @param array $data
     * @param string $name
     * @return bool
     */
    public function signIn(array $data, string $name): bool
    {
        if ($this->isSignIn($data, $name)) {
            return true;
        }

        $this->_signIn($name);

        return false;
    }

    /**
     * 处理签名In
     * @param string $name
     * @return bool
     */
    protected function _signIn(string $name): bool
    {
        $response = $this->vipPointScoreTaskApi()->sign();
        $this->assertVipPointAuthFailure($response, "大会员积分@{$name}: 签到时账号未登录");
        if ($response['code']) {
            if (($response['message'] ?? null) == '签到失败，请刷新重试') {
                $this->warning("大会员积分@{$name}: 今日已签到，重复签到");

                return true;
            }
            $this->warning("大会员积分@{$name}: 签到失败，错误码 {$response['code']} " . json_encode($response, JSON_UNESCAPED_UNICODE));

            return false;
        }

        $this->notice("大会员积分@{$name}: 签到成功");

        return true;
    }

    /**
     * 处理is签名In
     * @param array $data
     * @param string $name
     * @return bool
     */
    protected function _isSignIn(array $data, string $name): bool
    {
        $response = $this->vipPointTaskApi()->homepageCombine();
        $this->assertVipPointAuthFailure($response, "大会员积分@{$name}: 查询签到状态时账号未登录");
        if ($response['code']) {
            $this->warning('大会员积分: 获取签到信息失败');

            return false;
        }

        $data = $response['data'];
        if ($data['task']['signed']) {
            $this->info("大会员积分@{$name}: 今日已完成签到，已有积分 {$data['point_info']['point']}分，剩余 {$data['task']['task_count']} 项积分任务待完成");

            return true;
        }

        $this->info("大会员积分@{$name}: 今日未完成签到，已有积分 {$data['point_info']['point']}分，剩余 {$data['task']['task_count']} 项积分任务待完成");

        return false;
    }

    /**
     * 判断签名In是否满足条件
     * @param array $data
     * @param string $name
     * @return bool
     */
    protected function isSignIn(array $data, string $name): bool
    {
        return $this->_isSignIn($data, $name);
    }
}
