<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

final class SpaceArticleRule
{
    public function __construct(
        public readonly string $key,
        public readonly string $titlePrefix,
        public readonly string $urlPattern,
    ) {
    }
}
