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


use Bhp\Api\Api\X\VipPoint\ApiTask;
use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\User\User;
use Bhp\Util\AppTerminator;
use Bhp\Util\Loader\DirectoryLoader;

include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Traits' . DIRECTORY_SEPARATOR . 'CommonTaskInfo.php');
DirectoryLoader::requireDir(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Traits' . DIRECTORY_SEPARATOR, ['CommonTaskInfo.php']);

class VipPoint extends BasePlugin implements PluginTaskInterface
{
    use SignIn;
    use Bonus;
    use Privilege;
    use ViewAnimate;
    use ViewFilmChannel;
    use ViewVipMall;
    use ViewVideo;
    use BuyVipVideo;
    use BuyVipProduct;
    use BuyVipMall;
    use PointInfo;
    use DressView;
    use DressBuyAmount;

    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'VipPoint', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '大会员积分', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1112, // 插件优先级
        'cycle' => '5(分钟)', // 运行周期
    ];

    /**
     * @var string
     */
    protected string $home_task = 'https://big.bilibili.com/mobile/bigPoint/task';

    /**
     * @var string
     */
    protected string $home = 'https://big.bilibili.com/mobile/bigPoint';

    /**
     * @var string
     */
    protected string $title = '大会员积分';

    /**
     * @var array|null
     */
    protected ?array $tasks = [];

    /**
     * 目标任务
     * @var array|string[]
     */
    protected array $target_tasks = [
//        'dressUp' => '使用免费体验装扮权益',
//        'tvodbuy' => '购买单点付费影片',
//        'ogvwatchnew' => '观看剧集内容',
//        'offlinetask' => '购买联动线下门店商品',
//        'vipmallbuy'=>'购买指定会员购商品',

        'signIn' => '每日签到',
        'bonus' => '大会员福利大积分',
        'privilege' => '浏览大会员权益页面',
        'viewAnimate' => '浏览追番频道页10秒',
        'viewFilmChannel' => '浏览影视频道页10秒',
        'viewVipMall' => '浏览会员购页面10秒',
        'viewVideo' => '观看任意正片内容',
        'buyVipVideo' => '购买单点付费影片',
        'buyVipProduct' => '购买指定大会员产品',
        'buyVipMall' => '购买指定会员购商品',
        'dressView' => '浏览装扮商城主页',
        'dressBuyAmount' => '购买装扮',
        'pointInfo' => '积分信息'
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        Cache::initCache();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('vip_point')) {
            return TaskResult::keepSchedule();
        }

        $this->resetTaskResult();
        $this->initTask();

        if (!User::isVip($this->title)) {
            return TaskResult::nextAt(9);
        }

        if (!$this->getTaskList()) {
            return TaskResult::after(10 * 60);
        }

        foreach ($this->target_tasks as $target => $value) {
            if ($this->getTask($target)) {
                continue;
            }

            $this->executeTask($target, $value);

            return $this->resolveTaskResult(TaskResult::after(5 * 60));
        }

        return TaskResult::nextAt(9);
    }

    /**
     * @return void
     */
    /**
     * 动态执行任务
     * @param string $target
     * @param string $name
     * @return void
     */
    protected function executeTask(string $target, string $name): void
    {
        $response = $this->getTask('TaskList');
        //
        if (!method_exists($this, $target)) {
            AppTerminator::fail("VipPoint 不存在{$target}方法 请暂时关闭任务检查代码或通知开发者");
        }
        //
        $this->setTask($target, $this->$target($response, $name));
    }

    /**
     * @return void
     */
    protected function initTask(): void
    {
        $now = date("Y-m-d");
        if (!isset($this->tasks[$now])) {
            $this->setTask('start', true);
            $this->setTask('DelayedAction', null);
            foreach ($this->target_tasks as $target => $_) {
                $this->setTask($target, false);
            }
            return;
        }

        if (!array_key_exists('DelayedAction', $this->tasks[$now])) {
            $this->setTask('DelayedAction', null);
        }
    }

    /**
     * @return bool
     */
    protected function getTaskList(): bool
    {
        $response = ApiTask::combine();
        if ($response['code']) {
            Log::warning('大会员积分: 获取任务列表失败');
            return false;
        }
        // 设置任务
        $this->setTask('TaskList', $response);
        return true;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setTask(string $key, mixed $value): void
    {
        $now = date("Y-m-d");
        //
        $this->tasks[$now][$key] = $value;
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected function getTask(string $key): mixed
    {
        $now = date("Y-m-d");
        //
        return $this->tasks[$now][$key];
    }

    /**
     * @param string $key
     * @return void
     */
    protected function delTask(string $key): void
    {
        $now = date("Y-m-d");
        //
        unset($this->tasks[$now][$key]);
    }


}
