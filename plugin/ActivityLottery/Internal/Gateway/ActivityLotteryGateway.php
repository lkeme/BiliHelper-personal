<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Notice\Notice;
use Bhp\Request\Request;

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

    public function __construct(
        ?callable $pageHtmlFetcher = null,
        ?callable $noticePusher = null,
    ) {
        $this->pageHtmlFetcher = $pageHtmlFetcher ?? static fn (string $url): string => (string)Request::get('other', $url);
        $this->noticePusher = $noticePusher ?? static function (string $channel, string $message): void {
            Notice::push($channel, $message);
        };
    }

    public function fetchActivityPageHtml(string $url): string
    {
        return (string)($this->pageHtmlFetcher)($url);
    }

    public function pushNotice(string $channel, string $message): void
    {
        ($this->noticePusher)($channel, $message);
    }
}
