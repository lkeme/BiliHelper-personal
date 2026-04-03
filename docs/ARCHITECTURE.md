# 架构说明

## 总览

当前项目是一个面向 CLI 的单体自动化应用，入口为 `app.php`。运行时以单个 `profile` 为边界，统一装配配置、设备参数、缓存、日志、请求客户端和插件调度。

当前主链路：

1. `Bootstrap` 解析命令参数并装配核心服务。
2. `Core` 初始化 profile 目录与运行时路径常量。
3. `Runtime` 暴露 `AppContext`，统一访问配置、设备、认证信息和路径。
4. `Plugin` 发现并注册内置插件。
5. `Console` 进入 `app / debug / doctor / profiles / restore / script` 模式。
6. `Scheduler` 调度插件执行 `runOnce(): TaskResult`。

## 主要模块

### 启动与运行时

- `app.php`
- `src/Bootstrap/Bootstrap.php`
- `src/Core/Core.php`
- `src/Runtime/*`
- `src/Profile/*`

职责：

- 解析当前 profile
- 初始化 `config / cache / log` 目录
- 统一提供 `AppContext`
- 约束 CLI 启动环境和 PHP 版本

### 插件系统

- `src/Plugin/Plugin.php`
- `src/Plugin/PluginDiscovery.php`
- `src/Plugin/PluginManifest.php`
- `src/Plugin/PluginManifestValidator.php`
- `src/Plugin/BasePlugin*.php`
- `plugin/*`
- `src/Login/Login.php`

当前插件模型：

- 业务插件统一走 `PluginTaskInterface`
- 统一执行入口为 `runOnce(): TaskResult`
- 登录能力已内置到 `src/Login/`，不再作为独立插件目录维护
- 插件发现支持目录内候选入口扫描
- 插件 manifest 已类型化到 `PluginManifest`
- 插件装配仍会根据扫描结果 `include_once` 目标入口文件，但已不再依赖 `class_alias()` 或全局 helper 桥接

### ActivityLottery

- `plugin/ActivityLottery/ActivityLottery.php`
- `plugin/ActivityLottery/Internal/Runtime/ActivityLotteryRuntime.php`
- `plugin/ActivityLottery/Internal/Flow/*`
- `plugin/ActivityLottery/Internal/Node/*`
- `plugin/ActivityLottery/Internal/Gateway/*`

当前 `ActivityLottery` 已重构为活动流引擎：

- 插件入口只负责配置装配，并把执行委托给 `ActivityLotteryRuntime::tick()`
- 每个活动按 `biz_date` 生成独立 flow，节点状态和上下文持久化到 cache
- 运行窗口固定为 `06:00:00 ~ 23:00:00`，窗口外直接调度到下一次窗口开始
- `watch_video` / `watch_live` 复用 `src/Automation/Watch/*` 公共组件，插件内只保留活动语义和编排

### 调度与执行

- `src/Scheduler/Scheduler.php`
- `src/Scheduler/ScheduledTask.php`
- `src/Scheduler/TaskResult.php`

当前调度器负责：

- 启动阶段按优先级注册插件
- 根据 `TaskResult` 决定下次执行时间
- 管理失败次数、冷却窗口和基础治理状态
- 支持 `mode:app` 与 `mode:debug` 的统一执行链

### 配置、设备与 profile

- `src/Config/*`
- `src/Device/Device.php`
- `resources/device/default.yaml`
- `profile/<name>/config/*`

当前规则：

- 用户主配置为 `profile/<name>/config/user.ini`
- 默认设备参数来自 `resources/device/default.yaml`
- profile 自定义设备仅支持：
  - `profile/<name>/config/device.override.yaml`
  - `profile/<name>/config/device.override+.yaml`
- 配置模板同步由 `ConfigTemplateSynchronizer` 负责，保证缺失字段自动补齐

### 状态存储

- `src/Cache/Cache.php`
- `src/Cache/SqliteCacheStore.php`
- `plugin/*/*State*.php`

当前状态策略：

- 默认状态库为 `profile/<name>/cache/cache.sqlite3`
- 登录态、插件状态和部分调度状态统一收口到 SQLite
- 老版本缓存文件会按 scope 首次访问时迁移
- `mode:restore` 默认只清缓存，不清登录态；`--purge-auth` 才会一起清理认证信息

### HTTP 与 API

- `src/Request/Request.php`
- `src/Http/*`
- `src/Api/*`

当前请求链：

- 仅保留 Amp HTTP 客户端单轨实现
- `Request` 统一封装重试、默认头、代理、SSL 校验和故障分类
- API 层仍以静态类为主，但关键链路已开始引入响应对象和服务拆分

### 日志与通知

- `src/Log/*`
- `src/Notice/*`

当前能力：

- 终端彩色日志
- 显式 `caller / plugin / task` 上下文
- 本地日志文件落盘
- 多通知渠道推送

## 当前架构特点

### 已经完成的收口

- `src/Helpers.php` 已移除
- `TimeLock / Schedule / TaskQueue / OpenTelemetry` 已移除
- 登录态不再依赖 `auth.key`
- 插件别名兼容层已移除
- 默认运行入口已收敛为 `php app.php`

### 仍然保留的技术债

- `Bootstrap / Core / Runtime / Plugin / Scheduler / Request / Log / Notice / Console` 仍以 `SingleTon` 装配
- `APP_* / PROFILE_*` 运行时常量仍由 `Core` 初始化并被部分核心模块直接读取
- 插件装配虽已类型化，但仍保留“扫描目录 -> 解析类 -> include 文件”的动态发现模型
- API 层仍大量返回数组，DTO 化尚未覆盖到全部热点链路

## 推荐阅读顺序

1. `app.php`
2. `src/Bootstrap/Bootstrap.php`
3. `src/Core/Core.php`
4. `src/Runtime/AppContext.php`
5. `src/Plugin/Plugin.php`
6. `src/Scheduler/Scheduler.php`
7. `src/Request/Request.php`
8. `docs/MIGRATION.md`
