<?php declare(strict_types=1);

namespace Bhp\Notice;

use Bhp\FilterWords\FilterWords;
use Bhp\Notice\Channel\BarkNoticeChannel;
use Bhp\Notice\Channel\DebugNoticeChannel;
use Bhp\Notice\Channel\DingTalkNoticeChannel;
use Bhp\Notice\Channel\FeiShuNoticeChannel;
use Bhp\Notice\Channel\GoCqhttpNoticeChannel;
use Bhp\Notice\Channel\PushDeerNoticeChannel;
use Bhp\Notice\Channel\PushPlusNoticeChannel;
use Bhp\Notice\Channel\ScNoticeChannel;
use Bhp\Notice\Channel\SctNoticeChannel;
use Bhp\Notice\Channel\TelegramNoticeChannel;
use Bhp\Notice\Channel\WeComAppNoticeChannel;
use Bhp\Notice\Channel\WeComNoticeChannel;
use Bhp\Runtime\AppContext;
use Bhp\Util\Exceptions\RequestException;

final class Notice
{
    /**
     * @var list<NoticeChannel>
     */
    private array $channels;

    private readonly NoticeMessageFactory $messageFactory;

    /**
     * @param list<NoticeChannel>|null $channels
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly FilterWords $filterWords,
        ?NoticeMessageFactory $messageFactory = null,
        ?array $channels = null,
    ) {
        $this->messageFactory = $messageFactory ?? new NoticeMessageFactory($this->context);
        $this->channels = $channels ?? self::defaultChannels($this->context);
    }

    public function publish(string $type, string $msg = ''): void
    {
        if (!$this->enabled('notify')) {
            return;
        }

        if ($this->filterMsgWords($msg)) {
            return;
        }

        $this->dispatchMessage($this->messageFactory->create($type, $msg));
    }

    protected function dispatchMessage(NoticeMessage $message): void
    {
        $payload = $message->toArray();
        foreach ($this->channels as $channel) {
            if (!$channel->supports()) {
                continue;
            }

            try {
                $channel->dispatch($payload);
            } catch (RequestException $exception) {
                $this->context->log()->recordWarning("通知发送失败 [{$channel->name()}]: {$exception->getMessage()}");
            }
        }
    }

    protected function filterMsgWords(string $msg): bool
    {
        $defaultWords = $this->filterWords->get('Notice.default');
        if (!is_array($defaultWords)) {
            $defaultWords = [];
        }

        $customWords = array_values(array_filter(
            array_map('trim', explode(',', (string)$this->context->config('notify.filter_words', ''))),
            static fn(string $word): bool => $word !== '',
        ));

        foreach (array_merge($defaultWords, $customWords) as $word) {
            if (!is_string($word) || $word === '') {
                continue;
            }

            if (str_contains($msg, $word)) {
                return true;
            }
        }

        return false;
    }

    protected function enabled(string $key, bool $default = false): bool
    {
        return $this->context->enabled($key, $default);
    }

    /**
     * @return list<NoticeChannel>
     */
    private static function defaultChannels(AppContext $context): array
    {
        return [
            new SctNoticeChannel($context),
            new ScNoticeChannel($context),
            new TelegramNoticeChannel($context),
            new DingTalkNoticeChannel($context),
            new PushPlusNoticeChannel($context),
            new GoCqhttpNoticeChannel($context),
            new DebugNoticeChannel($context),
            new WeComNoticeChannel($context),
            new WeComAppNoticeChannel($context),
            new FeiShuNoticeChannel($context),
            new BarkNoticeChannel($context),
            new PushDeerNoticeChannel($context),
        ];
    }
}
