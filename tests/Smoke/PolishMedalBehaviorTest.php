<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\Cache\Cache;
use Bhp\Api\Msg\ApiMsg;
use Bhp\Api\XLive\AppUcenter\V1\ApiFansMedal;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Log\Log;
use Bhp\Plugin\Builtin\PolishMedal\PolishMedalPlugin;
use Bhp\Runtime\AppContext;
use PHPUnit\Framework\TestCase;

final class PolishMedalBehaviorTest extends TestCase
{
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->tempRoots as $root) {
            if (is_dir($root)) {
                $this->removeDirectory($root);
            }
        }
    }

    public function testCleanupInvalidMedalSkipsCancelledAnchorAndPersistsMarker(): void
    {
        $plugin = new TestPolishMedalPlugin();
        $context = $this->createContext();
        $log = new PolishMedalRecordingLog($context);

        $this->setBasePluginContext($plugin, $context, $log);
        $this->setPrivateProperty(PolishMedalPlugin::class, $plugin, 'fansMedalApi', new CancelledAnchorFansMedalApi());

        $this->invokePrivateMethod($plugin, 'fetchGreyMedalList', false);

        $queue = $this->getPrivateProperty($plugin, 'grey_fans_medals');
        self::assertCount(1, $queue);
        self::assertSame('正常主播', $queue[0]['anchor_name']);

        $invalid = $context->cache()->pull('invalid_medals', 'PolishMedal');
        self::assertIsArray($invalid);
        self::assertArrayHasKey('medal_2', $invalid);
        self::assertSame('账号已注销', $invalid['medal_2']['reason']);
    }

    public function testCleanupInvalidMedalMarksBannedRoomAsInvalid(): void
    {
        $plugin = new TestPolishMedalPlugin();
        $context = $this->createContext();
        $log = new PolishMedalRecordingLog($context);

        $this->setBasePluginContext($plugin, $context, $log);

        $medal = [
            'uid' => 1,
            'roomid' => 9557103,
            'medal_id' => 9,
            'medal_name' => '测试勋章',
            'anchor_name' => '测试主播',
        ];
        $response = [
            'code' => 10033,
            'message' => '房间已封禁',
        ];

        $this->invokePrivateMethod($plugin, 'triggerException', $medal, $response);

        $invalid = $context->cache()->pull('invalid_medals', 'PolishMedal');
        self::assertIsArray($invalid);
        self::assertArrayHasKey('medal_9', $invalid);
        self::assertSame('房间已封禁', $invalid['medal_9']['reason']);
    }

    public function testPolishLogsProgressForCurrentQueuePosition(): void
    {
        $plugin = new TestPolishMedalPlugin();
        $context = $this->createContext();
        $log = new PolishMedalRecordingLog($context);

        $this->setBasePluginContext($plugin, $context, $log);
        $this->setPrivateProperty(PolishMedalPlugin::class, $plugin, 'msgApi', new SuccessfulMsgApi());
        $this->setPrivateProperty(PolishMedalPlugin::class, $plugin, 'grey_fans_medals', [
            [
                'uid' => 1,
                'roomid' => 11,
                'medal_id' => 1,
                'medal_name' => 'A',
                'anchor_name' => '主播A',
            ],
            [
                'uid' => 2,
                'roomid' => 22,
                'medal_id' => 2,
                'medal_name' => 'B',
                'anchor_name' => '主播B',
            ],
        ]);
        $this->setPrivateProperty(PolishMedalPlugin::class, $plugin, 'medal_batch_total', 2);

        $this->invokePrivateMethod($plugin, 'polishTheMedal');

        self::assertTrue($log->hasMessageContaining('(1/2)'));
    }

    private function createContext(): AppContext
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bhp-polish-' . uniqid('', true);
        $this->tempRoots[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'polish' . DIRECTORY_SEPARATOR . 'cache', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'polish' . DIRECTORY_SEPARATOR . 'config', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'polish' . DIRECTORY_SEPARATOR . 'log', 0777, true);

        $profile = new \Bhp\Profile\ProfileContext(
            rtrim(str_replace('\\', '/', $root), '/') . '/',
            'polish',
            rtrim(str_replace('\\', '/', $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'polish'), '/') . '/',
            rtrim(str_replace('\\', '/', $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'polish' . DIRECTORY_SEPARATOR . 'config'), '/') . '/',
            rtrim(str_replace('\\', '/', $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'polish' . DIRECTORY_SEPARATOR . 'log'), '/') . '/',
            rtrim(str_replace('\\', '/', $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'polish' . DIRECTORY_SEPARATOR . 'cache'), '/') . '/',
        );
        $cache = new Cache($profile);
        $context = $this->createStub(AppContext::class);
        $context->method('cache')->willReturn($cache);
        $context->method('config')->willReturnCallback(static function (string $key, mixed $default = null, string $type = 'default'): mixed {
            return match ($key) {
                'polish_medal.cleanup_invalid_medal' => true,
                'polish_medal.reply_words' => '',
                default => $default,
            };
        });

        return $context;
    }

    private function setBasePluginContext(object $plugin, AppContext $context, Log $log): void
    {
        $this->setPrivateProperty(\Bhp\Plugin\BasePlugin::class, $plugin, 'context', $context);
        $this->setPrivateProperty(\Bhp\Plugin\BasePlugin::class, $plugin, 'log', $log);
        $this->setPrivateProperty(PolishMedalPlugin::class, $plugin, 'authFailureClassifier', new AuthFailureClassifier());
    }

    private function setPrivateProperty(string $className, object $target, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($className, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($target, $value);
    }

    private function getPrivateProperty(object $target, string $property): mixed
    {
        $reflection = new \ReflectionProperty(PolishMedalPlugin::class, $property);
        $reflection->setAccessible(true);
        return $reflection->getValue($target);
    }

    private function invokePrivateMethod(object $target, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod(PolishMedalPlugin::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($target, ...$args);
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->removeDirectory($target);
                continue;
            }

            @unlink($target);
        }

        @rmdir($path);
    }
}

