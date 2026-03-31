<?php declare(strict_types=1);

namespace Bhp\User;

use Bhp\Api\Vip\ApiUser;
use Bhp\Log\Log;
use Bhp\Util\Common\Common;

class UserProfileService
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $vipInfoResponse = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $navInfoResponse = null;

    /**
     * @var array<string, bool>
     */
    private array $reportedVipRequirementMisses = [];

    public function isVip(string $title = '用户信息', array $scope = [1, 2], string $info = '大会员'): bool
    {
        $response = $this->vipInfo();
        $code = $response['code'] ?? -1;
        if ($code) {
            return false;
        }

        $data = $response['data'] ?? [];

        $isVip = in_array($data['vip_type'] ?? 0, $scope, true)
            && (($data['vip_due_date'] ?? 0) > Common::getUnixTimestamp());

        if ($isVip) {
            return true;
        }

        $this->reportVipRequirementMiss($title, $info);

        return false;
    }

    public function isYearVip(string $title = '用户信息', array $scope = [2], string $info = '年度大会员'): bool
    {
        return $this->isVip($title, $scope, $info);
    }

    public function navInfo(): object
    {
        $response = $this->navInfoResponse();
        $code = $response['code'] ?? -1;
        if ($code) {
            return new \stdClass();
        }

        $data = $response['data'] ?? [];

        return json_decode((string)json_encode($data), false) ?: new \stdClass();
    }

    /**
     * @return array<string, mixed>
     */
    public function vipInfo(): array
    {
        if ($this->vipInfoResponse !== null) {
            return $this->vipInfoResponse;
        }

        $response = $this->requestVipInfo();
        if (($response['code'] ?? -1) !== 0) {
            Log::warning(sprintf(
                '用户资料: 获取大会员信息失败 %s -> %s',
                (string)($response['code'] ?? -1),
                (string)($response['message'] ?? 'invalid response'),
            ));
        }

        return $this->vipInfoResponse = $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function navInfoResponse(): array
    {
        if ($this->navInfoResponse !== null) {
            return $this->navInfoResponse;
        }

        $response = $this->requestNavInfo();
        if (($response['code'] ?? -1) !== 0) {
            Log::warning(sprintf(
                '用户资料: 获取用户信息失败 %s -> %s',
                (string)($response['code'] ?? -1),
                (string)($response['message'] ?? 'invalid response'),
            ));
        }

        return $this->navInfoResponse = $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestVipInfo(): array
    {
        return ApiUser::userVipInfo();
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestNavInfo(): array
    {
        return ApiUser::userNavInfo();
    }

    protected function logVipRequirementNotMet(string $title, string $info): void
    {
        Log::warning(sprintf('%s: 当前账号不是有效的%s，已跳过', $title, $info));
    }

    private function reportVipRequirementMiss(string $title, string $info): void
    {
        $key = $title . '|' . $info;
        if (isset($this->reportedVipRequirementMisses[$key])) {
            return;
        }

        $this->reportedVipRequirementMisses[$key] = true;
        $this->logVipRequirementNotMet($title, $info);
    }
}
