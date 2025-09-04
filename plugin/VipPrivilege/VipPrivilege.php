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

use Bhp\Api\Vip\ApiExperience;
use Bhp\Api\Vip\ApiPrivilegeAssets;
use Bhp\Api\Vip\ApiVipCenter;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;
use Bhp\User\User;
use Bhp\Util\Exceptions\NoLoginException;
use function Amp\delay;

class VipPrivilege extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'VipPrivilege', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '领取大会员权益', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1107, // 插件优先级
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
        if (TimeLock::getTimes() > time() || !getEnable('vip_privilege')) return;
        //
        $this->receiveTask();
        //
        // 定时23点 + 随机10-30分钟
        TimeLock::setTimes(TimeLock::timing(23) + mt_rand(10, 30) * 60);
    }


    /**
     * 领取
     * @return void
     * @throws NoLoginException
     */
    protected function receiveTask(): void
    {
        // 如果为年度大会员
        if (!User::isYearVip('大会员权益')) return;
        //
        $privilege_list = array_merge($this->vipExtraEx(), $this->filterCanReceive());
        if (empty($privilege_list)) {
            Log::info('大会员权益: 当前无可领取权益');
            return;
        }

        Log::info('大会员权益: 可领取权益数 ' . count($privilege_list));
        //
        foreach ($privilege_list as $privilege) {
            // 随机延迟 5-10秒
            delay(mt_rand(5, 10));
            // 特殊类型 9 每日10经验 需要观看视频
            if ($privilege['type'] == 9) {
                // 领取额外经验
                $this->extraExp();
                continue;
            }
            // 领取奖励
            $this->privilegeAssetReceive($privilege);
        }
    }

    /**
     * 大会员额外权益
     * @return array|array[]
     */
    protected function vipExtraEx(): array
    {
        $response = ApiVipCenter::v2();
        //
        if ($response['code']) {
            Log::warning("大会员权益: 获取大会员额外经验领取状态失败 {$response['code']} -> {$response['message']}");
            return [];
        }
        //data.experience.state 0-未领取 1-已领取
        if (isset($response['data']['experience']['state']) && $response['data']['experience']['state'] == 0) {
            return [
                [
                    'type' => 9,
                    'title' => '专属等级加速包',
                    'token' => '',
                    'state' => 0,
                    'customized_text' => '每日10经验',
                ]
            ];
        }
        return [];
    }


    /**
     * 过滤可领取的权益
     * @return array
     */
    protected function filterCanReceive(): array
    {
        $response = ApiPrivilegeAssets::list();
        // 请求失败
        if ($response['code']) {
            Log::warning("大会员权益: 获取APP端权益列表失败 {$response['code']} -> {$response['message']}");
            return [];
        }
        // 过滤tabs
        $tab = array_filter($response['data']['tabs'], function ($tab) {
            return $tab['name'] == '站内福利' && $tab['type'] == 1 && $tab['type_code'] == 'welfare';
        });
        if (empty($tab)) {
            Log::warning("大会员权益: 获取APP端权益列表失败，未找到站内福利");
            return [];
        }
        $tab = array_values($tab)[0];
        /// 遍历groups
        $privilege_list = [];
        foreach ($tab['groups'] as $group) {
            // 跳过年度专享游戏礼包
            if (isset($group['title']) && $group['title'] === '年度专享游戏礼包') {
                continue;
            }
            // 特色权益二选一，只选B币券
            if (isset($group['title']) && $group['title'] === '特色权益二选一') {
                foreach ($group['privilege_skus'] as $sku) {
                    if ($sku['title'] === 'B币券' && isset($sku['exchange']['can_exchange'], $sku['exchange']['hit_exchange_limit']) && $sku['exchange']['can_exchange'] && !$sku['exchange']['hit_exchange_limit']) {
                        $customized_text = $this->getCustomizedText($sku);
                        $privilege_list[] = [
                            'type' => $sku['type'],
                            'title' => $sku['title'],
                            'token' => $sku['token'],
                            'state' => $sku['exchange']['state'] ?? 0,
                            'customized_text' => $customized_text,
                        ];
                        break; // 只选一个
                    }
                }
                continue;
            }
            // 其他group，遍历privilege_skus
            foreach ($group['privilege_skus'] as $sku) {
                if (isset($sku['exchange']['can_exchange'], $sku['exchange']['hit_exchange_limit']) && $sku['exchange']['can_exchange'] && !$sku['exchange']['hit_exchange_limit']) {
                    $customized_text = $this->getCustomizedText($sku);
                    $privilege_list[] = [
                        'type' => $sku['type'],
                        'title' => $sku['title'],
                        'token' => $sku['token'],
                        'state' => $sku['exchange']['state'] ?? 0,
                        'customized_text' => $customized_text,
                    ];
                }
            }
        }
        //
        return $privilege_list;
    }


    /**
     * 大会员额外经验
     * @return void
     */
    protected function extraExp(): void
    {
        $response = ApiExperience::add();
        //
        if (!$response['code']) {
            Log::notice("大会员额外经验: 领取额外经验成功");
        } else if ($response['code'] == 69198) {
            Log::info("大会员额外经验: 用户经验已经领取");
        } else {
            Log::warning("大会员额外经验: 领取额外经验失败  {$response['code']} -> {$response['message']}");
        }
    }


    /**
     * 领取大会员权益
     * @param array $asset
     * @throws NoLoginException
     */
    protected function privilegeAssetReceive(array $asset): void
    {
        $response = ApiPrivilegeAssets::exchange($asset['token']);
        //
        switch ($response['code']) {
            case -101:
                throw new NoLoginException($response['message']);
            case 0:
                Log::notice("大会员权益: 领取权益[{$asset['title']} * {$asset['customized_text']}]成功");
                break;
            default:
                Log::warning("大会员权益: 领取权益[{$asset['title']} * {$asset['customized_text']}]失败 {$response['code']} -> {$response['message']}");
                break;
        }
    }

    /**
     * 获取并格式化权益自定义文本
     * @param array $sku
     * @return string
     */
    protected function getCustomizedText(array $sku): string
    {
        $customized = $sku['icon']['customized'] ?? [];
        if (empty($customized)) return '';
        return ($customized['number'] ?? '') . ($customized['currency_symbol'] ?? '') . ($customized['unit'] ?? '') . ($customized['logo_text'] ?? '');
    }
}
