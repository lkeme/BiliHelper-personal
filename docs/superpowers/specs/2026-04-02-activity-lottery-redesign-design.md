# ActivityLottery 全新重构设计

## 背景

当前 `ActivityLottery` 插件同时承载了活动目录读取、ERA 页面解析、任务执行、抽奖次数领取、抽奖执行、状态恢复、池调度与节流控制等多类职责，主文件体量过大，状态边界不清，运行逻辑难以验证。现状已经出现典型问题：活动索引本身可用，但运行时抽奖链路仍可能因为历史兼容逻辑误判活动不可用。

本次设计不保留旧兼容层，不以“修补老代码”为目标，而是定义一套新的、可扩展的活动任务流引擎，用于替换现有插件实现。

## 目标

- 以“单个活动”为单位建模为一条独立任务流。
- 在单个活动内部按严格顺序推进节点。
- 支持多个活动流在同一天内交错推进。
- 支持中恢复：PHP CLI 进程重启后可恢复到节点级状态。
- 以每日为硬边界：跨天直接废弃旧流，第二天重新建流。
- 通过池调度和请求治理在“尽量快”和“避免风控”之间取得保守平衡。
- 为未来新增任务能力或新增活动来源保留清晰扩展点。

## 非目标

- 不保留旧 `ActivityLottery` 的 trait 结构和历史兼容路径。
- 不实现强恢复，不恢复视频/直播旧会话内部瞬时状态。
- 不在第一版中把所有动作能力都抽成公共组件。
- 不让插件等待人工介入处理任务。
- 不让活动任务跨天继承执行。

## 约束与前提

- 插件运行窗口固定为 `06:00:00` 到 `23:00:00`。
- 每日任务流乱序生成，避免固定活动长期卡在相同时间点启动。
- 活动流内部采用严格顺序，不做“先抽一轮再回去跑任务”。
- 当前自动能力采用写死白名单。
- 第一版公共组件仅抽取 `watch_video` 与 `watch_live`。
- `follow` 与 `share` 保留在插件内部节点执行层。
- 开发阶段活动目录只读本地 `resources/activity_infos.json`。
- 正式阶段活动目录支持“远程拉取 + 本地合并去重”。
- 同一活动多来源冲突时按 `update_time` 新者优先。

## 术语

- 活动目录：活动来源数据的统一视图，来自本地、远程或两者合并。
- 活动流：某个活动在某一天的完整执行链。
- 节点：活动流中的单个阶段动作。
- 等待态：节点已发起动作，但需在未来某个时间点再次进入。
- 车道：某类危险动作的全局节流通道，例如关注、抽奖、领奖。

## 总体架构

新插件仍使用 `ActivityLottery` 作为插件入口类，但入口类只负责驱动活动流引擎，不直接承载业务细节。引擎由五类核心对象构成：

1. 目录层
   负责读取、合并、过滤活动目录。
2. 流模型层
   负责活动流、节点、上下文、状态持久化与恢复。
3. 规划层
   负责将活动目录项转换为严格顺序的节点链。
4. 池调度层
   负责在一轮调度中选择哪些活动流可以推进、推进几个步骤、何时让出控制权。
5. 节点执行层
   每类节点由独立 runner 执行，只负责单一节点语义。

插件主入口不再直接处理：

- ERA 页面解析细节
- 任务节点执行细节
- 抽奖链路细节
- 节流与预算的底层实现

## 活动目录设计

活动目录统一抽象为目录源链：

- `LocalCatalogSource`
- `RemoteCatalogSource`

目录加载器对外只返回一份去重后的活动目录列表。

### 唯一键规则

同一活动的判定优先使用以下字段：

1. `activity_id`
2. `page_id`
3. `lottery_id`
4. `url`

### 合并规则

- 同唯一键活动冲突时，优先保留 `update_time` 更新的记录。
- 若一方缺失 `update_time`，另一方优先。
- 若双方都缺失 `update_time`，本地记录优先，便于临时修正远程脏数据。

### 阶段划分

- 开发阶段：仅启用本地目录源。
- 正式阶段：启用远程目录源和本地目录源，并完成合并去重。

## 活动流模型

每条活动流至少包含以下字段：

- `flow_id`
- `biz_date`
- `activity`
- `status`
- `current_node_index`
- `nodes`
- `next_run_at`
- `attempts`
- `context`
- `logs`
- `created_at`
- `updated_at`

### 状态定义

活动流状态：

- `pending`
- `running`
- `blocked`
- `completed`
- `skipped`
- `expired`
- `failed`

节点状态：

- `pending`
- `running`
- `waiting`
- `succeeded`
- `skipped`
- `failed`

### 中恢复边界

中恢复的恢复粒度是节点级，不是会话级。

恢复时只要求能找回：

- 当前流在哪个节点
- 节点当前状态
- 下次运行时间
- 节点上下文

