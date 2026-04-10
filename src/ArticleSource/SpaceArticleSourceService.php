<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

use Bhp\Api\Space\ApiArticle;
use Bhp\Cache\Cache;
use Bhp\Runtime\AppContext;
use Closure;
use RuntimeException;

final class SpaceArticleSourceService
{
    /**
     * @param callable():array<int, array<string, mixed>>|null $articleListLoader
     * @param callable(int):array<string, mixed>|null $articleContentLoader
     * @param callable():int|null $clock
     */
    public function __construct(
        private readonly ?AppContext $context = null,
        ?SpaceArticleSourceConfig $config = null,
        ?SpaceArticleCacheStore $cacheStore = null,
        ?SpaceArticleParseService $parseService = null,
        ?callable $articleListLoader = null,
        ?callable $articleContentLoader = null,
        ?callable $clock = null,
    ) {
        $this->config = $config ?? new SpaceArticleSourceConfig();
        $this->cacheStore = $cacheStore ?? new SpaceArticleCacheStore($this->cache(), $this->config->retentionDays());
        $this->parseService = $parseService ?? new SpaceArticleParseService();
        $this->articleListLoader = $articleListLoader !== null ? Closure::fromCallable($articleListLoader) : null;
        $this->articleContentLoader = $articleContentLoader !== null ? Closure::fromCallable($articleContentLoader) : null;
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : null;
    }

    private readonly SpaceArticleSourceConfig $config;
    private readonly SpaceArticleCacheStore $cacheStore;
    private readonly SpaceArticleParseService $parseService;
    private readonly ?Closure $articleListLoader;
    private readonly ?Closure $articleContentLoader;
    private readonly ?Closure $clock;

    public function snapshotForToday(): SpaceArticleDailySnapshot
    {
        $now = $this->now();
        $bizDate = $this->config->bizDate($now);
        $existing = $this->cacheStore->load($bizDate);
        if ($existing instanceof SpaceArticleDailySnapshot && $existing->fetchAttempted) {
            return $existing;
        }

        $snapshot = $this->buildSnapshot($bizDate, $now);
        $this->cacheStore->save($snapshot);

        return $snapshot;
    }

    private function buildSnapshot(string $bizDate, int $fetchedAt): SpaceArticleDailySnapshot
    {
        $candidates = $this->fetchTodayCandidates($fetchedAt);
        if ($candidates === []) {
            return SpaceArticleDailySnapshot::empty($bizDate, $fetchedAt);
        }

        $reservationCandidate = $this->selectLatestCandidate($candidates, $this->config->rules()['reservation']);
        $lotteryCandidate = $this->selectLatestCandidate($candidates, $this->config->rules()['lottery']);

        $reservationIds = [];
        $lotteryIds = [];
        $reservationTitle = $reservationCandidate?->title;
        $lotteryTitle = $lotteryCandidate?->title;

        if ($reservationCandidate instanceof SpaceArticleCandidate) {
            $reservationIds = $this->parseService->extractIds(
                $this->loadArticleContent($reservationCandidate->cvId),
                $this->config->rules()['reservation'],
            );
        }

        if ($lotteryCandidate instanceof SpaceArticleCandidate) {
            $lotteryIds = $this->parseService->extractIds(
                $this->loadArticleContent($lotteryCandidate->cvId),
                $this->config->rules()['lottery'],
            );
        }

        return new SpaceArticleDailySnapshot(
            $bizDate,
            true,
            $fetchedAt,
            $reservationCandidate?->cvId,
            $reservationTitle,
            $lotteryCandidate?->cvId,
            $lotteryTitle,
            $reservationIds,
            $lotteryIds,
        );
    }

    /**
     * @return SpaceArticleCandidate[]
     */
    private function fetchTodayCandidates(int $now): array
    {
        $startOfDay = $this->config->startOfDayTimestamp($now);
        $candidates = [];
        foreach ($this->loadArticleList() as $row) {
            $candidate = SpaceArticleCandidate::fromArticleRow($row);
            if (!$candidate instanceof SpaceArticleCandidate) {
                continue;
            }

            if ($candidate->publishTime < $startOfDay) {
                continue;
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadArticleList(): array
    {
        if (is_callable($this->articleListLoader)) {
            $loaded = ($this->articleListLoader)();

            return is_array($loaded) ? array_values(array_filter($loaded, 'is_array')) : [];
        }

        $response = $this->articleApi()->article(
            $this->config->hostMid(),
            $this->config->articlePage(),
            $this->config->articlePageSize(),
        );
        $articles = $response['data']['articles'] ?? null;

        return is_array($articles) ? array_values(array_filter($articles, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadArticleContent(int $cvId): array
    {
        if (is_callable($this->articleContentLoader)) {
            $loaded = ($this->articleContentLoader)($cvId);

            return is_array($loaded) ? $loaded : [];
        }

        return $this->articleApi()->view($cvId);
    }

    /**
     * @param SpaceArticleCandidate[] $candidates
     */
    private function selectLatestCandidate(array $candidates, SpaceArticleRule $rule): ?SpaceArticleCandidate
    {
        $matched = array_values(array_filter(
            $candidates,
            static fn(SpaceArticleCandidate $candidate): bool => str_starts_with($candidate->title, $rule->titlePrefix),
        ));
        if ($matched === []) {
            return null;
        }

        usort($matched, static function (SpaceArticleCandidate $left, SpaceArticleCandidate $right): int {
            return $right->publishTime <=> $left->publishTime;
        });

        return $matched[0];
    }

    private function articleApi(): ApiArticle
    {
        return new ApiArticle($this->request());
    }

    private function request(): \Bhp\Request\Request
    {
        if ($this->context instanceof AppContext) {
            return $this->context->request();
        }

        throw new RuntimeException('SpaceArticleSourceService requires AppContext when no loaders are provided.');
    }

    private function cache(): Cache
    {
        if ($this->context instanceof AppContext) {
            return $this->context->cache();
        }

        throw new RuntimeException('SpaceArticleSourceService requires Cache when no cache store is provided.');
    }

    private function now(): int
    {
        if (is_callable($this->clock)) {
            return (int)($this->clock)();
        }

        return time();
    }
}
