<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

final class SpaceArticleSourceConfig
{
    private const ENCODED_HOST_MID = 'MzQ2MTU2ODg4NDkwMjg3OA==';
    private const ARTICLE_PAGE = 1;
    private const ARTICLE_PAGE_SIZE = 20;
    private const RETENTION_DAYS = 3;

    /**
     * 处理主机Mid
     * @return string
     */
    public function hostMid(): string
    {
        $decoded = base64_decode(self::ENCODED_HOST_MID, true);

        return is_string($decoded) ? trim($decoded) : '';
    }

    /**
     * 处理文章页面
     * @return int
     */
    public function articlePage(): int
    {
        return self::ARTICLE_PAGE;
    }

    /**
     * 处理文章页面Size
     * @return int
     */
    public function articlePageSize(): int
    {
        return self::ARTICLE_PAGE_SIZE;
    }

    /**
     * 处理retentionDays
     * @return int
     */
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

    /**
     * 处理startOfDay时间戳
     * @param int $now
     * @return int
     */
    public function startOfDayTimestamp(int $now): int
    {
        return strtotime(date('Y-m-d 00:00:00', $now)) ?: 0;
    }

    /**
     * 处理biz日期
     * @param int $now
     * @return string
     */
    public function bizDate(int $now): string
    {
        return date('Y-m-d', $now);
    }
}