final class TestPolishMedalPlugin extends PolishMedalPlugin
{
    public function __construct()
    {
    }
}

final class CancelledAnchorFansMedalApi extends ApiFansMedal
{
    public function __construct()
    {
    }

    public function panel(int $pn, int $ps): array
    {
        if ($pn > 1) {
            return [
                'code' => 0,
                'data' => [
                    'list' => [],
                    'special_list' => [],
                    'total_number' => 2,
                ],
            ];
        }

        return [
            'code' => 0,
            'data' => [
                'list' => [
                    [
                        'medal' => [
                            'target_id' => 11,
                            'medal_id' => 1,
                            'medal_name' => '有效勋章',
                            'medal_color_start' => 12632256,
                            'medal_color_end' => 12632256,
                            'medal_color_border' => 12632256,
                        ],
                        'anchor_info' => [
                            'nick_name' => '正常主播',
                        ],
                        'room_info' => [
                            'room_id' => 111,
                        ],
                    ],
                    [
                        'medal' => [
                            'target_id' => 22,
                            'medal_id' => 2,
                            'medal_name' => '注销勋章',
                            'medal_color_start' => 12632256,
                            'medal_color_end' => 12632256,
                            'medal_color_border' => 12632256,
                        ],
                        'anchor_info' => [
                            'nick_name' => '账号已注销',
                        ],
                        'room_info' => [
                            'room_id' => 222,
                        ],
                    ],
                ],
                'special_list' => [],
                'total_number' => 2,
            ],
        ];
    }
}

final class SuccessfulMsgApi extends ApiMsg
{
    public function __construct()
    {
    }

    public function sendBarrageAPP(int $room_id, string $content): array
    {
        return [
            'code' => 0,
            'message' => 'ok',
        ];
    }
}

final class PolishMedalRecordingLog extends Log
{
    /**
     * @var array<int, array{level: string, message: string}>
     */
    private array $entries = [];

    protected function log(string $level, string $msg, array $context = []): void
    {
        $this->entries[] = [
            'level' => $level,
            'message' => $msg,
        ];
    }

    public function hasMessageContaining(string $needle): bool
    {
        foreach ($this->entries as $entry) {
            if (str_contains($entry['message'], $needle)) {
                return true;
            }
        }

        return false;
    }
}
