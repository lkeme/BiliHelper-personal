<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */


use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\User\User;

include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Traits' . DIRECTORY_SEPARATOR . 'CommonTaskInfo.php');
requireDir(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Traits' . DIRECTORY_SEPARATOR, ['CommonTaskInfo.php']);

class VipPoint extends BasePlugin
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

    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
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
        'signIn' => '签到任务',
        'bonus' => '福利任务',
        'privilege' => '体验任务',
        'viewAnimate' => '浏览追番频道页10秒',
        'viewFilmChannel' => '浏览影视频道页10秒',
        'viewVipMall' => '浏览会员购页面10秒',
        'viewVideo' => '观看任意正片内容',
        'buyVipVideo' => '购买单点付费影片',
        'buyVipProduct' => '购买指定大会员产品',
        'buyVipMall' => '购买指定会员购商品',
        'pointInfo' => '已有积分'
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        // 时间锁
        TimeLock::initTimeLock();
        // 缓存
        Cache::initCache();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('vip_point')) return;
        //
        $this->initTask();
        //
        $this->receiveTask();
        // 全部完成
        if (TimeLock::getTimes() <= time()) {
            TimeLock::setTimes(TimeLock::timing(9));
        }
    }

    /**
     * @return void
     */
    protected function receiveTask(): void
    {
        // 如果为大会员
        if (!User::isVip($this->title)) return;
        // 获取远程任务列表
        if (!$this->getTaskList()) {
            TimeLock::setTimes(10 * 60);
            return;
        };
        //
        foreach ($this->target_tasks as $target => $value) {
            // 任务完成跳过
            if ($this->getTask($target)) continue;
            // 未完成执行
            $this->executeTask($target, $value);
            //
            TimeLock::setTimes(5 * 60);
            // 每次执行一个任务
            return;
        }
    }

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
            failExit("VipPoint 不存在{$target}方法 请暂时关闭任务检查代码或通知开发者");
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
        if (isset($this->tasks[$now])) return;
        //
        $this->setTask('start', true);
        //
        foreach ($this->target_tasks as $target => $_) {
            $this->setTask($target, false);
        }
    }

    /**
     * @return bool
     */
    protected function getTaskList(): bool
    {
        $response = \Bhp\Api\Api\X\VipPoint\ApiTask::combine();
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
