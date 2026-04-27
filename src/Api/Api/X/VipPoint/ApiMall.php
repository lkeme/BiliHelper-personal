<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\VipPoint;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiMall extends AbstractApiClient
{
    private const HOME_REFERER = 'https://big.bilibili.com/mobile/bigPoint/';

    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function skuList(int $pn = 1, int $ps = 20, ?int $categoryId = null): array
    {
        $payload = $this->request()->signCommonPayload([
            'csrf' => $this->request()->csrfValue(),
            'in_review' => 0,
            'pn' => max(1, $pn),
            'ps' => max(1, $ps),
        ]);
        if ($categoryId !== null && $categoryId > 0) {
            $payload['category_id'] = $categoryId;
        }

        return $this->decodeGet(
            'app',
            'https://api.bilibili.com/x/vip_point/sku/list',
            $payload,
            ['Referer' => self::HOME_REFERER],
            'vip_point.sku.list',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function skuInfo(string $token): array
    {
        $normalizedToken = trim($token);

        return $this->decodeGet(
            'app',
            'https://api.bilibili.com/x/vip_point/sku/info',
            $this->request()->signCommonPayload([
                'token' => $normalizedToken,
                'csrf' => $this->request()->csrfValue(),
            ]),
            ['Referer' => $this->skuReferer($normalizedToken)],
            'vip_point.sku.info',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyOrder(string $token, ?int $price = null): array
    {
        $payload = [
            'token' => trim($token),
            'csrf' => $this->request()->csrfValue(),
        ];
        if ($price !== null && $price > 0) {
            $payload['price'] = $price;
        }

        return $this->decodePost(
            'app',
            'https://api.bilibili.com/x/vip_point/trade/order/verify',
            $this->request()->signCommonPayload($payload),
            ['Referer' => $this->skuReferer((string)$payload['token'])],
            'vip_point.trade.order.verify',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrder(string $token, ?int $price = null): array
    {
        $payload = [
            'token' => trim($token),
            'csrf' => $this->request()->csrfValue(),
        ];
        if ($price !== null && $price > 0) {
            $payload['price'] = $price;
        }

        return $this->decodePost(
            'app',
            'https://api.bilibili.com/x/vip_point/trade/order/create',
            $this->request()->signCommonPayload($payload),
            ['Referer' => $this->skuReferer((string)$payload['token'])],
            'vip_point.trade.order.create',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentOrder(string $orderNo, string $token): array
    {
        return $this->decodeGet(
            'app',
            'https://api.bilibili.com/x/vip_point/trade/order/payment',
            $this->request()->signCommonPayload([
                'order_no' => trim($orderNo),
                'csrf' => $this->request()->csrfValue(),
            ]),
            [
                'Referer' => $this->skuReferer($token),
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'vip_point.trade.order.payment',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function orderDetail(string $orderNo): array
    {
        $normalizedOrderNo = trim($orderNo);

        return $this->decodeGet(
            'app',
            'https://api.bilibili.com/x/vip_point/trade/order/detail',
            $this->request()->signCommonPayload([
                'order_no' => $normalizedOrderNo,
                'csrf' => $this->request()->csrfValue(),
            ]),
            [
                'Referer' => "https://big.bilibili.com/mobile/bigPoint/orderResult?order_no={$normalizedOrderNo}",
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'vip_point.trade.order.detail',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function pointList(int $changeType = 0, int $pn = 1, int $ps = 20): array
    {
        return $this->decodeGet(
            'app',
            'https://api.bilibili.com/x/vip_point/list',
            $this->request()->signCommonPayload([
                'csrf' => $this->request()->csrfValue(),
                'change_type' => max(0, $changeType),
                'pn' => max(1, $pn),
                'ps' => max(1, $ps),
            ]),
            ['Referer' => self::HOME_REFERER],
            'vip_point.list',
        );
    }

    private function skuReferer(string $token): string
    {
        return 'https://big.bilibili.com/mobile/bigPoint/sku/' . trim($token);
    }
}
