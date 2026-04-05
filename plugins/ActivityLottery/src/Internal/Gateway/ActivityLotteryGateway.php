<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway;

use Bhp\Util\Exceptions\RequestException;

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
        $this->pageHtmlFetcher = $pageHtmlFetcher ?? static fn (string $url): string => '';
        $this->noticePusher = $noticePusher ?? static function (string $channel, string $message): void {
        };
    }

    public function fetchActivityPageHtml(string $url): string
    {
        try {
            return (string)($this->pageHtmlFetcher)($url);
        } catch (RequestException) {
            return '';
        }
    }

    public function pushNotice(string $channel, string $message): void
    {
        ($this->noticePusher)($channel, $message);
    }
}

