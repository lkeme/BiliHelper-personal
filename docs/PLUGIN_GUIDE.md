# 插件开发指南

## 基本原则

新插件必须遵守：

- 实现 `PluginTaskInterface`
- 提供 `runOnce(): TaskResult`
- 使用插件 manifest 声明元数据
- 优先使用 `AppContext` 访问配置、设备、认证与路径
- 不新增 `getConf()`、`getEnable()`、`getU()`、`setU()` 这类 helper 依赖
- 插件统一放在 `plugins/<plugin>/` 下，通过 `plugin.json` 接入运行时

## 推荐基类

常规插件：

- 继承 `BasePlugin`

资源文件读取：

- 直接使用 `Resource` 或专用 service，不再增加额外插件基类

`BasePlugin` 已提供：

- `config()`
- `enabled()`
- `auth()`
- `setAuth()`
- `appContext()`
- `scheduleAfter()`
- `retryAfter()`
- `resolveTaskResult()`
- `bootPlugin()`

## 复杂插件建议

对于需要目录加载、状态持久化、节点编排的插件，不要把全部流程堆进入口类。

推荐做法：

- 插件入口类只保留 `runOnce(): TaskResult`
- 复杂业务下沉到独立 runtime，例如 `ActivityLotteryRuntime`
- 节点执行、副作用调用、状态存储分别拆到 `Node / Gateway / Flow / Runtime`

`ActivityLottery` 当前就是这一模式：

- 入口类只负责 `enabled()` 判断和 runtime 装配
- runtime 负责窗口判断、建流、落库、调度和单步推进
- 生命周期日志由独立的 `ActivityLotteryLifecycleLogger` 负责
- 公共观看能力放在 `src/Automation/Watch/*`，避免插件内部重复造轮子

## 插件 manifest

插件目录最少应包含：

- `plugin.json`
- `src/`

发现阶段和运行态都只认 `plugin.json`。类属性 `info`、`getPluginInfo()`、`discoverManifest()` 都不再作为元数据来源。

装配阶段会先把 `plugin.json` 收敛为 `PluginManifest` DTO，再做校验和默认值补齐。

最少字段：

```json
{
  "hook": "DemoPlugin",
  "name": "DemoPlugin",
  "version": "0.0.1",
  "desc": "示例插件",
  "author": "YourName",
  "priority": 1200,
  "cycle": "5(分钟)",
  "valid_until": "2099-12-31 23:59:59",
  "activity_url": "",
  "reference_links": [],
  "class_name": "Bhp\\Plugin\\Builtin\\Demo\\DemoPlugin",
  "entry": "src/DemoPlugin.php",
  "vendor": "official",
  "source": "bundled"
}
```

推荐补全字段：

```json
{
  "hook": "DemoPlugin",
  "name": "DemoPlugin",
  "version": "0.0.1",
  "desc": "示例插件",
  "author": "YourName",
  "priority": 1200,
  "cycle": "5(分钟)",
  "valid_until": "2099-12-31 23:59:59",
  "activity_url": "",
  "reference_links": [],
  "mode": "app",
  "interval_seconds": 300,
  "max_concurrency": 1,
  "overrun_policy": "skip",
  "timeout_seconds": 30.0,
  "bootstrap_first": false,
  "governance_hosts": [],
  "governance_window_seconds": 0,
  "governance_max_requests_per_host": 0,
  "governance_cooldown_seconds": 0,
  "governance_group": "",
  "governance_group_max_concurrency": 0,
  "governance_profile": "",
  "governance_group_backoff_seconds": 0.0,
  "governance_cooldown_multiplier": 0.0,
  "php_min": "8.5.0",
  "php_max": null,
  "required_extensions": [],
  "provides_capabilities": [],
  "requires_capabilities": [],
  "class_name": "Bhp\\Plugin\\Builtin\\Demo\\DemoPlugin",
  "entry": "src/DemoPlugin.php",
  "vendor": "official",
  "source": "bundled"
}
```

