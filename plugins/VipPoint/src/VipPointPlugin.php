<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint;

use Bhp\Api\Api\Pgc\Activity\Deliver\ApiTask as VipPointDeliverApiTask;
use Bhp\Api\Api\Pgc\Activity\Score\ApiTask as VipPointScoreApiTask;
use Bhp\Api\Api\X\VipPoint\ApiTask;
use Bhp\Api\Show\Api\Activity\Fire\Common\ApiEvent;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Builtin\VipPoint\Traits\Bonus;
use Bhp\Plugin\Builtin\VipPoint\Traits\BuyVipMall;
use Bhp\Plugin\Builtin\VipPoint\Traits\BuyVipProduct;
use Bhp\Plugin\Builtin\VipPoint\Traits\BuyVipVideo;
use Bhp\Plugin\Builtin\VipPoint\Traits\DressBuyAmount;
use Bhp\Plugin\Builtin\VipPoint\Traits\DressView;
use Bhp\Plugin\Builtin\VipPoint\Traits\PointInfo;
use Bhp\Plugin\Builtin\VipPoint\Traits\Privilege;
use Bhp\Plugin\Builtin\VipPoint\Traits\SignIn;
use Bhp\Plugin\Builtin\VipPoint\Traits\ViewAnimate;
use Bhp\Plugin\Builtin\VipPoint\Traits\ViewFilmChannel;
use Bhp\Plugin\Builtin\VipPoint\Traits\ViewVideo;
use Bhp\Plugin\Builtin\VipPoint\Traits\ViewVipMall;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\AppTerminator;

class VipPointPlugin extends BasePlugin implements PluginTaskInterface
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
     * @var array<string, int|string>
     */
    public ?array $info = [
        'hook' => 'VipPoint',
        'name' => 'VipPoint',
        'version' => '0.0.1',
        'desc' => '大会员积分',
        'author' => 'Lkeme',
        'priority' => 1112,
        'cycle' => '5(分钟)',
    ];

    protected string $home_task = 'https://big.bilibili.com/mobile/bigPoint/task';

    protected string $home = 'https://big.bilibili.com/mobile/bigPoint';

    protected string $title = '大会员积分';

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $tasks = [];

    private ?VipPointRuntimeState $runtimeState = null;
    private ?ApiTask $vipPointTaskApi = null;
    private ?VipPointScoreApiTask $vipPointScoreTaskApi = null;
    private ?VipPointDeliverApiTask $vipPointDeliverTaskApi = null;
    private ?ApiEvent $vipPointEventApi = null;

    private AuthFailureClassifier $authFailureClassifier;

    /**
     * @var array<string, string>
     */
    protected array $targetTasks = [
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
        'pointInfo' => '积分信息',
    ];

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('vip_point')) {
            return TaskResult::keepSchedule();
        }

        $this->resetTaskResult();
        $this->initTask();

        if (!$this->userProfiles()->isVip($this->title)) {
            return TaskResult::nextAt(9);
        }

        if (!$this->getTaskList()) {
            return TaskResult::after(10 * 60);
        }

        foreach ($this->targetTasks as $target => $label) {
            if ($this->getTask($target)) {
                continue;
            }

            $this->executeTask($target, $label);

            return $this->resolveTaskResult(TaskResult::after(5 * 60));
        }

        return TaskResult::nextAt(9);
    }

    protected function executeTask(string $target, string $name): void
    {
        $response = $this->getTask('TaskList');

        if (!method_exists($this, $target)) {
            AppTerminator::fail("VipPoint 不存在{$target}方法 请暂时关闭任务检查代码或通知开发者");
        }

        $this->setTask($target, $this->$target($response, $name));
    }

    protected function initTask(): void
    {
        $date = date('Y-m-d');
        $this->runtimeState = VipPointRuntimeState::bootstrap(
            is_array($this->tasks) ? $this->tasks : [],
            $date,
            $this->targetTasks,
        );

        $tasks = $this->runtimeState->all();
        if (!array_key_exists('DelayedAction', $tasks[$date])) {
            $this->runtimeState->setTask('DelayedAction', null);
            $tasks = $this->runtimeState->all();
        }

        $this->tasks = $tasks;
    }

    protected function getTaskList(): bool
    {
        $response = $this->vipPointTaskApi()->combine();
        $this->authFailureClassifier->assertNotAuthFailure($response, '大会员积分: 获取任务列表时账号未登录');
        if ($response['code']) {
            $this->warning('大会员积分: 获取任务列表失败');

            return false;
        }

        $this->setTask('TaskList', $response);

        return true;
    }

    protected function setTask(string $key, mixed $value): void
    {
        $state = $this->state();
        $state->setTask($key, $value);
        $this->tasks = $state->all();
    }

    protected function getTask(string $key): mixed
    {
        return $this->state()->task($key);
    }

    protected function delTask(string $key): void
    {
        $state = $this->state();
        $state->deleteTask($key);
        $this->tasks = $state->all();
    }

    private function state(): VipPointRuntimeState
    {
        if (!$this->runtimeState instanceof VipPointRuntimeState) {
            $this->initTask();
        }

        return $this->runtimeState;
    }

    protected function vipPointTaskApi(): ApiTask
    {
        return $this->vipPointTaskApi ??= new ApiTask($this->appContext()->request());
    }

    protected function vipPointScoreTaskApi(): VipPointScoreApiTask
    {
        return $this->vipPointScoreTaskApi ??= new VipPointScoreApiTask($this->appContext()->request());
    }

    protected function vipPointDeliverTaskApi(): VipPointDeliverApiTask
    {
        return $this->vipPointDeliverTaskApi ??= new VipPointDeliverApiTask($this->appContext()->request());
    }

    protected function vipPointEventApi(): ApiEvent
    {
        return $this->vipPointEventApi ??= new ApiEvent($this->appContext()->request());
    }
}