对于视频和直播节点：

- 只恢复到“该节点待继续”这一层
- 不恢复旧心跳会话或旧观看会话
- 节点重新进入后重建观看链路

## 节点生命周期

单个节点统一走以下状态机：

- `pending -> running -> succeeded`
- `pending -> running -> waiting -> running -> succeeded`
- `pending -> running -> skipped`
- `pending -> running -> failed`

### 含义

- `succeeded`
  当前节点完成，活动流推进到下一个节点。
- `waiting`
  当前节点未来还要继续，例如等待观看时长、直播心跳、任务状态同步。
- `skipped`
  当前节点不做，但不阻塞活动流。
- `failed`
  当前节点达到最大重试次数后失败。

默认策略是：

- 节点失败导致活动流失败
- 但人工节点、未支持能力节点、显式跳过节点除外

## 节点链设计

每个活动流固定分为四个阶段。

### 阶段 A：准备阶段

固定节点：

1. `load_activity_snapshot`
2. `validate_activity`
3. `parse_era_page`

其中：

- `validate_activity` 只负责基础校验，例如时间窗口、目录字段完整性、抽奖后端基础可用性。
- `parse_era_page` 单独负责 ERA 页面解析与任务快照抽取。

### 阶段 B：ERA 任务阶段

该阶段根据活动页面解析结果动态展开节点，但节点顺序固定。

第一版白名单能力：

- `follow`
- `share`
- `watch_video`
- `watch_live`
- `claim_reward`

对应节点：

- `era_follow_task`
- `era_share_task`
- `era_watch_video_task`
- `era_watch_live_task`
- `era_claim_reward_task`

非白名单能力：

- 生成 `era_skip_unsupported_task`
- 状态直接为 `skipped`
- 记录原因“当前版本未实现自动化能力”

同一活动内推荐顺序：

1. `claim_reward_ready`
2. `share`
3. `follow`
4. `watch_video`
5. `watch_live`
6. `claim_reward_after_progress`

同类任务存在多个目标时，不再拆分成多个流级节点，而是在单节点内部维护子步骤游标。

### 阶段 C：抽奖阶段

固定节点：

1. `refresh_draw_times`
2. `execute_draws`
3. `record_draw_result`
4. `notify_draw_result`

抽奖阶段只能在 ERA 任务阶段结束后进入。

### 阶段 D：收尾阶段

固定节点：

1. `finalize_flow`
2. `cleanup_runtime`

## 自动能力策略

第一版使用写死白名单，不开放未知能力开关。

### 公共组件

抽到 `src/` 的公共能力：

- `watch_video`
- `watch_live`

建议放置为：

- `src/Automation/Watch/VideoWatchService.php`
- `src/Automation/Watch/VideoWatchSession.php`
- `src/Automation/Watch/LiveWatchService.php`
- `src/Automation/Watch/LiveWatchSession.php`

### 插件内能力

保留在插件内部节点层：

- `follow`
- `share`

理由：

- 单步动作，无复杂会话状态
- 业务语义差异大
- 当前复用收益低于抽象成本

## FlowPool 设计

`FlowPool` 采用“虚拟池 + 协作式推进”，不是插件内部真并发线程池。

### 基本原则

- 每次 `runOnce()` 只推进一小段工作。
- 每条活动流每轮最多推进一个节点步骤。
- 达到预算后立即返回，让调度器接管下一轮推进。

### 每轮预算

建议第一版保守预算：

- `max_flows_per_tick = 4`
- `max_steps_per_tick = 6`
- `max_runtime_ms_per_tick = 2500~3000`

### 公平性

- 当日建流前先乱序。
- `FlowPool` 维护游标，下一轮从游标后继续选取。
- 不允许单流连续吃掉整轮预算。

## 请求治理设计

请求治理分两层。

### 第一层：插件内软治理

插件自己控制：

- 一轮推进预算
- 单节点单步推进
- 节点等待态
- 危险动作车道冷却

### 第二层：底层硬治理

继续依赖现有基础设施：

- HTTP 请求治理拦截器
- Scheduler 的治理退避
- host 维度窗口与 cooldown

## 车道与频率控制

重复动作必须拆成子步骤，并进入全局动作车道。

建议至少定义以下车道：

- `page_fetch`
- `task_status`
- `follow`
- `unfollow`
- `draw_refresh`
- `draw_execute`
- `claim_reward`

### 第一版保守频率

- `follow/unfollow`
  每次一个目标，间隔 `10~20` 秒随机。
- `draw_refresh`
  每次一个活动，间隔 `5~10` 秒随机。
- `draw_execute`
  每次只抽一次，间隔 `8~15` 秒随机。
- `claim_reward`
  每次一个奖励，间隔 `10~20` 秒随机。
- `task_status`
  同一任务状态查询间隔 `30~120` 秒，按节点类型区分。

## 每日边界与调度策略

