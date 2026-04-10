<?php declare(strict_types=1);

namespace Bhp\Api\XLive;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiRevenueWallet extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function appSilver2coin(): array
    {
        return $this->decodePost(
            'app',
            'https://api.live.bilibili.com/AppExchange/silver2coin',
            $this->request()->signCommonPayload([]),
            [],
            'xlive.revenue.app_silver2coin',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function pcSilver2coin(): array
    {
        return $this->decodePost('pc', 'https://api.live.bilibili.com/xlive/revenue/v1/wallet/silver2coin', [
            'csrf_token' => $this->request()->csrfValue(),
            'csrf' => $this->request()->csrfValue(),
            'visit_id' => '',
        ], [], 'xlive.revenue.pc_silver2coin');
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/revenue/v1/wallet/getStatus', [], [], 'xlive.revenue.status');
    }

    /**
     * @return array<string, mixed>
     */
    public function myWallet(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/revenue/v1/wallet/myWallet', [
            'need_bp' => 1,
            'need_metal' => 1,
            'platform' => 'pc',
        ], [], 'xlive.revenue.my_wallet');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */}
