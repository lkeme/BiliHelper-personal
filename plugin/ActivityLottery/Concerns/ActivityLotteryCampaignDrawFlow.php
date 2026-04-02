<?php declare(strict_types=1);

use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\ActivityLottery\Internal\ActivityCampaign;

trait ActivityLotteryCampaignDrawFlow
{
    protected function runCampaignDrawStage(): void
    {
        $this->claimCampaignDrawCredits();
        $this->refreshCampaignDrawCredits();
        $this->executeCampaignDraws();
    }

    /**
     * @return ActivityCampaign[]
     */
    protected function campaignDrawCreditClaimQueue(): array
    {
        $queue = is_array($this->config['campaign_draw_credit_claim_queue'] ?? null)
            ? $this->config['campaign_draw_credit_claim_queue']
            : [];

        return array_values(array_filter(array_map(
            static fn (mixed $campaign): ?ActivityCampaign => is_array($campaign) ? ActivityCampaign::fromArray($campaign) : null,
            $queue
        )));
    }

    /**
     * @return ActivityCampaign[]
     */
    protected function campaignDrawCreditRefreshQueue(): array
    {
        $queue = is_array($this->config['campaign_draw_credit_refresh_queue'] ?? null)
            ? $this->config['campaign_draw_credit_refresh_queue']
            : [];

        return array_values(array_filter(array_map(
            static fn (mixed $campaign): ?ActivityCampaign => is_array($campaign) ? ActivityCampaign::fromArray($campaign) : null,
            $queue
        )));
    }

    /**
     * @return ActivityCampaign[]
     */
    protected function campaignDrawExecuteQueue(): array
    {
        $queue = is_array($this->config['campaign_draw_execute_queue'] ?? null)
            ? $this->config['campaign_draw_execute_queue']
            : [];

        return array_values(array_filter(array_map(
            static fn (mixed $campaign): ?ActivityCampaign => is_array($campaign) ? ActivityCampaign::fromArray($campaign) : null,
            $queue
        )));
    }

    protected function enqueueCampaignDrawCreditClaim(ActivityCampaign $campaign): void
    {
        $this->config['campaign_draw_credit_claim_queue'][] = $campaign->toArray();
    }

    protected function enqueueCampaignDrawCreditRefresh(ActivityCampaign $campaign): void
    {
        $this->config['campaign_draw_credit_refresh_queue'][] = $campaign->toArray();
    }

    protected function enqueueCampaignDrawExecution(ActivityCampaign $campaign): void
    {
        $this->config['campaign_draw_execute_queue'][] = $campaign->toArray();
    }

    protected function popCampaignDrawCreditClaim(): ?ActivityCampaign
    {
        $campaign = array_shift($this->config['campaign_draw_credit_claim_queue']);

        return is_array($campaign) ? ActivityCampaign::fromArray($campaign) : null;
    }

    protected function popCampaignDrawCreditRefresh(): ?ActivityCampaign
    {
        $campaign = array_shift($this->config['campaign_draw_credit_refresh_queue']);

        return is_array($campaign) ? ActivityCampaign::fromArray($campaign) : null;
    }

    protected function popCampaignDrawExecution(): ?ActivityCampaign
    {
        $campaign = array_shift($this->config['campaign_draw_execute_queue']);

        return is_array($campaign) ? ActivityCampaign::fromArray($campaign) : null;
    }

    protected function executeCampaignDraws(): void
    {
        if (isset($this->config[date("Y-m-d")]['campaign_draw_execute'])) {
            return;
        }

        $processed = 0;
        while ($processed < self::CAMPAIGN_DRAW_EXECUTE_BATCH_LIMIT) {
            if ($this->campaignDrawCreditClaimQueue() === [] && $this->campaignDrawCreditRefreshQueue() === [] && $this->campaignDrawExecuteQueue() === []) {
                $this->config[date("Y-m-d")]['campaign_draw_execute'] = true;
                return;
            }

            $campaign = $this->popCampaignDrawExecution();
            if ($campaign === null) {
                return;
            }

            Log::info("转盘活动: 当前活动 {$campaign->title} 开始执行抽奖");

            $response = $this->requestCampaignDrawExecution($campaign);
            $this->handleCampaignDrawExecution($campaign, $response);
            $processed++;
        }
    }

    protected function handleCampaignDrawExecution(ActivityCampaign $campaign, array $data): void
    {
        if ($this->checkCampaignDrawDisabled($campaign, $data)) {
            return;
        }

        if ($data['code'] != 0) {
            Log::warning("转盘活动: 当前活动 {$campaign->title} 抽奖失败 Error: {$data['code']} -> {$data['message']}");
            return;
        }

        if (str_contains($data['data'][0]['gift_name'], '未中奖') || $data['data'][0]['gift_id'] == 0) {
            Log::notice("转盘活动: 当前活动 {$campaign->title} 未命中 {$data['data'][0]['gift_name']} ");
            return;
        }

        Log::notice("转盘活动: 当前活动 {$campaign->title} 抽奖命中 {$data['data'][0]['gift_name']}");
        Notice::push(
            'activity_lottery',
            $this->formatCampaignNotice(
                $campaign,
                '抽奖命中 ' . (string)($data['data'][0]['gift_name'] ?? '')
            )
        );
    }

