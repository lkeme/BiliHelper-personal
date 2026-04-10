<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\VipPoint;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiTask extends AbstractApiClient
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task',
    ];

    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function combine(): array
    {
        return $this->decodeGet('app', 'https://api.bilibili.com/x/vip_point/task/combine', $this->request()->signCommonPayload([]), self::HEADERS, 'vip_point.task.combine');
    }

    /**
     * @return array<string, mixed>
     */
    public function homepageCombine(): array
    {
        return $this->decodeGet('app', 'https://api.bilibili.com/x/vip_point/homepage/combine', $this->request()->signCommonPayload([]), self::HEADERS, 'vip_point.homepage.combine');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */}
