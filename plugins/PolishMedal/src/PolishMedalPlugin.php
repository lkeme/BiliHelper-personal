<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\PolishMedal;

use Bhp\Api\Msg\ApiMsg;
use Bhp\Api\XLive\AppUcenter\V1\ApiFansMedal;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\ArrayR\ArrayR;
use Bhp\Util\Fake\Fake;

class PolishMedalPlugin extends BasePlugin implements PluginTaskInterface
{
    private const CACHE_SCOPE = 'PolishMedal';
    private const INVALID_MEDALS_CACHE_KEY = 'invalid_medals';
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiFansMedal $fansMedalApi = null;
    private ?ApiMsg $msgApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    /**
     * @var array<int, array{uid: mixed, roomid: mixed, medal_id: mixed, medal_name: string, anchor_name: string}>
     */
    private array $grey_fans_medals = [];
    private int $medal_batch_total = 0;

    private int $metal_lock = 0;
    private int $next_polish_at = 0;

    /**
     * @var array<int, mixed>
     */
    private array $black_list = [];

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('polish_medal')) {
            return TaskResult::keepSchedule();
        }

        $now = time();
        if ($this->metal_lock < $now) {
            if (empty($this->grey_fans_medals)) {
                if ($this->config('polish_medal.everyday', false, 'bool')) {
                    $this->fetchGreyMedalList(true);
                    $this->metal_lock = $now + (int)TaskResult::secondsUntilNextAt(7, 0, 0, 1, 60);
                } else {
                    $this->fetchGreyMedalList();
                    $this->metal_lock = $now + 10 * 60 * 60;
                }
            } else {
                $this->metal_lock = $now + 60 * 60;
            }
        }

        if (!empty($this->grey_fans_medals) && $this->next_polish_at <= $now) {
            $this->polishTheMedal();
            $this->next_polish_at = time() + mt_rand(4, 10) * 60;
        }

        if (!empty($this->grey_fans_medals)) {
            return TaskResult::after(max(60, $this->next_polish_at - time()));
        }

        return TaskResult::after(max(60, $this->metal_lock - time()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMedalList(): array
    {
        $medalList = [];
        for ($i = 1; $i <= 100; $i++) {
            $deRaw = $this->fansMedalApi()->panel($i, 50);
            $this->authFailureClassifier->assertNotAuthFailure($deRaw, '点亮徽章: 获取徽章列表时账号未登录');
            if (isset($deRaw['code']) && $deRaw['code']) {
                $this->warning("获取徽章列表失败 => {$deRaw['message']} => {$deRaw['code']}");
            }
            foreach (['list', 'special_list'] as $key) {
                if (isset($deRaw['data'][$key])) {
                    foreach ($deRaw['data'][$key] as $vo) {
                        if (isset($vo['medal']) && is_array($vo['medal'])) {
                            $vo['medal']['roomid'] = isset($vo['room_info']['room_id']) ? $vo['room_info']['room_id'] : 0;
                        }
                        $medalList[] = $vo;
                    }
                }
            }
            if (count($medalList) >= $deRaw['data']['total_number'] || empty($medalList)) {
                break;
            }
        }
        if (!empty($medalList)) {
            $num = count($medalList);
            $this->info("勋章列表获取成功, 共获取到 $num 个!");
        }

        return $medalList;
    }

    private function fetchGreyMedalList(bool $all = false): void
    {
        $this->black_list = ($tmp = $this->cacheGet('black_list', self::CACHE_SCOPE, [])) ? $tmp : [];
        $data = $this->fetchMedalList();
        $invalidMedals = $this->loadInvalidMedals();
        $greyFansMedals = [];
        foreach ($data as $vo) {
            $candidate = $this->normalizeMedalCandidate($vo);
            if ($candidate === null) {
                continue;
            }
            $medal = is_array($vo['medal'] ?? null) ? $vo['medal'] : $vo;
            if (in_array($candidate['roomid'], $this->black_list, true)) {
                continue;
            }
            if ($this->isInvalidMedal($candidate, $invalidMedals)) {
                continue;
            }
            if ($this->cleanupInvalidMedalEnabled() && $candidate['anchor_name'] === '账号已注销') {
                $this->markInvalidMedal($candidate, '账号已注销');
                continue;
            }
            if ($all) {
                $greyFansMedals[] = $candidate;
                continue;
            }
            if (($medal['medal_color_start'] ?? null) == 12632256 && ($medal['medal_color_end'] ?? null) == 12632256 && ($medal['medal_color_border'] ?? null) == 12632256) {
                $greyFansMedals[] = $candidate;
            }
        }
        $this->grey_fans_medals = $greyFansMedals;
        $this->medal_batch_total = count($this->grey_fans_medals);
        if ($this->medal_batch_total > 0) {
            $this->info("点亮徽章: 待处理 {$this->medal_batch_total} 个");
        }
        shuffle($this->grey_fans_medals);
    }

    private function polishTheMedal(): void
    {
        $remainingBeforePop = count($this->grey_fans_medals);
        $medal = array_pop($this->grey_fans_medals);
        if (is_null($medal)) {
            return;
        }
        if (in_array($medal['roomid'], [21686237, 0], true)) {
            return;
        }

        $progress = $this->progressLabel($remainingBeforePop);
        $this->info("开始点亮{$progress}直播间@{$medal['roomid']}的勋章 [{$medal['anchor_name']} / {$medal['medal_name']}]");
        $words = (string)$this->config('polish_medal.reply_words', '');
        $customWord = $words === '' ? Fake::emoji() : ArrayR::toRand(explode(',', $words));
        $res = $this->msgApi()->sendBarrageAPP((int)$medal['roomid'], $customWord);
        $this->authFailureClassifier->assertNotAuthFailure($res, "点亮徽章: 在直播间@{$medal['roomid']}发送弹幕时账号未登录");
        if (isset($res['code']) && $res['code'] == 0) {
            $this->notice("点亮徽章{$progress}: 在直播间@{$medal['roomid']}发送点亮弹幕成功");
        } else {
            $this->triggerException($medal, $res, $progress);
        }
    }

    /**
     * @param array{uid: mixed, roomid: mixed, medal_id: mixed, medal_name: string, anchor_name: string} $medal
     * @param array<string, mixed> $res
     */
    protected function triggerException(array $medal, array $res, string $progress = ''): void
    {
        $this->warning("点亮徽章{$progress}: 在直播间@{$medal['roomid']}发送点亮弹幕失败, CODE -> {$res['code']} MSG -> {$res['message']} ");
        switch ($res['code']) {
            case 1003:
                $this->info("直播间@{$medal['roomid']}已被禁言, 加入黑名单");
                $this->black_list[] = $medal['roomid'];
                $this->cacheSet('black_list', $this->black_list, self::CACHE_SCOPE);
                break;
            case 10033:
                if ($this->cleanupInvalidMedalEnabled()) {
                    $this->markInvalidMedal($medal, '房间已封禁');
                }
                break;
            default:
                break;
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array{uid: mixed, roomid: mixed, medal_id: mixed, medal_name: string, anchor_name: string}|null
     */
    private function normalizeMedalCandidate(array $item): ?array
    {
        $medal = is_array($item['medal'] ?? null) ? $item['medal'] : $item;
        $roomInfo = is_array($item['room_info'] ?? null) ? $item['room_info'] : [];
        $anchorInfo = is_array($item['anchor_info'] ?? null) ? $item['anchor_info'] : [];

        $roomId = (int)($medal['roomid'] ?? $roomInfo['room_id'] ?? 0);
        if ($roomId === 0) {
            return null;
        }

        return [
            'uid' => $medal['target_id'] ?? 0,
            'roomid' => $roomId,
            'medal_id' => $medal['medal_id'] ?? 0,
            'medal_name' => trim((string)($medal['medal_name'] ?? '')),
            'anchor_name' => trim((string)($anchorInfo['nick_name'] ?? $medal['target_name'] ?? '')),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadInvalidMedals(): array
    {
        $invalid = $this->cacheGet(self::INVALID_MEDALS_CACHE_KEY, self::CACHE_SCOPE, []);
        return is_array($invalid) ? $invalid : [];
    }

    /**
     * @param array{uid: mixed, roomid: mixed, medal_id: mixed, medal_name: string, anchor_name: string} $medal
     */
    private function markInvalidMedal(array $medal, string $reason): void
    {
        $invalid = $this->loadInvalidMedals();
        $key = $this->invalidMedalKey($medal);
        $invalid[$key] = [
            'roomid' => (int)$medal['roomid'],
            'uid' => (int)$medal['uid'],
            'medal_id' => (int)$medal['medal_id'],
            'medal_name' => $medal['medal_name'],
            'anchor_name' => $medal['anchor_name'],
            'reason' => $reason,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->cacheSet(self::INVALID_MEDALS_CACHE_KEY, $invalid, self::CACHE_SCOPE);
        $this->notice("点亮徽章: 标记无效勋章 [{$medal['anchor_name']} / {$medal['medal_name']}] -> {$reason}");
    }

    /**
     * @param array{uid: mixed, roomid: mixed, medal_id: mixed, medal_name: string, anchor_name: string} $medal
     * @param array<string, array<string, mixed>> $invalidMedals
     */
    private function isInvalidMedal(array $medal, array $invalidMedals): bool
    {
        return array_key_exists($this->invalidMedalKey($medal), $invalidMedals);
    }

    /**
     * @param array{uid: mixed, roomid: mixed, medal_id: mixed, medal_name: string, anchor_name: string} $medal
     */
    private function invalidMedalKey(array $medal): string
    {
        $medalId = (int)$medal['medal_id'];
        if ($medalId > 0) {
            return 'medal_' . $medalId;
        }

        return 'room_' . (int)$medal['roomid'];
    }

    private function cleanupInvalidMedalEnabled(): bool
    {
        return $this->config('polish_medal.cleanup_invalid_medal', false, 'bool');
    }

    private function progressLabel(int $remainingBeforePop): string
    {
        if ($this->medal_batch_total <= 0) {
            return '';
        }

        $current = max(1, $this->medal_batch_total - $remainingBeforePop + 1);

        return "({$current}/{$this->medal_batch_total}) ";
    }

    private function msgApi(): ApiMsg
    {
        return $this->msgApi ??= new ApiMsg($this->appContext()->request());
    }

    private function fansMedalApi(): ApiFansMedal
    {
        return $this->fansMedalApi ??= new ApiFansMedal($this->appContext()->request());
    }
}
