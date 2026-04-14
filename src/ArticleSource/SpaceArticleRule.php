<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

final class SpaceArticleRule
{
    /**
     * 初始化 SpaceArticleRule
     * @param string $key
     * @param string $titlePrefix
     * @param string $urlPattern
     */
    public function __construct(
        public readonly string $key,
        public readonly string $titlePrefix,
        public readonly string $urlPattern,
    ) {
    }
}
