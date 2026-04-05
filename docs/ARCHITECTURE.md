# 架构说明

## 总览

当前项目是一个面向 CLI 的单体自动化应用，唯一入口为 `app.php`。运行时以单个 `profile` 为边界，通过显式 `AppKernel` 装配 `ProfileContext`、`AppContext`、核心服务、控制台命令和插件运行时。

当前主链路：

1. `app.php` 创建 `Bhp\App\AppKernel`。
2. `AppKernel::boot()` 解析 `profile` 和运行模式，创建 `ServiceContainer`。
3. `ProfileContext::fromAppRoot()` 计算 `profile/<name>/config|cache|log` 路径。
4. `Bootstrap` 完成环境检查与核心服务初始化。
5. `Console` 注册并执行 `mode:app`、`mode:debug`、`mode:script`。
6. `Plugin` 基于核心注册表和外部插件 manifest 装配插件，`Scheduler` 调度 `runOnce(): TaskResult`。

## 启动与运行时

关键文件：

- `app.php`
- `src/App/AppKernel.php`
- `src/App/ServiceContainer.php`
- `src/Bootstrap/Bootstrap.php`
- `src/Profile/ProfileContext.php`
- `src/Runtime/AppContext.php`
- `src/Runtime/RuntimeContext.php`

当前职责：

- 解析当前 `profile`
- 初始化 `config / cache / log` 目录
- 通过 `ServiceContainer` 统一装配核心依赖
- 通过 `AppContext` 暴露配置、设备、认证态和路径
- 约束 CLI 启动环境与 PHP 扩展

当前运行时不再依赖 `SingleTon` 装配，也不再通过 `APP_* / PROFILE_*` 路径常量传播目录信息。路径统一由 `ProfileContext` 和 `AppContext` 提供。

## 命令面

当前控制台只暴露三个模式：

- `mode:app` / `m:a`
- `mode:debug` / `m:d`
- `mode:script` / `m:s`

缓存清理选项：

- `--reset-cache` 在进入对应模式前清理当前 `profile` 的缓存
- `--purge-auth` 与 `--reset-cache` 联用时，同时清理登录态

命令实现位于：

- `src/Console/Console.php`
- `src/Console/Command/AppCommand.php`
- `src/Console/Command/DebugCommand.php`
- `src/Console/Command/ScriptCommand.php`
- `src/Profile/ProfileCacheResetService.php`

## 插件系统

关键文件：

- `src/Plugin/Plugin.php`
- `src/Plugin/CorePluginRegistry.php`
- `src/Plugin/ExternalPluginRegistry.php`
- `src/Plugin/BasePlugin.php`
- `src/Plugin/PluginManifestValidator.php`
- `src/Login/Login.php`

当前插件模型：

- 核心注册表只保留 `Login`
- 业务插件统一视为第三方插件，通过 `plugins/<plugin>/plugin.json` 发现
- 官方随仓库分发的第三方插件位于 `plugins/*`
- `Plugin` 只消费核心条目和外部 manifest 条目，不再扫描旧 `plugin/` 目录或依赖入口 glue
- 插件统一通过 manifest 校验后装配
- 调度型插件统一执行 `runOnce(): TaskResult`
- `mode:script` 只加载脚本插件；`mode:app` 和 `mode:debug` 不加载脚本插件

## ActivityLottery

关键文件：

- `plugins/ActivityLottery/src/ActivityLotteryPlugin.php`
- `plugins/ActivityLottery/src/Internal/Runtime/ActivityLotteryRuntime.php`
- `plugins/ActivityLottery/src/Internal/Flow/ActivityFlowStore.php`

当前 `ActivityLottery` 已重构为活动流引擎：

- 插件入口负责装配运行依赖，并委托 `ActivityLotteryRuntime::tick()`
- 每个活动按 `biz_date + flow_id` 落到 SQLite 行级记录
- `ActivityFlowStore` 使用 `activity_flow_entries` 表做 row-wise 持久化，而不是整日单 blob 文件
- 默认数据库位于 `profile/<name>/cache/cache.sqlite3`
- `watch_video` / `watch_live` 复用 `src/Automation/Watch/*` 公共组件

## 状态与配置

关键文件：

- `src/Cache/Cache.php`
- `src/Config/Config.php`
- `src/Device/Device.php`
- `resources/device/default.yaml`
- `profile/<name>/config/user.ini`

当前规则：

- 默认状态库为 `profile/<name>/cache/cache.sqlite3`
- 登录态、插件状态和部分调度状态统一收口到 SQLite
- 默认设备参数来自 `resources/device/default.yaml`
- profile 自定义设备仅支持：
  - `profile/<name>/config/device.override.yaml`
  - `profile/<name>/config/device.override+.yaml`
- 配置模板同步由 `ConfigTemplateSynchronizer` 负责，保证缺失字段自动补齐

## Docker 运行模型

关键文件：

- `docker/Dockerfile`
- `docker/entrypoint.sh`
- `docker-compose.yml`
- `docker-compose.local.yml`

当前规则：

- 生产 Docker 运行时默认不可变，容器启动时不会再拉取远程代码
- 生产更新方式为 `docker compose pull` 后再执行 `docker compose up -d`
- 本地开发使用 `docker-compose.local.yml`，通过 bind mount 提供源码
- 当镜像内容、扩展或依赖变更时，重新执行 `docker compose -f docker-compose.local.yml up --build -d`

## 推荐阅读顺序

1. `app.php`
2. `src/App/AppKernel.php`
3. `src/Profile/ProfileContext.php`
4. `src/Runtime/AppContext.php`
5. `src/Plugin/CorePluginRegistry.php`
6. `src/Plugin/ExternalPluginRegistry.php`
7. `src/Plugin/Plugin.php`
8. `src/Scheduler/Scheduler.php`
9. `docs/MIGRATION.md`
