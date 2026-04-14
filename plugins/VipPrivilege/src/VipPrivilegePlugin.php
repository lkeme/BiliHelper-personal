<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPrivilege;

use Bhp\Api\Vip\ApiExperience;
use Bhp\Api\Vip\ApiPrivilegeAssets;
use Bhp\Api\Vip\ApiVipCenter;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\NoLoginException;

class VipPrivilegePlugin extends BasePlugin implements PluginTaskInterface
{
    private const CACHE_SCOPE = 'VipPrivilege';
    private const CACHE_KEY = 'pending_privileges';
    private const CACHE_DATE_KEY = 'pending_privileges_date';
    private const HANDLED_CACHE_KEY = 'handled_privileges';
    private const HANDLED_CACHE_DATE_KEY = 'handled_privileges_date';
    private const NON_FATAL_EXCHANGE_CODES = [6034024];
    private ?ApiVipCenter $vipCenterApi = null;
    private ?ApiPrivilegeAssets $privilegeAssetsApi = null;
    private ?ApiExperience $experienceApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    /**
     * 初始化 VipPrivilegePlugin
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    /**
     * 执行一次任务
     * @return TaskResult
     */
    public function runOnce(): TaskResult
    {
        if (!$this->enabled('vip_privilege')) {
            return TaskResult::keepSchedule();
        }

        $this->resetTaskResult();
        $this->receiveTask();

        return $this->resolveTaskResult(TaskResult::nextDayAt(23, 0, 0, 10, 30));
    }

