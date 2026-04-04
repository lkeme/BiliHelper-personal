# Login Audit And Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 系统性审查并加固 `Login` 插件与调度器协作链路，消除异步化后出现的登录竞态、未登录抢跑、重登失效、远程无人值守场景失控等问题。

**Architecture:** 以“全局登录状态机 + 调度器登录门闩 + 鉴权失败归一化 + 远程托管处置策略”为主线，把 `Login` 从“一个普通插件”提升为“全局认证协调器”。先补审查与观测，再收口状态定义，最后统一业务插件登录失败上报与调度器响应。

**Tech Stack:** PHP 8.x, 自研 `Scheduler`, 插件系统, `Cache` 状态存储, `Request/Api*` 请求封装, 本地测试脚本。

---

### Task 1: 盘点登录主链与状态定义

**Files:**
- Modify: `docs/superpowers/plans/2026-04-04-login-audit-hardening.md`
- Review: `src/Login/Login.php`
- Review: `src/Login/LoginSessionCoordinator.php`
- Review: `src/Login/LoginTokenLifecycleService.php`
- Review: `src/Login/LoginPendingFlowFactory.php`
- Review: `src/Login/LoginPendingFlowLifecycleService.php`
- Review: `src/Login/LoginPendingFlowResumeService.php`
- Review: `src/Login/LoginRuntimeState.php`
- Review: `src/Login/LoginPendingFlowStore.php`
- Review: `src/Login/LoginPendingFlowStateService.php`

- [ ] **Step 1: 明确登录状态机枚举**

定义至少这些状态：
`missing_auth`、`auth_ready`、`token_expiring`、`refreshing`、`pending_manual_intervention`、`manual_in_progress`、`failed_unrecoverable`

- [ ] **Step 2: 记录当前代码中的真实状态来源**

重点记录：
- `hasLoginTokens()` 只看 `access_token/refresh_token`
- `Request` 实际使用 `cookie/pc_cookie`
- `pending_login_flow` 只代表“挂起流程”，不代表“全局未登录”

- [ ] **Step 3: 形成状态判定表**

输出一个矩阵：
- token 有/无
- cookie 有/无
- pc_cookie 有/无
- pending_flow 有/无
- token 校验成功/失败
- myInfo 成功/失败

并给出每种组合应进入的目标状态。

- [ ] **Step 4: 标出当前状态定义不一致的位置**

必须记录的高风险点：
- `src/Login/Login.php`
- `src/Request/Request.php`
- `src/Runtime/AppContext.php`

- [ ] **Step 5: 提交审查结论**

提交信息建议：
`docs: record login state machine audit`

### Task 2: 审查调度器对登录状态的全局门闩

**Files:**
- Review: `src/Scheduler/Scheduler.php`
- Review: `src/Scheduler/SchedulerStateStore.php`
- Review: `src/Plugin/Plugin.php`
- Review: `src/Login/LoginBuiltinBootstrapper.php`
- Test: `tests/Login/` 下新增集成测试文件

- [ ] **Step 1: 画出现有调度器启动顺序**

确认：
- `Login` 是唯一 `bootstrap_first`
- `runInitialRound()` 是否能确保其他任务在首轮不会先跑
- `tick()` 阶段是否只在 `pending_login_flow` 时阻塞其他任务

- [ ] **Step 2: 写失败测试，复现“未登录态其他插件抢跑”**

测试目标：
- `Login` 缺 token 且无 pending flow
- 其他插件当前仍被调度执行

- [ ] **Step 3: 写失败测试，复现“业务插件抛 NoLoginException 后不触发 Login 立即重跑”**

测试目标：
- 业务插件抛 `NoLoginException`
- 调度器只是让业务插件 `after(3600)`，没有重置 `Login.nextRunAt`

- [ ] **Step 4: 定义登录门闩判定接口**

建议新增一个中心化服务，例如：
- `src/Login/LoginGateStateService.php`

职责：
- 判断当前是否允许业务插件执行
- 判断当前是否应强制优先调度 Login

- [ ] **Step 5: 约束调度器门闩行为**

目标规则：
- `Login` 永不被门闩阻塞
- 任何“认证未就绪”状态都应阻塞其他插件
- 任何插件抛 `NoLoginException` 都应触发 `Login` 尽快调度

- [ ] **Step 6: 运行调度器相关测试**

Run: `php tests/Login/SchedulerLoginGateTest.php`
Expected: PASS

- [ ] **Step 7: 提交**

提交信息建议：
`test: cover scheduler login gate scenarios`

### Task 3: 统一“已登录”定义到请求依赖面

