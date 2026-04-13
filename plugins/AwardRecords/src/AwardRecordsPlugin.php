<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\AwardRecords;

use Bhp\Api\Lottery\V1\ApiAward;
use Bhp\Api\XLive\GeneralInterface\V1\ApiGuardBenefit;
use Bhp\Api\XLive\LotteryInterface\V1\ApiAnchor;
use Bhp\Api\XLive\Revenue\V1\ApiWallet;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\RequestException;

class AwardRecordsPlugin extends BasePlugin implements PluginTaskInterface
{
    private const CACHE_SCOPE = 'AwardRecords';
    private const TRANSIENT_RETRY_SECONDS = 300;

    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiWallet $operationWalletApi = null;
    private ?ApiAward $awardApi = null;
    private ?ApiAnchor $anchorApi = null;
    private ?ApiGuardBenefit $guardBenefitApi = null;

    /**
     * @var array<string, list<string>>
     */
    protected array $records = [];

    /**
     * @var array<string, int>
     */
    protected array $locks = [
        'operation' => 0,
        'award' => 0,
        'celestial' => 0,
        'bonus' => 0,
    ];

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
        if (!$this->enabled('award_records')) {
            return TaskResult::keepSchedule();
        }

        $this->awardRecordsTask();

        return TaskResult::after(5 * 60);
    }

    protected function awardRecordsTask(): void
    {
        $this->records = ($tmp = $this->cacheGet('records', self::CACHE_SCOPE, null)) ? $tmp : $this->initRecords();

        if ($this->locks['operation'] < time()) {
            $this->operation();
        }
        if ($this->locks['award'] < time()) {
            $this->award();
        }
        if ($this->locks['celestial'] < time()) {
            $this->celestial();
        }
        if ($this->locks['bonus'] < time()) {
            $this->bonus();
        }

        $this->cacheSet('records', $this->records, self::CACHE_SCOPE);
    }

    protected function operation(string $title = '运营奖惩'): bool
    {
        try {
            $response = $this->operationWalletApi()->apCenterList();
        } catch (RequestException $exception) {
            $this->warning("获奖记录: 获取{$title}失败 {$exception->getMessage()}");
            $this->locks['operation'] = time() + self::TRANSIENT_RETRY_SECONDS;

            return false;
        }
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['operation'] = $this->failureLockAt((int)$response['code'], 6 * 60 * 60);

            return false;
        }

        $now = date('m') . '月' . date('d') . '日';
        foreach ($response['data']['list'] as $data) {
            $info = $data['md'] . '-' . $data['desc'];
            $isNew = !in_array($info, $this->records['operation'], true);

            if ($isNew) {
                $this->records['operation'][] = $info;
            }

            if (!$isNew || $now !== $data['md']) {
                continue;
            }

            $this->notice($info);
            $this->notify($title, $info);
        }
        $this->locks['operation'] = time() + 24 * 60 * 60;

        return true;
    }

    protected function award(string $title = '获奖记录'): bool
    {
        try {
            $response = $this->awardApi()->awardList();
        } catch (RequestException $exception) {
            $this->warning("获奖记录: 获取{$title}失败 {$exception->getMessage()}");
            $this->locks['award'] = time() + self::TRANSIENT_RETRY_SECONDS;

            return false;
        }
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['award'] = $this->failureLockAt((int)$response['code'], 60 * 60);

            return false;
        }

        foreach ($response['data']['list'] as $data) {
            $info = $data['create_time'] . '-' . $data['id'] . '-' . $data['source'] . '-' . $data['gift_name'];
            $isNew = !in_array($info, $this->records['award'], true);

            if ($isNew) {
                $this->records['award'][] = $info;
            }

            if ($isNew && $this->isTodayDateTime((string)($data['create_time'] ?? '')) && $data['update_time'] === '') {
                $this->notice($info);
                $this->notify($title, $info);
            }
        }
        $this->locks['award'] = time() + 6 * 60 * 60;

        return true;
    }

    protected function celestial(string $title = '天选时刻'): bool
    {
        try {
            $response = $this->anchorApi()->awardRecord();
        } catch (RequestException $exception) {
            $this->warning("获奖记录: 获取{$title}失败 {$exception->getMessage()}");
            $this->locks['celestial'] = time() + self::TRANSIENT_RETRY_SECONDS;

            return false;
        }
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['celestial'] = $this->failureLockAt((int)$response['code'], 30 * 60);

            return false;
        }

        foreach ($response['data']['list'] as $data) {
            $info = $data['end_time'] . '-' . $data['id'] . '-' . $data['anchor_name'] . '-' . $data['award_name'];
            $isNew = !in_array($info, $this->records['celestial'], true);

            if ($isNew) {
                $this->records['celestial'][] = $info;
            }

            if ($isNew && $this->isTodayDateTime((string)($data['end_time'] ?? ''))) {
                $this->notice($info);
                $this->notify($title, $info);
            }
        }
        $this->locks['celestial'] = time() + 10 * 60;

        return true;
    }

    protected function bonus(string $title = '航海回馈'): bool
    {
        try {
            $response = $this->guardBenefitApi()->winListByUser();
        } catch (RequestException $exception) {
            $this->warning("获奖记录: 获取{$title}失败 {$exception->getMessage()}");
            $this->locks['bonus'] = time() + self::TRANSIENT_RETRY_SECONDS;

            return false;
        }
        $this->authFailureClassifier->assertNotAuthFailure($response, "获奖记录: 获取{$title}时账号未登录");

        if ($response['code']) {
            $this->warning("获奖记录: 获取{$title}失败 {$response['code']} -> {$response['message']}");
            $this->locks['bonus'] = $this->failureLockAt((int)$response['code'], 6 * 30 * 60);

            return false;
        }

        foreach ($response['data']['list'] as $data) {
            $info = $data['settlement_time'] . '-' . $data['win_id'] . '-' . $data['anchor_name'] . '-' . $data['award_name'];
            $isNew = !in_array($info, $this->records['bonus'], true);
            $settlementTime = strtotime($data['settlement_time']);
            if ($settlementTime === false || time() < $settlementTime) {
                continue;
            }

            if ($isNew) {
                $this->records['bonus'][] = $info;
                if ($this->isTodayDateTime((string)($data['settlement_time'] ?? ''))) {
                    $this->notice($info);
                    $this->notify($title, $info);
                }
            }
        }
        $this->locks['bonus'] = time() + 24 * 60 * 60;

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function initRecords(): array
    {
        return [
            'operation' => [],
            'award' => [],
            'celestial' => [],
            'bonus' => [],
        ];
    }

    private function operationWalletApi(): ApiWallet
    {
        return $this->operationWalletApi ??= new ApiWallet($this->appContext()->request());
    }

    private function awardApi(): ApiAward
    {
        return $this->awardApi ??= new ApiAward($this->appContext()->request());
    }

    private function anchorApi(): ApiAnchor
    {
        return $this->anchorApi ??= new ApiAnchor($this->appContext()->request());
    }

    private function guardBenefitApi(): ApiGuardBenefit
    {
        return $this->guardBenefitApi ??= new ApiGuardBenefit($this->appContext()->request());
    }

    private function isTodayDateTime(string $value): bool
    {
        $timestamp = strtotime(trim($value));
        if ($timestamp === false) {
            return false;
        }

        return date('Y-m-d', $timestamp) === date('Y-m-d');
    }

    private function failureLockAt(int $code, int $fallbackSeconds): int
    {
        return time() + ($code === -500 ? self::TRANSIENT_RETRY_SECONDS : $fallbackSeconds);
    }
}
