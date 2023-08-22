<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Api\Manga\ApiManga;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\Util\Exceptions\NoLoginException;

class Manga extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'Manga', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '漫画签到/分享', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1101, // 插件优先级
        'cycle' => '24(小时)', // 运行周期
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        //
        TimeLock::initTimeLock();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     * @throws NoLoginException
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('manga')) return;
        //
        if ($this->shareTask() && $this->signInTask()) {
            TimeLock::setTimes(TimeLock::timing(10));
        } else {
            TimeLock::setTimes(3600);
        }
    }

    /**
     * 签到任务
     * @return bool
     */
    protected function signInTask(): bool
    {
        // {"code":0,"msg":"","data":{}}
        // {"code":"invalid_argument","msg":"clockin clockin is duplicate","meta":{"argument":"clockin"}}
        $response = ApiManga::ClockIn();
        //
        switch ($response['code']) {
            case 0:
                Log::notice('漫画: 签到成功');
                break;
            case 'invalid_argument':
                Log::notice('漫画: 今日已经签到过了哦~');
                break;
            default:
                Log::warning("漫画: 签到失败 {$response['code']} -> {$response['msg']}");
                return false;
        }
        $this->signInInfo();
        return true;
    }

    /**
     * 分享任务
     * @return bool
     * @throws NoLoginException
     */
    protected function shareTask(): bool
    {
        // {"code":0,"msg":"","data":{"point":5}}
        // {"code":1,"msg":"","data":{"point":0}}
        $response = ApiManga::ShareComic();
        //
        switch ($response['code']) {
            case 0:
                if ($response['msg'] == '今日已分享') {
                    Log::notice('漫画: 今日已经分享过了哦~');
                } else {
                    Log::notice("漫画: 分享成功，经验值+{$response['data']['point']}");
                }
                break;
            case 'unauthenticated':
                throw new NoLoginException($response['message']);
            default:
                Log::warning("漫画: 分享失败 {$response['code']} -> {$response['msg']}");
                return false;
        }
        return true;
    }

    /**
     * 签到信息
     * @return void
     */
    protected function signInInfo(): void
    {
        $response = ApiManga::GetClockInInfo();
        if ($response['code']) {
            Log::warning("漫画: 获取签到信息失败 {$response['code']} -> {$response['msg']}");
        } else {
            Log::notice("漫画: 已连续签到 {$response['data']['day_count']} 天，继续加油哦~");
        }
    }


}
