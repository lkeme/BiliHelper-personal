<?php declare(strict_types=1);

namespace Bhp\Tests\GameForecast;

use Bhp\Plugin\Builtin\GameForecast\GameForecastPlugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class GameForecastPluginTest extends TestCase
{
    public function testAlreadyGuessedMessageIncludesStakedOptions(): void
    {
        $plugin = (new ReflectionClass(GameForecastPlugin::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GameForecastPlugin::class, 'alreadyGuessedMessage');

        $message = $method->invoke($plugin, [
            'questions' => [[
                'title' => 'XLG vs NOVA 的胜者是？',
                'details' => [
                    ['option' => 'XLG', 'stake' => 10],
                    ['option' => 'NOVA', 'stake' => 0],
                ],
            ]],
        ]);

        self::assertSame('赛事预测: 跳过已投注赛事 XLG vs NOVA 的胜者是？ [已投注: XLG]', $message);
    }
}