### 运行窗口

- `06:00:00` 前不推进活动流
- `23:00:00` 后不再推进当天活动流

### 当天初始化

每天进入窗口后：

1. 清理旧 `biz_date` 流
2. 重新加载活动目录
3. 乱序建流

### 跨天策略

- 若流的 `biz_date` 不是今天，直接标记 `expired`
- 昨日未完成流直接废弃，不继承到第二天
- 第二天重新建全新流

### 调度返回策略

- 仍有可推进流：`TaskResult::after(10~30秒随机)`
- 只有等待中长任务：根据最近 `next_run_at` 计算，设置最小和最大夹逼
- 当天全部完成：`TaskResult::nextDayAt(6, 随机分钟)`
- 超出运行窗口：直接调度到下一次窗口开始

## 失败、跳过与风控策略

### 活动级失败

以下情况直接终止整个活动流：

- 活动页不存在
- 活动时间窗口失效
- 抽奖后端确认不可用
- 准备阶段 ERA 页面解析失败且该活动依赖 ERA 任务链

### 节点级失败

以下情况允许节点级重试：

- 网络异常
- 限流
- 初始化失败但语义不确定

达到最大次数后节点失败，活动流失败。

### 节点级跳过

以下情况直接 `skipped`：

- 需要人工介入
- 能力不在白名单
- 当前活动不支持该自动化能力

### 风控处理

明确风控或高风险拒绝时：

- 当前节点直接 `skipped`
- 给活动流打风险标记
- 禁止该流继续执行同类高风险动作

## 日志与通知策略

### 日志模型

日志围绕“活动流 + 节点”输出，不再打印散装动作日志。

每条核心日志至少包含：

- `flow_id`
- `activity_title`
- `node_type`
- `node_status`

### 日志级别

- `INFO`
  流创建、节点开始、节点等待、节点完成
- `NOTICE`
  抽奖命中、领奖成功、关键结果
- `WARNING`
  节点跳过、节点失败、活动失效、风控命中

### 日志去重

- 同一等待原因设置去重窗口
- 连续等待只在足够间隔后重复打印
- 抽奖未命中按活动汇总，避免刷屏

### 通知策略

- 仅在关键结果时通知
- 第一版至少通知：
  - 抽奖命中
  - 奖励领取成功
  - 需要关注的高风险跳过

## 模块拆分

建议目录结构：

```text
plugin/ActivityLottery/
  ActivityLottery.php
  Internal/
    Catalog/
      ActivityCatalogItem.php
      ActivityCatalogLoader.php
      CatalogSourceInterface.php
      LocalCatalogSource.php
      RemoteCatalogSource.php
    Flow/
      ActivityFlow.php
      ActivityFlowContext.php
      ActivityFlowStatus.php
      ActivityFlowFactory.php
      ActivityFlowPlanner.php
      ActivityFlowStore.php
      ActivityNode.php
      ActivityNodeResult.php
      ActivityNodeStatus.php
    Pool/
      ActivityFlowPool.php
      ActivityFlowPicker.php
      ActivityFlowBudget.php
      ActivityLaneLimiter.php
    Runtime/
      ActivityLotteryRuntime.php
      ActivityLotteryClock.php
      ActivityLotteryWindow.php
    Support/
      ActivityCapability.php
      ActivityRandomizer.php
      ActivityLogger.php
    Page/
      EraPageSnapshot.php
      EraPageParser.php
      EraTaskSnapshot.php
      EraTaskCapabilityResolver.php
    Node/
      NodeRunnerInterface.php
      LoadActivitySnapshotNodeRunner.php
      ValidateActivityNodeRunner.php
      ParseEraPageNodeRunner.php
      EraFollowNodeRunner.php
      EraShareNodeRunner.php
      EraWatchVideoNodeRunner.php
      EraWatchLiveNodeRunner.php
      EraClaimRewardNodeRunner.php
      RefreshDrawTimesNodeRunner.php
      ExecuteDrawNodeRunner.php
      RecordDrawResultNodeRunner.php
      NotifyDrawResultNodeRunner.php
      FinalizeFlowNodeRunner.php
    Gateway/
      ActivityLotteryGateway.php
      EraTaskGateway.php
      DrawGateway.php
      WatchVideoGateway.php
      WatchLiveGateway.php
```

## 验收标准

- 新插件不再依赖旧 `ActivityLottery` 的 trait 流程。
- 每个活动按独立流建模，状态可持久化到每日范围。
- 插件重启后可恢复到节点级状态。
- 跨天后旧流自动废弃。
- 同天活动流乱序生成。
- 活动流内部严格顺序推进。
- 风险动作具备插件内频率控制与车道限速。
- 观看视频与观看直播能力抽为公共组件。
- 日志能清晰反映活动流与节点推进状态。
- 正式阶段可扩展为“远程拉取 + 本地合并去重”的目录链。