**Files:**
- Review: `src/Login/Login.php`
- Review: `src/Login/LoginTokenLifecycleService.php`
- Review: `src/Login/LoginCookieMaintenanceService.php`
- Review: `src/Login/LoginCookiePatchService.php`
- Review: `src/Request/Request.php`
- Review: `src/Runtime/AppContext.php`
- Test: `tests/Login/LoginReadinessTest.php`

- [ ] **Step 1: 写失败测试，复现“token 就绪但 cookie 不可用”**

测试目标：
- `access_token/refresh_token` 有值
- `cookie/pc_cookie` 缺失或坏值
- 系统仍错误认为“登录完成”

- [ ] **Step 2: 定义统一的 `auth_ready` 判定**

建议规则至少包含：
- `access_token`
- `refresh_token`
- `cookie`
- `uid`
- `csrf`

是否要求 `pc_cookie` 取决于项目中 PC 接口覆盖面，可作为增强态定义。

- [ ] **Step 3: 评估 CookiePatch 的阻断等级**

明确：
- `buvid3` 缺失只是降级警告
- 还是必须阻断业务插件运行

- [ ] **Step 4: 在 Login 主链中替换零散判定**

把：
- `hasLoginTokens()`
- `assertLoginReady()`

改造成统一依赖 `auth_ready` 判定服务。

- [ ] **Step 5: 运行定向测试**

Run: `php tests/Login/LoginReadinessTest.php`
Expected: PASS

- [ ] **Step 6: 提交**

提交信息建议：
`refactor: unify login readiness checks`

### Task 4: 统一业务侧鉴权失败归一化

**Files:**
- Review: `plugin/ActivityLottery/Internal/Node/*`
- Review: `plugin/MainSite/*`
- Review: `plugin/Manga/*`
- Review: `plugin/LoveClub/*`
- Review: `plugin/VipPrivilege/*`
- Review: `plugin/Silver2Coin/*`
- Review: `plugin/VipPoint/*`
- Review: `plugin/LiveGoldBox/*`
- Review: `src/Automation/*`
- Create: `src/Auth/AuthFailureClassifier.php`
- Test: `tests/Login/AuthFailurePropagationTest.php`

- [ ] **Step 1: 盘点所有鉴权失败码与报文**

至少收敛：
- `-101`
- `-111`
- `账号未登录`
- `user not login`
- `csrf 校验失败`

- [ ] **Step 2: 写失败测试，复现新链路未上抛登录异常**

样本优先：
- `ActivityLottery`
- `VipPoint`
- `Automation` 复用组件

- [ ] **Step 3: 新增统一鉴权失败分类器**

职责：
- 输入 API 响应
- 输出 `auth_missing` / `csrf_invalid` / `not_auth_issue`

- [ ] **Step 4: 在业务节点中优先识别登录失败**

原则：
- 业务失败继续走原逻辑
- 认证失败必须抛 `NoLoginException` 或返回调度器可识别结果

- [ ] **Step 5: 审查“静默 false/empty object”路径**

像 `UserProfileService`、部分 helper 当前会吞掉错误，只记 warning。
要确认哪些场景需要继续吞，哪些场景必须提升为认证失败。

- [ ] **Step 6: 运行回归**

Run: `php tests/Login/AuthFailurePropagationTest.php`
Expected: PASS

- [ ] **Step 7: 提交**

提交信息建议：
`refactor: normalize auth failure propagation`

### Task 5: 审查挂起登录流程与人工介入语义

**Files:**
- Review: `src/Login/LoginPendingFlowFactory.php`
- Review: `src/Login/LoginPendingFlowLifecycleService.php`
- Review: `src/Login/LoginFlowController.php`
- Review: `src/Login/Login.php`
- Create: `src/Login/LoginManualInterventionPolicy.php`
- Test: `tests/Login/LoginManualInterventionTest.php`

- [ ] **Step 1: 定义“人工介入中”状态**

需要明确区分：
- 系统自动可恢复
- 等待验证码/扫码/短信
- 人工正在处理
- 人工超时未处理

- [ ] **Step 2: 写失败测试，复现“远程无人值守一直等待”**

测试目标：
- pending flow 长时间存在
- 系统一直重试，不退出、不通知

- [ ] **Step 3: 写失败测试，复现“人工正在处理时被误判为失败”**

测试目标：
- 在 TTL 内继续轮询
- 不应过早 purge 状态或触发退出

- [ ] **Step 4: 设计人工介入策略**

建议至少配置：
- `login_policy.manual_intervention_grace_seconds`
- `login_policy.unattended_exit_on_pending`
- `login_policy.notify_on_pending`
- `login_policy.notify_on_unrecoverable`

- [ ] **Step 5: 给 pending flow 增补元数据**

建议增加：
- `created_at`
- `last_seen_at`
- `intervention_deadline`
- `operator_note`（可选）
- `origin`（account_captcha/sms/qrcode）

