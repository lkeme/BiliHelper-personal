<?php declare(strict_types=1);

namespace Bhp\Plugin {
    final class PluginBehaviorTestClock
    {
        public static ?int $frozenTimestamp = null;
    }

    function time(): int
    {
        return PluginBehaviorTestClock::$frozenTimestamp ?? \time();
    }
}

namespace Tests\Smoke {

    use Bhp\Config\Config;
    use Bhp\FilterWords\FilterWords;
    use Bhp\Log\Log;
    use Bhp\Notice\Notice;
    use Bhp\Plugin\Plugin;
    use Bhp\Plugin\PluginBehaviorTestClock;
    use Bhp\Runtime\AppContext;
    use PHPUnit\Framework\TestCase;

    final class PluginBehaviorTest extends TestCase
    {
        protected function tearDown(): void
        {
            PluginBehaviorTestClock::$frozenTimestamp = null;
            parent::tearDown();
        }

        public function testTriggerAggregatesScalarHandlerResultsWithoutDiscardingEarlierValues(): void
        {
            $context = $this->createStub(AppContext::class);
            $plugin = new TestPluginService($this->createStub(Config::class), 'app', __DIR__, $context, $this->notice($context), $this->createStub(Log::class));
            $handler = new TriggerProbeHandler();

            $plugin->register($handler, 'first');
            $plugin->register($handler, 'second');
            $plugin->register($handler, 'ignored');

            self::assertSame('first2', $plugin->trigger('TriggerProbeHandler'));
        }

        public function testTimeWindowSupportsOvernightRanges(): void
        {
            PluginBehaviorTestClock::$frozenTimestamp = strtotime('2026-04-05 00:30:00');
            $context = $this->createStub(AppContext::class);
            $plugin = new TestPluginService($this->createStub(Config::class), 'app', __DIR__, $context, $this->notice($context), $this->createStub(Log::class));

            self::assertTrue($plugin->evaluateTimeWindow('23:00:00', '01:00:00'));
        }

        public function testTimeWindowReturnsFalseWhenWindowCannotBeParsed(): void
        {
            PluginBehaviorTestClock::$frozenTimestamp = strtotime('2026-04-05 12:00:00');
            $context = $this->createStub(AppContext::class);
            $plugin = new TestPluginService($this->createStub(Config::class), 'app', __DIR__, $context, $this->notice($context), $this->createStub(Log::class));

            self::assertFalse($plugin->evaluateTimeWindow('not-a-time', '13:00:00'));
        }

        private function notice(AppContext $context): Notice
        {
            $filterWords = $this->createStub(FilterWords::class);
            $filterWords
                ->method('get')
                ->willReturn([]);

            return new Notice($context, $filterWords, null, []);
        }
    }

    final class TestPluginService extends Plugin
    {
        protected function detector(): void
        {
        }

        public function evaluateTimeWindow(string $start, string $end): bool
        {
            return $this->isWithinTimeRange($start, $end);
        }
    }

    final class TriggerProbeHandler
    {
        public function first(): string
        {
            return 'first';
        }

        public function second(): int
        {
            return 2;
        }

        /**
         * @return array{ignored: bool}
         */
        public function ignored(): array
        {
            return ['ignored' => true];
        }
    }
}