    protected function refreshCampaignDrawCredits(): void
    {
        if (isset($this->config[date("Y-m-d")]['campaign_draw_credit_refresh'])) {
            return;
        }

        $processed = 0;
        while ($processed < self::CAMPAIGN_DRAW_REFRESH_BATCH_LIMIT) {
            if ($this->campaignDrawCreditClaimQueue() === [] && $this->campaignDrawCreditRefreshQueue() === []) {
                $this->config[date("Y-m-d")]['campaign_draw_credit_refresh'] = true;
                return;
            }

            $campaign = $this->popCampaignDrawCreditRefresh();
            if ($campaign === null) {
                return;
            }

            Log::info("转盘活动: 当前活动 {$campaign->title} 开始刷新抽奖次数");

            $response = $this->requestCampaignDrawCreditRefresh($campaign);
            $this->handleCampaignDrawCreditRefresh($campaign, $response);
            $processed++;
        }
    }

    protected function handleCampaignDrawCreditRefresh(ActivityCampaign $campaign, array $data): void
    {
        if ($this->checkCampaignDrawDisabled($campaign, $data)) {
            return;
        }

        if ($data['code'] != 0 || !isset($data['data']['times'])) {
            Log::warning("转盘活动: 当前活动 {$campaign->title} 刷新抽奖次数失败 Error: {$data['code']} -> {$data['message']}");
            return;
        }

        if ($data['data']['times'] == 0) {
            if (in_array($campaign->drawId, $this->campaignDrawZeroCreditSeen, true)) {
                Log::warning("转盘活动: 当前活动 {$campaign->title} 连续两次没有抽奖次数，判定为已不可用");
                $this->config['campaign_draw_disabled_ids'][] = $campaign->drawId;
                return;
            }

            $this->campaignDrawZeroCreditSeen[] = $campaign->drawId;
            Log::warning("转盘活动: 当前活动 {$campaign->title} 当前没有抽奖次数");
            return;
        }

        Log::info("转盘活动: 当前活动 {$campaign->title} 当前可抽 {$data['data']['times']} 次");
        for ($i = 0; $i < $data['data']['times']; $i++) {
            $this->enqueueCampaignDrawExecution($campaign);
        }
    }

    protected function claimCampaignDrawCredits(): void
    {
        if (isset($this->config[date("Y-m-d")]['campaign_draw_credit_claim'])) {
            return;
        }

        $processed = 0;
        while ($processed < self::CAMPAIGN_DRAW_CLAIM_BATCH_LIMIT) {
            if ($this->campaignDrawCreditClaimQueue() === []) {
                $this->config[date("Y-m-d")]['campaign_draw_credit_claim'] = true;
                return;
            }

            $campaign = $this->popCampaignDrawCreditClaim();
            if ($campaign === null) {
                return;
            }

            Log::info("转盘活动: 当前活动 {$campaign->title} 开始领取抽奖次数");

            $response = $this->requestCampaignDrawCreditClaim($campaign);
            $this->handleCampaignDrawCreditClaim($campaign, $response);
            $processed++;
        }
    }

    protected function requestCampaignDrawExecution(ActivityCampaign $campaign): array
    {
        return Bhp\Api\Api\X\Activity\ApiActivity::doLottery($this->campaignDrawRequestPayload($campaign));
    }

    protected function requestCampaignDrawCreditRefresh(ActivityCampaign $campaign): array
    {
        return Bhp\Api\Api\X\Activity\ApiActivity::myTimes($this->campaignDrawRequestPayload($campaign));
    }

    protected function requestCampaignDrawCreditClaim(ActivityCampaign $campaign): array
    {
        return Bhp\Api\Api\X\Activity\ApiActivity::addTimes($this->campaignDrawRequestPayload($campaign));
    }

    protected function campaignDrawRequestPayload(ActivityCampaign $campaign): array
    {
        return [
            'sid' => $campaign->drawId,
            'url' => $campaign->activityUrl,
            'title' => $campaign->title,
        ];
    }

    protected function handleCampaignDrawCreditClaim(ActivityCampaign $campaign, array $data): void
    {
        if ($this->checkCampaignDrawDisabled($campaign, $data)) {
            return;
        }

        if ($data['code'] != 0 || !isset($data['data']['add_num'])) {
            Log::warning("转盘活动: 当前活动 {$campaign->title} 领取抽奖次数失败 Error: {$data['code']} -> {$data['message']}");
            return;
        }

        Log::info("转盘活动: 当前活动 {$campaign->title} 领取抽奖次数 +{$data['data']['add_num']}");
        $this->enqueueCampaignDrawCreditRefresh($campaign);
    }

    protected function checkCampaignDrawDisabled(ActivityCampaign $campaign, array $data): bool
    {
        if ($data['code'] == 170001 || $data['code'] == 175003 || $data['code'] == 170405) {
            Log::warning("转盘活动: 当前活动 {$campaign->title} 已不可用 Error: {$data['code']} -> {$data['message']}");
            $this->config['campaign_draw_disabled_ids'][] = $campaign->drawId;
            return true;
        }

        return false;
    }
}
