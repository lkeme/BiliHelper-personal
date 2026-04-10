<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

final class SpaceArticleSourceConfig
{
    private const ENCODED_HOST_MID = 'MzQ2MTU2ODg4NDkwMjg3OA==';
    private const ARTICLE_PAGE = 1;
    private const ARTICLE_PAGE_SIZE = 20;
    private const RETENTION_DAYS = 3;

    public function hostMid(): string
    {
        $decoded = base64_decode(self::ENCODED_HOST_MID, true);

        return is_string($decoded) ? trim($decoded) : '';
    }

    public function articlePage(): int
    {
        return self::ARTICLE_PAGE;
    }

    public function articlePageSize(): int
    {
        return self::ARTICLE_PAGE_SIZE;
    }

    public function retentionDays(): int
    {
        return self::RETENTION_DAYS;
    }

    /**
     * @return array<string, SpaceArticleRule>
     */
    public function rules(): array
    {
        return [
            'reservation' => new SpaceArticleRule(
                'reservation',
                '预约抽奖',
                '/https:\/\/space\.bilibili\.com\/(\d+)/u',
            ),
            'lottery' => new SpaceArticleRule(
                'lottery',
                '互动抽奖',
                '/https:\/\/t\.bilibili\.com\/(\d+)/u',
            ),
        ];
    }

    public function startOfDayTimestamp(int $now): int
    {
        return strtotime(date('Y-m-d 00:00:00', $now)) ?: 0;
    }

    public function bizDate(int $now): string
    {
        return date('Y-m-d', $now);
    }
}
