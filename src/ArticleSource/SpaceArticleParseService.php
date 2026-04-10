<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

final class SpaceArticleParseService
{
    /**
     * @param array<string, mixed> $articleViewData
     * @return string[]
     */
    public function extractIds(array $articleViewData, SpaceArticleRule $rule): array
    {
        $matches = [];
        foreach ($this->collectStrings($articleViewData) as $value) {
            if (!str_contains($value, 'https://')) {
                continue;
            }

            if (preg_match_all($rule->urlPattern, $value, $captures) < 1) {
                continue;
            }

            foreach ($captures[1] ?? [] as $id) {
                $normalized = trim((string)$id);
                if ($normalized !== '') {
                    $matches[] = $normalized;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function collectStrings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            foreach ($this->collectStrings($item) as $string) {
                $strings[] = $string;
            }
        }

        return $strings;
    }
}
