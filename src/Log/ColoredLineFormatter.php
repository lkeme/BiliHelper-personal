<?php declare(strict_types=1);

namespace Bhp\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

final class ColoredLineFormatter extends LineFormatter
{
    /**
     * @var array<int, string>
     */
    private array $colorMap = [
        100 => "\033[0;37m",
        200 => "\033[32m",
        250 => "\033[36m",
        300 => "\033[33m",
        400 => "\033[31m",
        500 => "\033[1;31m",
        550 => "\033[1;35m",
        600 => "\033[1;35m",
    ];

    public function format(LogRecord $record): string
    {
        $formatted = trim(parent::format($record));
        $color = $this->colorMap[$record->level->value] ?? "\033[0m";

        return $color . $formatted . "\033[0m" . PHP_EOL;
    }
}
