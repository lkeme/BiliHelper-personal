<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Silver2Coin;

use Bhp\Api\XLive\ApiRevenueWallet;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\NoLoginException;

class Silver2CoinPlugin extends BasePlugin implements PluginTaskInterface
{
    private ?ApiRevenueWallet $revenueWalletApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('silver2coin')) {
            return TaskResult::keepSchedule();
        }

        try {
            if (!$this->before()) {
                return TaskResult::nextDayAt(10, 0, 0, 1, 60);
            }

            if (!$this->exchangeTask()) {
                return TaskResult::after(3600);
            }

            $this->after();
        } catch (NoLoginException $e) {
            $this->warning("银瓜子兑换硬币: {$e->getMessage()}");

            return TaskResult::after(3600);
        }

        return TaskResult::nextDayAt(10, 0, 0, 1, 60);
    }

    protected function after(): void
    {
        $this->revenueWalletApi()->myWallet();
    }

    protected function before(): bool
    {
        $response = $this->revenueWalletApi()->getStatus();

        switch ($response['code']) {
            case -101:
                throw new NoLoginException($response['message']);
            case 0:
                if ($response['data']['silver_2_coin_left'] == 0) {
                    $this->notice('银瓜子兑换硬币: 今日已兑换过一次了哦~');

                    return false;
                }
                if ($response['data']['silver'] < 700) {
                    $this->notice('银瓜子兑换硬币: 瓜子余额不足以兑换哦~~');

                    return false;
                }

                return true;
            default:
                $this->warning("银瓜子兑换硬币: 获取钱包状态失败 {$response['code']} -> {$response['message']}");

                return false;
        }
    }

    protected function exchangeTask(): bool
    {
        $response = $this->revenueWalletApi()->appSilver2coin();
        if ($this->handle('APP', $response)) {
            return true;
        }

        $response = $this->revenueWalletApi()->pcSilver2coin();
        if ($this->handle('PC', $response)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function handle(string $type, array $data): bool
    {
        switch ($data['code']) {
            case 0:
                $this->notice("银瓜子兑换硬币[$type]: {$data['message']}");

                return true;
            case 403:
                $this->warning("银瓜子兑换硬币[$type]: {$data['message']}");

                return true;
            default:
                $this->warning("银瓜子兑换硬币[$type]: CODE -> {$data['code']} MSG -> {$data['message']} ");
        }

        return false;
    }
    private function revenueWalletApi(): ApiRevenueWallet
    {
        return $this->revenueWalletApi ??= new ApiRevenueWallet($this->appContext()->request());
    }
}
