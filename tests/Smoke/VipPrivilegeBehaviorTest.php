<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\App\ServiceContainer;
use Bhp\Cache\Cache;
use Bhp\Api\Vip\ApiPrivilegeAssets;
use Bhp\Log\Log;
use Bhp\Plugin\Builtin\VipPrivilege\VipPrivilegePlugin;
use Bhp\Profile\ProfileContext;
use Bhp\Runtime\RuntimeContext;
use PHPUnit\Framework\TestCase;

final class VipPrivilegeBehaviorTest extends TestCase
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

    public function testPhoneAuthorizationPrivilegeErrorIsTreatedAsHandledOutcome(): void
    {
        $plugin = new TestVipPrivilegePlugin();
        $context = $this->createContextWithCache();
        $log = new VipPrivilegeRecordingLog($context);

        $this->setPrivateProperty(\Bhp\Plugin\BasePlugin::class, $plugin, 'context', $context);
        $this->setPrivateProperty(\Bhp\Plugin\BasePlugin::class, $plugin, 'log', $log);
        $this->setPrivateProperty(VipPrivilegePlugin::class, $plugin, 'privilegeAssetsApi', new PhoneAuthFailurePrivilegeAssetsApi());

        $plugin->invokePrivilegeAssetReceive([
            'title' => '猫耳商城30元券包',
            'customized_text' => '2张优惠券',
            'token' => 'test-token',
        ]);

        self::assertFalse($log->hasLevel('WARNING'));
        self::assertTrue($log->hasMessageContaining('手机号授权异常'));
        self::assertTrue($log->hasMessageContaining('视为已处理'));
    }

    public function testHandledPhoneAuthorizationPrivilegeIsNotQueuedAgainOnSameDay(): void
    {
        $plugin = new TestVipPrivilegePlugin();
        $context = $this->createContextWithCache();
        $log = new VipPrivilegeRecordingLog($context);

        $this->setPrivateProperty(\Bhp\Plugin\BasePlugin::class, $plugin, 'context', $context);
        $this->setPrivateProperty(\Bhp\Plugin\BasePlugin::class, $plugin, 'log', $log);
        $this->setPrivateProperty(VipPrivilegePlugin::class, $plugin, 'privilegeAssetsApi', new StickyPhoneAuthFailurePrivilegeAssetsApi());

        $plugin->invokePrivilegeAssetReceive([
            'title' => '猫耳商城30元券包',
            'customized_text' => '2张优惠券',
            'token' => 'same-token',
        ]);

        $remaining = $plugin->invokeFilterCanReceive();

        self::assertSame([], $remaining);
    }

    private function setPrivateProperty(string $className, object $target, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($className, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($target, $value);
    }

    private function createContextWithCache(): RuntimeContext
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bhp-vippriv-' . uniqid('', true);
        $this->tempRoots[] = $root;
        mkdir($root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'vippriv' . DIRECTORY_SEPARATOR . 'cache', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'vippriv' . DIRECTORY_SEPARATOR . 'config', 0777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'vippriv' . DIRECTORY_SEPARATOR . 'log', 0777, true);

        $profile = ProfileContext::fromAppRoot($root, 'vippriv');
        $cache = new Cache($profile);
        $container = new ServiceContainer();

        return new RuntimeContext($profile, $container, $cache);
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

final class TestVipPrivilegePlugin extends VipPrivilegePlugin
{
    public function __construct()
    {
    }

    /**
     * @param array<string, mixed> $asset
     */
    public function invokePrivilegeAssetReceive(array $asset): void
    {
        $this->privilegeAssetReceive($asset);
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function invokeFilterCanReceive(): array
    {
        return $this->filterCanReceive();
    }
}

final class PhoneAuthFailurePrivilegeAssetsApi extends ApiPrivilegeAssets
{
    public function __construct()
    {
    }

    public function exchange(string $token): array
    {
        return [
            'code' => 6034024,
            'message' => '用户手机号授权异常',
        ];
    }
}

final class StickyPhoneAuthFailurePrivilegeAssetsApi extends ApiPrivilegeAssets
{
    public function __construct()
    {
    }

    public function exchange(string $token): array
    {
        return [
            'code' => 6034024,
            'message' => '用户手机号授权异常',
        ];
    }

    public function list(): array
    {
        return [
            'code' => 0,
            'data' => [
                'tabs' => [[
                    'name' => '站内福利',
                    'type' => 1,
                    'type_code' => 'welfare',
                    'groups' => [[
                        'title' => '普通权益',
                        'privilege_skus' => [[
                            'type' => 123,
                            'title' => '猫耳商城30元券包',
                            'token' => 'same-token',
                            'exchange' => [
                                'can_exchange' => true,
                                'hit_exchange_limit' => false,
                                'state' => 0,
                            ],
                            'icon' => [
                                'customized' => [
                                    'number' => '2',
                                    'currency_symbol' => '',
                                    'unit' => '张优惠券',
                                    'logo_text' => '',
                                ],
                            ],
                        ]],
                    ]],
                ]],
            ],
        ];
    }
}

final class VipPrivilegeRecordingLog extends Log
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

    public function hasLevel(string $level): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['level'] === $level) {
                return true;
            }
        }

        return false;
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
