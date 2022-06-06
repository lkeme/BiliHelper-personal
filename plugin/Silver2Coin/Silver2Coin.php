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

use Bhp\Api\XLive\ApiRevenueWallet;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class Silver2Coin extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    protected ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'Silver2Coin', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '银瓜子兑换硬币', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1105, // 插件优先级
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
     * @use 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('silver2coin')) return;
        //
        if ($this->exchangeTask()) {
            // 定时10点 + 1-60分钟随机
            TimeLock::setTimes(TimeLock::timing(10, 0, 0, true));
        } else {
            TimeLock::setTimes(3600);
        }
    }

    /**
     * @use 兑换任务
     * @return bool
     */
    protected function exchangeTask(): bool
    {
        //
        $response = ApiRevenueWallet::appSilver2coin();
        if ($this->handle('APP', $response)) return true;
        //
        $response = ApiRevenueWallet::appSilver2coin();
        if ($this->handle('PC', $response)) return true;
        //
        return false;
    }

    /**
     * @use 处理结果
     * @param string $type
     * @param array $data
     * @return bool
     */
    protected function handle(string $type, array $data): bool
    {
        // {"code":403,"msg":"每天最多能兑换 1 个","message":"每天最多能兑换 1 个","data":[]}
        // {"code":403,"msg":"仅主站正式会员以上的用户可以兑换","message":"仅主站正式会员以上的用户可以兑换","data":[]}
        // {"code":0,"msg":"兑换成功","message":"兑换成功","data":{"gold":"5074","silver":"36734","tid":"727ab65376a15a6b117cf560a20a21122334","coin":1}}
        // {"code":0,"data":{"coin":1,"gold":1234,"silver":4321,"tid":"Silver2Coin21062316490299678123456"},"message":"兑换成功"}
        switch ($data['code']) {
            case 0:
                Log::notice("银瓜子兑换硬币[$type]: {$data['message']}");
                return true;
            case 403:
                Log::warning("银瓜子兑换硬币[$type]: {$data['message']}");
                return true;
            default:
                Log::warning("银瓜子兑换硬币[$type]: CODE -> {$data['code']} MSG -> {$data['message']} ");
        }
        return false;
    }


}
 