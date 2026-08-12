<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Follow;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Login\AuthFailureClassifier;

/**
 * 关注状态判定。
 *
 * 安全约束（不可退让）：绝不能把用户原有的关注误判为"本插件关注的"，否则会被取关。
 *
 * 因此采用非对称判定：
 *   - 运营位 is_follow 提示为 true  → 直接视为已关注并跳过（最坏结果只是少做一点进度，无害）
 *   - 提示为 false 或缺失          → 必须调 relationWithSelf 权威复核后才允许关注
 *   - 权威复核失败/结果不可判定    → 一律视为"不可关注"并跳过（fail-safe，宁可不做也不误取关）
 */
final class FollowStateResolver
{
    /**
     * @var array<int, bool> mid => is_follow 提示
     */
    private array $hints = [];

    private readonly ApiRelation $apiRelation;
    private readonly AuthFailureClassifier $authFailureClassifier;

    public function __construct(
        ApiRelation $apiRelation,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiRelation = $apiRelation;
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * 用运营位列表结果预热提示（省去逐个 relationWithSelf 请求）
     *
     * @param array<int, mixed> $operationList eva_operation/list 的 data.list
     */
    public function primeFromOperationList(array $operationList): void
    {
        foreach ($operationList as $item) {
            if (!is_array($item)) {
                continue;
            }

            $account = $item['object']['account'] ?? null;
            if (!is_array($account)) {
                continue;
            }

            $mid = (int)($account['mid'] ?? 0);
            if ($mid <= 0 || !array_key_exists('is_follow', $account)) {
                continue;
            }

            // 只采信 true；false 不写入提示，交给权威复核
            if ((bool)$account['is_follow'] === true) {
                $this->hints[$mid] = true;
            }
        }
    }

    /**
     * 判定目标是否已被关注。
     *
     * @return bool true 表示已关注（不应再关注，也绝不应取关）
     */
    public function isFollowing(int $mid): bool
    {
        if ($mid <= 0) {
            return true;
        }

        if (($this->hints[$mid] ?? false) === true) {
            return true;
        }

        return $this->queryFollowing($mid) !== false;
    }

    /**
     * 权威查询：true=已关注，false=确认未关注，null=无法判定
     */
    public function queryFollowing(int $mid): ?bool
    {
        if ($mid <= 0) {
            return null;
        }

        $response = $this->apiRelation->relationWithSelf($mid);
        $this->authFailureClassifier->assertNotAuthFailure($response, '查询关注关系时账号未登录');

        if ((int)($response['code'] ?? -1) !== 0) {
            return null;
        }

        $attribute = ApiRelation::extractCurrentUserRelationAttribute($response);
        if ($attribute === null) {
            return null;
        }

        $following = ApiRelation::isFollowingAttribute($attribute);
        if ($following) {
            $this->hints[$mid] = true;
        }

        return $following;
    }

    /**
     * 记入已关注提示（关注成功后调用，避免同轮重复请求）
     */
    public function markFollowed(int $mid): void
    {
        if ($mid > 0) {
            $this->hints[$mid] = true;
        }
    }

    /**
     * 清除提示（取关成功后调用）
     */
    public function forget(int $mid): void
    {
        unset($this->hints[$mid]);
    }
}
