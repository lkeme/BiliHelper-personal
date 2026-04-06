<?php declare(strict_types=1);

namespace Bhp\Config;

final class ConfigTemplateSynchronizer
{
    public function synchronize(string $templatePath, string $targetPath): ConfigTemplateSyncResult
    {
        $templateContent = (string)file_get_contents($templatePath);
        $targetExists = is_file($targetPath);
        $targetContent = $targetExists ? (string)file_get_contents($targetPath) : '';
        $rawValues = $targetExists ? $this->extractRawValues($targetContent) : [];

        $rendered = $this->renderTemplate($templateContent, $rawValues);
        if ($targetExists && $rendered === $targetContent) {
            return new ConfigTemplateSyncResult(false);
        }

        $backupPath = null;
        if ($targetExists) {
            $backupPath = $targetPath . '.bak';
            file_put_contents($backupPath, $targetContent);
        }

        file_put_contents($targetPath, $rendered);

        return new ConfigTemplateSyncResult(true, $backupPath);
    }

    /**
     * @return array<string, string>
     */
    private function extractRawValues(string $content): array
    {
        $values = [];
        $section = '';
        foreach ($this->splitLines($content) as $line) {
            $trimmed = trim($line);
            if (preg_match('/^\[(.+)]$/', $trimmed, $matches) === 1) {
                $section = trim($matches[1]);
                continue;
            }

            $legacySection = $this->extractTrailingSectionHeader($trimmed);
            if ($legacySection !== null) {
                $section = $legacySection;
                continue;
            }

            if ($trimmed === '' || str_starts_with($trimmed, ';') || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $matches) === 1) {
                $values[$section . '.' . $matches[1]] = $matches[2];
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $rawValues
     */
    private function renderTemplate(string $templateContent, array $rawValues): string
    {
        $newline = $this->detectNewline($templateContent);
        $hasTrailingNewline = preg_match('/\R\z/', $templateContent) === 1;
        $hasBom = str_starts_with($templateContent, "\xEF\xBB\xBF");
        $body = $hasBom ? substr($templateContent, 3) : $templateContent;

        $section = '';
        $lines = $this->splitLines($body, $newline);
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            $legacySection = $this->extractTrailingSectionHeader($trimmed);
            if ($legacySection !== null) {
                $section = $legacySection;
                continue;
            }

            if ($trimmed === '' || str_starts_with($trimmed, ';') || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^\[(.+)]$/', $trimmed, $matches) === 1) {
                $section = trim($matches[1]);
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+)(\s*=\s*)(.*)$/', $line, $matches) === 1) {
                $key = $section . '.' . $matches[1];
                if (array_key_exists($key, $rawValues)) {
                    $lines[$index] = $matches[1] . $matches[2] . $rawValues[$key];
                }
            }
        }

        $rendered = implode($newline, $lines);
        if ($hasTrailingNewline) {
            $rendered .= $newline;
        }

        return ($hasBom ? "\xEF\xBB\xBF" : '') . $rendered;
    }

    private function detectNewline(string $content): string
    {
        if (str_contains($content, "\r\n")) {
            return "\r\n";
        }

        if (str_contains($content, "\r")) {
            return "\r";
        }

        return "\n";
    }

    /**
     * @return string[]
     */
    private function splitLines(string $content, ?string $newline = null): array
    {
        $newline ??= $this->detectNewline($content);

        return explode($newline, $content);
    }

    private function extractTrailingSectionHeader(string $line): ?string
    {
        if ($line === '' || (!str_starts_with($line, ';') && !str_starts_with($line, '#'))) {
            return null;
        }

        if (preg_match('/(\[[A-Za-z0-9_]+])$/', $line, $matches) !== 1) {
            return null;
        }

        if (preg_match('/^\[(.+)]$/', $matches[1], $sectionMatches) !== 1) {
            return null;
        }

        return trim($sectionMatches[1]);
    }
}