- [ ] **Step 6: 规定超时后的统一动作**

建议规则：
- 宽限期内：继续等待并通知
- 超过宽限期且无人值守：退出进程或进入 fail-closed
- 人工已标记“介入中”：延长等待窗口

- [ ] **Step 7: 运行测试**

Run: `php tests/Login/LoginManualInterventionTest.php`
Expected: PASS

- [ ] **Step 8: 提交**

提交信息建议：
`feat: add login manual intervention policy`

### Task 6: 审查异步在途任务的认证快照与中断策略

**Files:**
- Review: `src/Scheduler/Scheduler.php`
- Review: `src/Runtime/AppContext.php`
- Review: `src/Request/Request.php`
- Review: `src/Login/Login.php`
- Create: `tests/Login/LoginConcurrentTransitionTest.php`

- [ ] **Step 1: 写失败测试，复现“Login 清空 auth 时已有任务继续运行”**

测试目标：
- 业务任务已启动
- `Login` 进入 `invalidateSessionAuth()`
- 在途任务继续发请求

- [ ] **Step 2: 选择策略并记录取舍**

候选方案：
- 方案 A：严格阻断新任务，不处理中断老任务
- 方案 B：请求层读取认证快照，在单任务生命周期内保持一致
- 方案 C：支持软中断/取消

推荐先做 A + B，避免一次过度设计。

- [ ] **Step 3: 如果采用快照，定义快照边界**

至少明确：
- 一个插件 tick 内认证是否固定
- `Request` 是每次实时读 auth，还是读任务启动时快照

- [ ] **Step 4: 运行并发测试**

Run: `php tests/Login/LoginConcurrentTransitionTest.php`
Expected: PASS

- [ ] **Step 5: 提交**

提交信息建议：
`test: cover login concurrent auth transitions`

### Task 7: 增强登录与调度可观测性

**Files:**
- Modify: `src/Login/Login.php`
- Modify: `src/Scheduler/Scheduler.php`
- Modify: `src/Login/LoginPendingFlowLifecycleService.php`
- Create: `tests/Login/LoginObservabilityTest.php`

- [ ] **Step 1: 统一日志事件名**

建议增加：
- `login.state.change`
- `login.pending.begin`
- `login.pending.resume`
- `login.pending.timeout`
- `scheduler.task.held_by_login`
- `scheduler.login.rearm`

- [ ] **Step 2: 给日志补关键字段**

至少包含：
- `login_state`
- `pending_type`
- `retry_after_seconds`
- `hook`
- `reason`
- `auth_ready`

- [ ] **Step 3: 运行日志测试**

Run: `php tests/Login/LoginObservabilityTest.php`
Expected: PASS

- [ ] **Step 4: 提交**

提交信息建议：
`feat: add login and scheduler observability`

### Task 8: 建立远程托管处置策略

**Files:**
- Modify: `src/Scheduler/Scheduler.php`
- Modify: `src/Login/Login.php`
- Modify: `src/Notice/*`（若需要）
- Create: `tests/Login/LoginRemoteOpsPolicyTest.php`
- Docs: `docs/LOGIN_REMOTE_POLICY.md`

- [ ] **Step 1: 明确远程服务运行模式**

定义两个模式：
- `interactive`
- `unattended`

- [ ] **Step 2: 写失败测试，复现“无人值守时挂起但不退出”**

- [ ] **Step 3: 为无人值守模式定义动作**

建议：
- 自动恢复失败且需人工介入时，发送通知
- 超过宽限期后退出进程，交给外部 supervisor 重启
- 或设置为 fail-closed，阻止其他插件继续跑

- [ ] **Step 4: 为人工介入中场景定义豁免**

建议：
- 有显式“介入中”标记时不立即退出
- 但要持续发提醒

- [ ] **Step 5: 运行测试**

Run: `php tests/Login/LoginRemoteOpsPolicyTest.php`
Expected: PASS

- [ ] **Step 6: 提交**

提交信息建议：
`feat: add remote login failure handling policy`

### Task 9: 建立完整测试矩阵

**Files:**
- Create: `tests/Login/`
- Docs: `docs/LOGIN_TEST_MATRIX.md`

- [ ] **Step 1: 建立测试矩阵文档**

至少覆盖：
- 冷启动无 token
- token 存在但 cookie 失效
- pending flow 存在
- pending flow 超时
- 业务插件抛 `NoLoginException`
- 人工介入中
- 无人值守超时退出

- [ ] **Step 2: 串联一条端到端脚本**

Run: `php tools/run-login-tests.php`
Expected: `login-tests-ok`

- [ ] **Step 3: 提交**

提交信息建议：
`test: add login end-to-end matrix`
