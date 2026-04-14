<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class SpaceArticleCatalog
{
    /**
     * @param SpaceArticleSummary[] $articles
     */
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        private readonly array $articles,
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromResponse(array $response): self
    {
        $articles = [];
        foreach ($response['data']['articles'] ?? [] as $article) {
            if (!is_array($article)) {
                continue;
            }

            $articles[] = SpaceArticleSummary::fromArray($article);
        }

        return new self(
            (int)($response['code'] ?? -1),
            (string)($response['message'] ?? ''),
            $articles,
        );
    }

    /**
     * 判断Successful是否满足条件
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->code === 0;
    }

    /**
     * @return SpaceArticleSummary[]
     */
    public function articles(): array
    {
        return $this->articles;
    }
}
