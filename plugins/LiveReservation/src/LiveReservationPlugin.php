<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveReservation;

use Bhp\Api\Space\ApiReservation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

class LiveReservationPlugin extends BasePlugin implements PluginTaskInterface
{
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiReservation $reservationApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */
    public ?array $info = [
        'hook' => 'LiveReservation',
        'name' => 'LiveReservation',
        'version' => '0.0.1',
        'desc' => '预约直播有奖',
        'author' => 'Lkeme',
        'priority' => 1109,
        'cycle' => '1-3(小时)',
    ];

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
        $vmids = (string)$this->config('live_reservation.vmids', '');
        $vmids = explode(',', $vmids);
        foreach ($vmids as $vmid) {
            $reservationList = $this->fetchReservation($vmid);
            foreach ($reservationList as $reservation) {
                $this->reserve($reservation);
            }
        }
    }

    /**
     * @return array<int, array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed}>
     */
    protected function fetchReservation(string $vmid): array
    {
        $reservationList = [];
        $response = $this->reservationApi()->reservation($vmid);
        $this->authFailureClassifier->assertNotAuthFailure($response, '预约直播: 获取预约列表时账号未登录');
        if ($response['code']) {
            $this->warning("预约直播: 获取预约列表失败: {$response['code']} -> {$response['message']}");
        } else {
            $deData = $response['data'] ?: [];
            foreach ($deData as $data) {
                $result = self::checkLottery($data);
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
     * @return array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed}|false
     */
    protected function checkLottery(array $data): bool|array
    {
        if ($data['etime'] <= time()) {
            return false;
        }
        if ($data['is_follow']) {
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
}