当前默认值由 `PluginManifest` 自动补齐：

- `php_min = 8.5.0`
- `valid_until = 2099-12-31 23:59:59`
- `php_max = null`
- `required_extensions = []`
- `provides_capabilities = []`
- `requires_capabilities = []`

Link metadata:

- `activity_url`: main activity link for the plugin; write `""` when unknown or not applicable
- `reference_links`: developer reference link list; write `[]` when empty
- each `reference_links` item is `{"url": "...", "comment": "..."}`
- these fields are manifest metadata only and do not participate in runtime behavior

治理元数据说明：

- `governance_group` / `governance_group_max_concurrency`：对同一治理组做并发预算控制
- `governance_profile`：使用内建治理画像，当前支持 `auth`、`interactive`
- `governance_group_backoff_seconds`：显式覆盖治理组预算耗尽后的回退秒数
- `governance_cooldown_multiplier`：显式放大 host 冷却窗口回退时间

## 插件注册

在构造函数中注册：

```php
public function __construct(Plugin &$plugin)
{
    $plugin->register($this, 'runOnce');
}
```

不要省略注册，否则注册表会把插件标记为 `not_registered`。

如果插件需要缓存初始化，优先统一写成：

```php
public function __construct(Plugin &$plugin)
{
    $this->bootPlugin($plugin, true);
}
```

## runOnce 约定

推荐模板：

```php
public function runOnce(): TaskResult
{
    $this->resetTaskResult();

    if (!$this->enabled('demo_plugin')) {
        return TaskResult::keepSchedule();
    }

    // 业务逻辑

    return $this->resolveTaskResult(TaskResult::after(300));
}
```

关键点：

- 开头调用 `resetTaskResult()`
- 禁止返回 `void`、`bool`、`array`
- 必须返回 `TaskResult`
- 延迟、重试、定时调度都通过 `TaskResult` 描述

## 配置访问

推荐：

```php
$enabled = $this->enabled('demo_plugin');
$limit = $this->config('demo_plugin.limit', 10, 'int');
$device = $this->appContext()->device('platform.headers.app_ua');
```

不要再写：

```php
getEnable('demo_plugin');
getConf('demo_plugin.limit', 10, 'int');
```

## 认证访问

推荐：

```php
$token = $this->auth('access_token');
$cookie = $this->auth('cookie');
$this->setAuth('access_token', $newToken);
```

不要直接操作缓存，也不要继续用全局 helper。

## 状态缓存

插件内如果需要持久化自己的运行状态，优先使用显式 cache scope，而不是依赖隐式调用栈推断。

推荐：

```php
private const CACHE_SCOPE = 'MainSite';

$records = Cache::get('records', self::CACHE_SCOPE);
Cache::set('records', $records, self::CACHE_SCOPE);
```

不要在热点插件里继续新增：

```php
Cache::get('records');
Cache::set('records', $records);
```

## 日志规范

日志默认会补齐这些上下文：

- `profile`
- `plugin`
- `task`
- `request_id`

插件内仍应尽量写清业务语义：

```php
$this->info('示例插件: 开始执行');
$this->warning('示例插件: 请求失败');
```

不要输出：

- 明文 token
- 明文 cookie
- 明文密码

## 通知规范

业务层只通过插件基类或注入后的 `Notice` 服务发通知：

```php
$this->notify('update', '发现新版本');
```

不要在插件里拼接具体渠道 URL，也不要直接调用通知渠道实现。

## 测试建议

新插件至少补：

- 启用/禁用行为
- 成功分支
- 失败或重试分支
- 特殊调度分支

如果插件只依赖 `BasePlugin` 的上下文方法，测试会比直接依赖 helper 更容易写。

## 新插件 checklist

- 已声明 manifest 基本字段
- 已实现 `runOnce(): TaskResult`
- 已在构造函数调用 `register($this, 'runOnce')`
- 已优先考虑命名空间插件类与 `bootPlugin()` 模式
- 未新增 helper 依赖
- 未输出敏感信息
- 已补基础行为测试
