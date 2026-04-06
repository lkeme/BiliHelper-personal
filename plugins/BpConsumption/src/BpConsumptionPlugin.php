<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\BpConsumption;

use Bhp\Api\Pay\ApiPay;
use Bhp\Api\Pay\ApiWallet;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

class BpConsumptionPlugin extends BasePlugin implements PluginTaskInterface
{
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiPay $payApi = null;
    private ?ApiWallet $walletApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('bp_consumption')) {
            return TaskResult::keepSchedule();
        }

        $this->consumptionTask();

        return TaskResult::nextAt(14, 0, 0, 1, 120);
    }

    protected function consumptionTask(): void
    {
        if (!$this->userProfiles()->isYearVip('消费B币券')) {
            return;
        }

        $bpBalance = $this->getUserWallet();
        if ($bpBalance != 5) {
            return;
        }

        if ($this->config('bp_consumption.bp2charge', false, 'bool')) {
            $upMid = $this->config('bp_consumption.bp2charge_uid', 6580464, 'int');
            if ($upMid == intval($this->uid())) {
                $this->warning("消费B币券: 充电UID不能为自己 {$upMid}，请检查设置项");

                return;
            }

            $this->BP2charge($upMid, $bpBalance);

            return;
        }

        if ($this->config('bp_consumption.bp2gold', false, 'bool')) {
            $this->BP2gold($bpBalance);
        }
    }

    protected function BP2gold(int $num): void
    {
        $response = $this->payApi()->gold($num);
        $this->authFailureClassifier->assertNotAuthFailure($response, '消费B币券: 充值金瓜子时账号未登录');
        if ($response['code']) {
            $this->warning("消费B币券: 充值金瓜子失败 {$response['code']} -> {$response['message']}");
        } else {
            $this->notice("消费B币券: 充值金瓜子成功 NUM -> {$response['data']['bp']} ORDER -> {$response['data']['order_id']}");
        }
    }

    protected function BP2charge(int $uid, int $num = 5): void
    {
        $response = $this->payApi()->battery($uid, $num);
        $this->authFailureClassifier->assertNotAuthFailure($response, "消费B币券: 给{$uid}充电时账号未登录");
        if ($response['code']) {
            $this->warning("消费B币券: 给{$uid}充电失败 {$response['code']} -> {$response['message']}");
        } elseif ($response['data']['status'] == 4) {
            $this->notice("消费B币券: 给{$uid}充电成功 NUM -> {$response['data']['bp_num']} EXP -> {$response['data']['exp']} ORDER -> {$response['data']['order_no']}");
        } else {
            $this->warning("消费B币券: 给{$uid}充电失败 {$response['data']['status']} -> {$response['data']['msg']}");
        }
    }

    protected function getUserWallet(): int
    {
        $response = $this->walletApi()->getUserWallet();
        $this->authFailureClassifier->assertNotAuthFailure($response, '消费B币券: 获取钱包时账号未登录');

        $code = $this->resolveWalletResponseCode($response);
        $message = $this->resolveWalletResponseMessage($response);
        if ($code !== 0) {
            $this->warning("消费B币券: 获取用户钱包信息失败 {$code} -> {$message}");

            return 0;
        }

        $balance = $this->resolveWalletBalance($response);
        if ($balance === null) {
            $this->warning('消费B币券: 获取用户钱包信息失败，接口返回中缺少可用的B币券余额字段');

            return 0;
        }

        $this->info("消费B币券: 获取用户钱包信息成功 B币券余额剩余 {$balance}");

        return intval($balance);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function resolveWalletResponseCode(array $response): int
    {
        foreach (['code', 'errno', 'errcode'] as $key) {
            if (isset($response[$key]) && is_numeric($response[$key])) {
                return (int)$response[$key];
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function resolveWalletResponseMessage(array $response): string
    {
        foreach (['message', 'msg', 'errmsg', 'showMsg'] as $key) {
            $value = trim((string)($response[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '未知错误';
    }

    /**
     * @param array<string, mixed> $response
     */
    private function resolveWalletBalance(array $response): ?float
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $accountInfo = is_array($data['accountInfo'] ?? null) ? $data['accountInfo'] : [];

        foreach ([
            $data['couponBalance'] ?? null,
            $data['availableBp'] ?? null,
            $data['totalBp'] ?? null,
            $accountInfo['couponBalance'] ?? null,
            $accountInfo['availableBp'] ?? null,
            $accountInfo['totalBp'] ?? null,
        ] as $candidate) {
            if (is_numeric($candidate)) {
                return (float)$candidate;
            }
        }

        return null;
    }

    private function payApi(): ApiPay
    {
        return $this->payApi ??= new ApiPay($this->appContext()->request());
    }

    private function walletApi(): ApiWallet
    {
        return $this->walletApi ??= new ApiWallet($this->appContext()->request());
    }
}
