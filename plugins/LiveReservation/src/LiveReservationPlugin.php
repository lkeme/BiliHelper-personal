<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveReservation;

use Bhp\Api\Space\ApiReservation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

final class LiveReservationPlugin extends BasePlugin implements PluginTaskInterface
{
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiReservation $reservationApi = null;
    private ?LiveReservationRemoteIndexLoader $remoteIndexLoader = null;

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('live_reservation')) {
            return TaskResult::keepSchedule();
        }

        $this->reservationTask();

        return TaskResult::after(mt_rand(1, 3) * 60 * 60);
    }

    protected function reservationTask(): void
    {
        $targets = $this->mergeReservationTargets(
            $this->remoteIndexLoader()->load(),
            $this->parseConfiguredVmids((string)$this->config('live_reservation.vmids', '')),
        );

        foreach ($targets as $target) {
            $reservationList = $this->fetchReservation((string)$target['up_mid'], $target['sids']);
            foreach ($reservationList as $reservation) {
                $this->reserve($reservation);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $remoteTargets
     * @param string[] $configuredVmids
     * @return array<int, array{up_mid:string,sids:array<int,string>}>
     */
    protected function mergeReservationTargets(array $remoteTargets, array $configuredVmids): array
    {
        $merged = [];
        foreach ($remoteTargets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $upMid = trim((string)($target['up_mid'] ?? ''));
            if ($upMid === '') {
                continue;
            }

            $key = 'up_mid:' . $upMid;
            $merged[$key] = [
                'up_mid' => $upMid,
                'sids' => array_values(array_filter(array_map(
                    static fn (mixed $sid): string => trim((string)$sid),
                    is_array($target['sids'] ?? null) ? $target['sids'] : [],
                ), static fn (string $sid): bool => $sid !== '')),
            ];
        }

        foreach ($configuredVmids as $vmid) {
            $key = 'up_mid:' . $vmid;
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'up_mid' => $vmid,
                    'sids' => [],
                ];
            }
        }

        return array_values($merged);
    }

    /**
     * @return string[]
     */
    protected function parseConfiguredVmids(string $vmids): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            preg_split('/[|,]/', $vmids, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param string[] $allowedSids
     * @return array<int, array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed}>
     */
    protected function fetchReservation(string $vmid, array $allowedSids = []): array
    {
        $reservationList = [];
        $response = $this->reservationApi()->reservation($vmid);
        $this->authFailureClassifier->assertNotAuthFailure($response, '预约直播: 获取预约列表时账号未登录');
        if ($response['code']) {
            $this->warning("预约直播: 获取预约列表失败: {$response['code']} -> {$response['message']}");
        } else {
            $deData = $response['data'] ?: [];
            foreach ($deData as $data) {
                $result = $this->checkLottery($data, $allowedSids);
                if (!$result) {
                    continue;
                }
                $reservationList[] = $result;
            }
            $this->info('预约直播: 获取预约列表成功 ' . count($reservationList));
        }

        return $reservationList;
    }

    /**
     * @param array<string, mixed> $data
     * @param string[] $allowedSids
     * @return array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed}|false
     */
    protected function checkLottery(array $data, array $allowedSids = []): bool|array
    {
        if ($data['etime'] <= time()) {
            return false;
        }
        if ($data['is_follow']) {
            return false;
        }
        if ($allowedSids !== [] && !in_array((string)($data['sid'] ?? ''), $allowedSids, true)) {
            return false;
        }
        if (array_key_exists('lottery_prize_info', $data) && array_key_exists('lottery_type', $data)) {
            return [
                'sid' => $data['sid'],
                'name' => $data['name'],
                'vmid' => $data['up_mid'],
                'jump_url' => $data['lottery_prize_info']['jump_url'],
                'text' => $data['lottery_prize_info']['text'],
            ];
        }

        return false;
    }

    /**
     * @param array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed} $data
     */
    protected function reserve(array $data): void
    {
        $response = $this->reservationApi()->reserve($data['sid'], $data['vmid']);
        $this->authFailureClassifier->assertNotAuthFailure($response, '预约直播: 执行预约时账号未登录');

        $this->info("预约直播: {$data['name']}|{$data['vmid']}|{$data['sid']}");
        $this->info("预约直播: {$data['text']}");
        $this->info("预约直播: {$data['jump_url']}");

        if ($response['code']) {
            $this->warning("预约直播: 尝试预约并抽奖失败 {$response['code']} -> {$response['message']}");
        } else {
            $this->notice("预约直播: 尝试预约并抽奖成功 {$response['message']}");
        }
    }

    private function reservationApi(): ApiReservation
    {
        return $this->reservationApi ??= new ApiReservation($this->appContext()->request());
    }

    private function remoteIndexLoader(): LiveReservationRemoteIndexLoader
    {
        return $this->remoteIndexLoader ??= new LiveReservationRemoteIndexLoader($this->appContext());
    }
}
