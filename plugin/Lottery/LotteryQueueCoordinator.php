<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

final class LotteryQueueCoordinator
{
    public function __construct(
        private readonly LotteryContentAnalyzer $analyzer,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return int[]
     */
    public function registerArticleIds(array $articles, LotteryRuntimeState $state): array
    {
        $added = [];
        foreach ($this->analyzer->filterArticleIds($articles) as $cv) {
            if ($state->hasCv($cv)) {
                continue;
            }

            $state->addCv($cv);
            $added[] = $cv;
        }

        return $added;
    }

    /**
     * @return int[]
     */
    public function registerDynamicIds(string $data, LotteryRuntimeState $state): array
    {
        $added = [];
        foreach ($this->analyzer->extractDynamicIds($data) as $dynamicId) {
            if ($state->hasDynamic($dynamicId)) {
                continue;
            }

            $state->addDynamic($dynamicId);
            $added[] = $dynamicId;
        }

        return $added;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function registerReserveLottery(array $data, LotteryRuntimeState $state): ?\Bhp\Api\Response\DynamicReserveLottery
    {
        $lottery = $this->analyzer->extractReserveLottery($data);
        if ($lottery === null) {
            return null;
        }

        $state->addLottery($lottery->toArray());

        return $lottery;
    }
}
