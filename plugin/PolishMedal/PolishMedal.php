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
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\Util\ArrayR\ArrayR;
use Bhp\Util\Fake\Fake;

class PolishMedal extends BasePlugin
{

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

    private static array $grey_fans_medals = []; // 灰色勋章
    private static int $metal_lock = 0; // 勋章时间锁

    /**
     * @var array
     */
    private static array $black_list = []; // 黑名单

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        // 时间锁
        TimeLock::initTimeLock();
        //
        Cache::initCache();
        // 缓存
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('polish_medal')) return;

        if (self::$metal_lock < time()) {
            // 如果勋章过多导致未处理完，就1小时一次，否则10小时一次。
            if (empty(self::$grey_fans_medals)) {
                // 处理每日
                if (getConf('polish_medal.everyday', false, 'bool')) {
                    // 如果是 直接定时到第二天7点
                    self::fetchGreyMedalList(true);
                    self::$metal_lock = time() + TimeLock::timing(7, 0, 0, true);
                } else {
                    // 否则按正常逻辑
                    self::fetchGreyMedalList();
                    self::$metal_lock = time() + 10 * 60 * 60; // 10小时
                }
            } else {
                self::$metal_lock = time() + 60 * 60; // 1小时一次
            }
        }
        // 点亮灰色勋章
        if (TimeLock::getTimes() < time()) {
            // 随机4-10分钟处理一次点亮操作
            self::polishTheMedal();
            TimeLock::setTimes(mt_rand(4, 10) * 60);
        }
    }

    /**
     * 获取徽章列表
     * @return array
     */
    private static function fetchMedalList(): array
    {
        $medalList = [];
        for ($i = 1; $i <= 100; $i++) {
            $de_raw = ApiFansMedal::panel($i, 50);
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
    private static function fetchGreyMedalList(bool $all = false): void
    {
        self::$black_list = ($tmp = Cache::get('black_list')) ? $tmp : [];
        // 获取徽章列表
        $data = self::fetchMedalList();
        foreach ($data as $vo) {
            // 过滤主站勋章
            if (!isset($vo['roomid']) || $vo['roomid'] == 0) continue;
            // 过滤黑名单
            if (in_array($vo['roomid'], self::$black_list)) continue;
            // 如果是每天擦亮 ，就不过滤|否则过滤掉，只点亮灰色
            if ($all) {
                self::$grey_fans_medals[] = [
                    'uid' => $vo['target_id'],
                    'roomid' => $vo['roomid'],
                ];
            } else {
                //  灰色
                if ($vo['medal_color_start'] == 12632256 && $vo['medal_color_end'] == 12632256 && $vo['medal_color_border'] == 12632256) {
                    self::$grey_fans_medals[] = [
                        'uid' => $vo['target_id'],
                        'roomid' => $vo['roomid'],
                    ];
                }
            }
        }
        // 乱序
        shuffle(self::$grey_fans_medals);
    }


    /**
     * 点亮徽章
     * @return void
     */
    private static function polishTheMedal(): void
    {
        $medal = array_pop(self::$grey_fans_medals);
        // 为空
        if (is_null($medal)) return;
        // 特殊房间处理|央视未开播|CODE -> 11000 MSG -> ''
        if (in_array($medal['roomid'], [21686237, 0])) return;

        Log::info("开始点亮直播间@{$medal['roomid']}的勋章");
        // 擦亮
        $custom_word = empty($words = getConf('polish_medal.reply_words')) ? Fake::emoji() : ArrayR::toRand(explode(',', $words));
        $res = ApiMsg::sendBarrageAPP($medal['roomid'], $custom_word);
        if (isset($res['code']) && $res['code'] == 0) {
            Log::notice("在直播间@{$medal['roomid']}发送点亮弹幕成功");
        } else {
            self::triggerException($medal, $res);
        }
    }

    protected static function triggerException(array $medal, array $res): void
    {
        Log::warning("在直播间@{$medal['roomid']}发送点亮弹幕失败, CODE -> {$res['code']} MSG -> {$res['message']} ");
        //
        switch ($res['code']) {
            case 1003:
                Log::info("直播间@{$medal['roomid']}已被禁言, 加入黑名单");
                self::$black_list[] = $medal['roomid'];
                Cache::set('black_list', self::$black_list);
                break;
            default:
                break;
        }


    }
}
