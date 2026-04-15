<?php declare(strict_types=1);

namespace Bhp\Notice;

use Bhp\Runtime\AppContext;

final class NoticeMessageFactory
{
    /**
     * @param callable():string|null $userNameResolver
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly mixed $userNameResolver = null,
    ) {
    }

    /**
     * @return array{title:string, content:string}
     */
    public function fillContent(string $type, string $msg): array
    {
        $nowTime = date('Y-m-d H:i:s');
        $userName = $this->resolveUserName();

        $info = match ($type) {
            'update' => [
                'title' => '版本更新通知',
                'content' => "[$nowTime] 用户: $userName 程序更新通知: $msg",
            ],
            'anchor' => [
                'title' => '天选时刻获奖记录',
                'content' => "[$nowTime] 用户: $userName 在天选时刻中获得: $msg",
            ],
            'raffle' => [
                'title' => '实物奖励获奖纪录',
                'content' => "[$nowTime] 用户: $userName 在实物奖励中获得: $msg",
            ],
            'gift' => [
                'title' => '活动礼物获奖纪录',
                'content' => "[$nowTime] 用户: $userName 在活动礼物中获得: $msg",
            ],
            'storm' => [
                'title' => '节奏风暴获奖纪录',
                'content' => "[$nowTime] 用户: $userName 在节奏风暴中获得: $msg",
            ],
            'cookieRefresh' => [
                'title' => 'Cookie刷新',
                'content' => "[$nowTime] 用户: $userName 刷新Cookie: $msg",
            ],
            'todaySign' => [
                'title' => '每日签到',
                'content' => "[$nowTime] 用户: $userName 签到: $msg",
            ],
            'banned' => [
                'title' => '任务小黑屋',
                'content' => "[$nowTime] 用户: $userName 小黑屋: $msg",
            ],
            'network_error' => [
                'title' => '网络异常 ',
                'content' => "[$nowTime] 用户: $userName 错误详情: $msg",
            ],
            'login_relogin_required' => [
                'title' => '登录状态失效',
                'content' => "[$nowTime] 用户: $userName 详情: $msg",
            ],
            'key_expired' => [
                'title' => '监控KEY异常',
                'content' => "[$nowTime] 用户: $userName 监控KEY到期或者错误，请及时查错或续期后重试哦~",
            ],
            'capsule_lottery' => [
                'title' => '直播扭蛋抽奖活动',
                'content' => "[$nowTime] 用户: $userName 详情: $msg",
            ],
            'activity_lottery' => [
                'title' => '转盘抽奖活动',
                'content' => "[$nowTime] 用户: $userName 详情: $msg",
            ],
            'jury_leave_office' => [
                'title' => '已卸任風機委員',
                'content' => "[$nowTime] 用户: $userName 详情: $msg ，请及时关注風機委員连任状态哦~",
            ],
            'jury_auto_apply' => [
                'title' => '嘗試連任風機委員',
                'content' => "[$nowTime] 用户: $userName 详情: $msg ，请及时关注風機委員连任状态哦~",
            ],
            default => [
                'title' => $type,
                'content' => "[$nowTime] 用户: $userName 详情: $msg",
            ],
        };

        $info['title'] = '【BHP】' . $info['title'];

        return $info;
    }

    /**
     * 处理create
     * @param string $type
     * @param string $msg
     * @return NoticeMessage
     */
    public function create(string $type, string $msg): NoticeMessage
    {
        $info = $this->fillContent($type, $msg);

        return new NoticeMessage(
            $type,
            $info['title'],
            $info['content'],
            [
                'profile' => $this->context->profileName(),
                'type' => $type,
            ],
        );
    }

    /**
     * 解析用户名称
     * @return string
     */
    private function resolveUserName(): string
    {
        if (is_callable($this->userNameResolver)) {
            return (string)call_user_func($this->userNameResolver);
        }

        return (string)($this->context->config('print.uname') ?? $this->context->config('login_account.username'));
    }
}
