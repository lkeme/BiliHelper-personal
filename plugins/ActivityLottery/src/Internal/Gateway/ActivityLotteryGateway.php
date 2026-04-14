<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway;

final class ActivityLotteryGateway
{
    /**
     * @var callable(string): string
     */
    private readonly mixed $pageHtmlFetcher;
    /**
     * @var callable(string, string): void
     */
    private readonly mixed $noticePusher;

    /**
     * 初始化 ActivityLotteryGateway
     * @param callable $pageHtmlFetcher
     * @param callable $noticePusher
     */
    public function __construct(
        ?callable $pageHtmlFetcher = null,
        ?callable $noticePusher = null,
    ) {
        $this->pageHtmlFetcher = $pageHtmlFetcher ?? static fn (string $url): string => '';
        $this->noticePusher = $noticePusher ?? static function (string $channel, string $message): void {
        };
    }

    /**
     * 获取Activity页面Html
     * @param string $url
     * @return string
     */
    public function fetchActivityPageHtml(string $url): string
    {
        return (string)($this->pageHtmlFetcher)($url);
    }

    /**
     * 处理push通知
     * @param string $channel
     * @param string $message
     * @return void
     */
    public function pushNotice(string $channel, string $message): void
    {
        ($this->noticePusher)($channel, $message);
    }
}

