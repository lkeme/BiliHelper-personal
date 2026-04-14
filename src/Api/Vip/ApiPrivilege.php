<?php declare(strict_types=1);

namespace Bhp\Api\Vip;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiPrivilege extends AbstractApiClient
{
    /**
     * 初始化 ApiPrivilege
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function my(): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/vip/privilege/my', [], [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ], 'vip.privilege.my');
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(int $type): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/vip/privilege/receive', [
            'type' => $type,
            'csrf' => $this->request()->csrfValue(),
        ], [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ], 'vip.privilege.receive');
    }
}