    /**
     * @throws NoLoginException
     */
    protected function receiveTask(): void
    {
        if (!$this->userProfiles()->isYearVip('大会员权益')) {
            return;
        }

        $privilegeList = $this->loadPendingPrivileges();
        if ($privilegeList === []) {
            $privilegeList = array_merge($this->vipExtraEx(), $this->filterCanReceive());
            $this->savePendingPrivileges($privilegeList);
        }

        if (empty($privilegeList)) {
            $this->info('大会员权益: 当前无可领取权益');

            return;
        }

        $this->info('大会员权益: 待领取权益数 ' . count($privilegeList));
        $privilege = $privilegeList[0] ?? null;
        if (!is_array($privilege)) {
            $this->clearPendingPrivileges();

            return;
        }

        $handled = ($privilege['type'] ?? 0) == 9
            ? $this->extraExp()
            : $this->privilegeAssetReceive($privilege);

        if ($handled) {
            array_shift($privilegeList);
        }

        if ($privilegeList === []) {
            $this->clearPendingPrivileges();
        } else {
            $this->savePendingPrivileges($privilegeList);
            $this->scheduleAfter((float)mt_rand(5, 10), 'continue vip privilege queue');
        }
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    protected function vipExtraEx(): array
    {
        $response = $this->vipCenterApi()->v2();
        if ($response['code']) {
            $this->warning("大会员权益: 获取大会员额外经验领取状态失败 {$response['code']} -> {$response['message']}");

            return [];
        }
        if (isset($response['data']['experience']['state']) && $response['data']['experience']['state'] == 0) {
            return [[
                'type' => 9,
                'title' => '专属等级加速包',
                'token' => '',
                'state' => 0,
                'customized_text' => '每日10经验',
            ]];
        }

        return [];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    protected function filterCanReceive(): array
    {
        $handledTokens = $this->loadHandledPrivilegeTokens();
        $response = $this->privilegeAssetsApi()->list();
        if ($response['code']) {
            $this->warning("大会员权益: 获取APP端权益列表失败 {$response['code']} -> {$response['message']}");

            return [];
        }
        $tab = array_filter($response['data']['tabs'], function ($tab) {
            return $tab['name'] == '站内福利' && $tab['type'] == 1 && $tab['type_code'] == 'welfare';
        });
        if (empty($tab)) {
            $this->warning('大会员权益: 获取APP端权益列表失败，未找到站内福利');

            return [];
        }
        $tab = array_values($tab)[0];
        $privilegeList = [];
        foreach ($tab['groups'] as $group) {
            if (isset($group['title']) && $group['title'] === '年度专享游戏礼包') {
                continue;
            }
            if (isset($group['title']) && $group['title'] === '特色权益二选一') {
                foreach ($group['privilege_skus'] as $sku) {
                    if ($sku['title'] === 'B币券' && isset($sku['exchange']['can_exchange'], $sku['exchange']['hit_exchange_limit']) && $sku['exchange']['can_exchange'] && !$sku['exchange']['hit_exchange_limit']) {
                        if ($this->isHandledPrivilegeToken((string)$sku['token'], $handledTokens)) {
                            continue;
                        }
                        $privilegeList[] = [
                            'type' => $sku['type'],
                            'title' => $sku['title'],
                            'token' => $sku['token'],
                            'state' => $sku['exchange']['state'] ?? 0,
                            'customized_text' => $this->getCustomizedText($sku),
                        ];
                        break;
                    }
                }
                continue;
            }
            foreach ($group['privilege_skus'] as $sku) {
                if (isset($sku['exchange']['can_exchange'], $sku['exchange']['hit_exchange_limit']) && $sku['exchange']['can_exchange'] && !$sku['exchange']['hit_exchange_limit']) {
                    if ($this->isHandledPrivilegeToken((string)$sku['token'], $handledTokens)) {
                        continue;
                    }
                    $privilegeList[] = [
                        'type' => $sku['type'],
                        'title' => $sku['title'],
                        'token' => $sku['token'],
                        'state' => $sku['exchange']['state'] ?? 0,
                        'customized_text' => $this->getCustomizedText($sku),
                    ];
                }
            }
        }

        return $privilegeList;
    }

    /**
     * 处理extraExp
     * @return bool
     */
    protected function extraExp(): bool
    {
        $response = $this->experienceApi()->add();
        if (!$response['code']) {
            $this->notice('大会员额外经验: 领取额外经验成功');
            return true;
        } elseif ($response['code'] == 69198) {
            $this->info('大会员额外经验: 用户经验已经领取');
            return true;
        } else {
            $this->warning("大会员额外经验: 领取额外经验失败  {$response['code']} -> {$response['message']}");
            return false;
        }
    }

    /**
     * @param array<string, mixed> $asset
     * @throws NoLoginException
     */
    protected function privilegeAssetReceive(array $asset): bool
    {
        $response = $this->privilegeAssetsApi()->exchange((string)$asset['token']);
        switch ($response['code']) {
            case -101:
                throw new NoLoginException($response['message']);
            case 0:
                $this->rememberHandledPrivilegeToken((string)($asset['token'] ?? ''));
                $this->notice("大会员权益: 领取权益[{$asset['title']} * {$asset['customized_text']}]成功");
                return true;
            case 6034024:
                $this->rememberHandledPrivilegeToken((string)($asset['token'] ?? ''));
                $this->notice("大会员权益: 领取权益[{$asset['title']} * {$asset['customized_text']}]跳过 {$response['code']} -> {$response['message']}，视为已处理");
                return true;
            default:
                $this->warning("大会员权益: 领取权益[{$asset['title']} * {$asset['customized_text']}]失败 {$response['code']} -> {$response['message']}");
                return false;
        }
    }

    /**
     * @param array<string, mixed> $sku
     */
    protected function getCustomizedText(array $sku): string
    {
        $customized = $sku['icon']['customized'] ?? [];
        if (empty($customized)) {
            return '';
        }

        return ($customized['number'] ?? '') . ($customized['currency_symbol'] ?? '') . ($customized['unit'] ?? '') . ($customized['logo_text'] ?? '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadPendingPrivileges(): array
    {
        $today = date('Y-m-d');
        $savedDate = $this->cacheGet(self::CACHE_DATE_KEY, self::CACHE_SCOPE, null);
        if (!is_string($savedDate) || $savedDate !== $today) {
            return [];
        }

        $privileges = $this->cacheGet(self::CACHE_KEY, self::CACHE_SCOPE, null);

        return is_array($privileges) ? array_values($privileges) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $privileges
     */
    protected function savePendingPrivileges(array $privileges): void
    {
        $this->cacheSet(self::CACHE_DATE_KEY, date('Y-m-d'), self::CACHE_SCOPE);
        $this->cacheSet(self::CACHE_KEY, array_values($privileges), self::CACHE_SCOPE);
    }

    /**
     * 删除或清理待处理Privileges
     * @return void
     */
    protected function clearPendingPrivileges(): void
    {
        $this->cacheSet(self::CACHE_DATE_KEY, '', self::CACHE_SCOPE);
        $this->cacheSet(self::CACHE_KEY, [], self::CACHE_SCOPE);
    }

    /**
     * @return string[]
     */
    protected function loadHandledPrivilegeTokens(): array
    {
        $today = date('Y-m-d');
        $savedDate = $this->cacheGet(self::HANDLED_CACHE_DATE_KEY, self::CACHE_SCOPE, null);
        if (!is_string($savedDate) || $savedDate !== $today) {
            return [];
        }

        $tokens = $this->cacheGet(self::HANDLED_CACHE_KEY, self::CACHE_SCOPE, null);
        if (!is_array($tokens)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $token): string => trim((string)$token), $tokens), static fn (string $token): bool => $token !== ''));
    }

    /**
     * 处理rememberHandled权益令牌
     * @param string $token
     * @return void
     */
    protected function rememberHandledPrivilegeToken(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        $tokens = $this->loadHandledPrivilegeTokens();
        if (!in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }

        $this->cacheSet(self::HANDLED_CACHE_DATE_KEY, date('Y-m-d'), self::CACHE_SCOPE);
        $this->cacheSet(self::HANDLED_CACHE_KEY, array_values($tokens), self::CACHE_SCOPE);
    }

    /**
     * @param string[] $handledTokens
     */
    protected function isHandledPrivilegeToken(string $token, array $handledTokens): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        return in_array($token, $handledTokens, true);
    }

    /**
     * 处理大会员CenterAPI
     * @return ApiVipCenter
     */
    private function vipCenterApi(): ApiVipCenter
    {
        return $this->vipCenterApi ??= new ApiVipCenter($this->appContext()->request());
    }

    /**
     * 处理权益AssetsAPI
     * @return ApiPrivilegeAssets
     */
    private function privilegeAssetsApi(): ApiPrivilegeAssets
    {
        return $this->privilegeAssetsApi ??= new ApiPrivilegeAssets($this->appContext()->request());
    }

    /**
     * 处理experienceAPI
     * @return ApiExperience
     */
    private function experienceApi(): ApiExperience
    {
        return $this->experienceApi ??= new ApiExperience($this->appContext()->request());
    }
}
