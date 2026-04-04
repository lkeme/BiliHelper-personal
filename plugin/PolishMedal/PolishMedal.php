<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Api\Msg\ApiMsg;
use Bhp\Api\XLive\AppUcenter\V1\ApiFansMedal;
use Bhp\Cache\Cache;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\ArrayR\ArrayR;
use Bhp\Util\Fake\Fake;

class PolishMedal extends BasePlugin implements PluginTaskInterface
{
    private AuthFailureClassifier $authFailureClassifier;

    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'PolishMedal', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '点亮徽章', // 插件描述
        'author' => 'possible318/Lkeme',// 作者
        'priority' => 1115, // 插件优先级
        'cycle' => '1(小时)', // 运行周期
    ];

    private array $grey_fans_medals = []; // 灰色勋章
    private int $metal_lock = 0; // 勋章时间锁
    private int $next_polish_at = 0;

    /**
     * @var array
     */
    private array $black_list = []; // 黑名单

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        Cache::initCache();
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
     * 获取徽章列表
     * @return array
     */
    private function fetchMedalList(): array
    {
        $medalList = [];
        for ($i = 1; $i <= 100; $i++) {
            $de_raw = ApiFansMedal::panel($i, 50);
            $this->authFailureClassifier->assertNotAuthFailure($de_raw, '点亮徽章: 获取徽章列表时账号未登录');
            if (isset($de_raw['code']) && $de_raw['code']) {
                Log::warning("获取徽章列表失败 => {$de_raw['message']} => {$de_raw['code']}");
            }
            $keys = ['list', 'special_list'];
            foreach ($keys as $key) {
                if (isset($de_raw['data'][$key])) {
                    foreach ($de_raw['data'][$key] as $vo) {
                        // 部分主站勋章没有直播间
                        if (isset($vo['room_info']['room_id'])) {
                            $vo['medal']['roomid'] = $vo['room_info']['room_id'];
                        } else {
                            $vo['medal']['roomid'] = 0;
                        }
                        $medalList[] = $vo['medal'];
                    }
                }
            }
            // total_number || count == 0
            if (count($medalList) >= $de_raw['data']['total_number'] || empty($medalList)) {
                break;
            }
        }
        // count == 0
        if (!empty($medalList)) {
            $num = count($medalList);
            Log::info("勋章列表获取成功, 共获取到 $num 个!");
        }
        return $medalList;
    }


    /**
     * 获取熄灭徽章
     * @param bool $all
     */
    private function fetchGreyMedalList(bool $all = false): void
    {
        $this->black_list = ($tmp = Cache::get('black_list')) ? $tmp : [];
        // 获取徽章列表
        $data = $this->fetchMedalList();
        foreach ($data as $vo) {
            // 过滤主站勋章
            if (!isset($vo['roomid']) || $vo['roomid'] == 0) continue;
            // 过滤黑名单
            if (in_array($vo['roomid'], $this->black_list, true)) continue;
            // 如果是每天擦亮 ，就不过滤|否则过滤掉，只点亮灰色
            if ($all) {
                $this->grey_fans_medals[] = [
                    'uid' => $vo['target_id'],
                    'roomid' => $vo['roomid'],
                ];
            } else {
                //  灰色
                if ($vo['medal_color_start'] == 12632256 && $vo['medal_color_end'] == 12632256 && $vo['medal_color_border'] == 12632256) {
                    $this->grey_fans_medals[] = [
                        'uid' => $vo['target_id'],
                        'roomid' => $vo['roomid'],
                    ];
                }
            }
        }
        // 乱序
        shuffle($this->grey_fans_medals);
    }


    /**
     * 点亮徽章
     * @return void
     */
    private function polishTheMedal(): void
    {
        $medal = array_pop($this->grey_fans_medals);
        // 为空
        if (is_null($medal)) return;
        // 特殊房间处理|央视未开播|CODE -> 11000 MSG -> ''
        if (in_array($medal['roomid'], [21686237, 0])) return;

        Log::info("开始点亮直播间@{$medal['roomid']}的勋章");
        // 擦亮
        $custom_word = empty($words = (string)$this->config('polish_medal.reply_words', '')) ? Fake::emoji() : ArrayR::toRand(explode(',', $words));
        $res = ApiMsg::sendBarrageAPP($medal['roomid'], $custom_word);
        $this->authFailureClassifier->assertNotAuthFailure($res, "点亮徽章: 在直播间@{$medal['roomid']}发送弹幕时账号未登录");
        if (isset($res['code']) && $res['code'] == 0) {
            Log::notice("在直播间@{$medal['roomid']}发送点亮弹幕成功");
        } else {
            $this->triggerException($medal, $res);
        }
    }

    protected function triggerException(array $medal, array $res): void
    {
        Log::warning("在直播间@{$medal['roomid']}发送点亮弹幕失败, CODE -> {$res['code']} MSG -> {$res['message']} ");
        //
        switch ($res['code']) {
            case 1003:
                Log::info("直播间@{$medal['roomid']}已被禁言, 加入黑名单");
                $this->black_list[] = $medal['roomid'];
                Cache::set('black_list', $this->black_list);
                break;
            default:
                break;
        }


    }
}
