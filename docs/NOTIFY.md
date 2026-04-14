## 关于推送

### 开关

```ini
[notify]
enable = false
filter_words =
```

### 推送单元

> 以下数据为示例(e.g.)，需要根据实际需求配置

**钉钉**

> 文档: https://open.dingtalk.com/document/orgapp/custom-robots-send-group-messages  
> 说明: 钉钉机器人 webhook 的 `access_token`  
> 说明: 若机器人开启了加签安全校验，当前实现暂不支持

```ini
; Dingtalk机器人|token|依赖USE_NOTIFY
[notify_dingtalk]
token = 566cc69da782ec****
```

**Telegram**

> 文档: https://core.telegram.org/bots/api#sendmessage  
> 说明: 如果开启 TGbot API 反代，填写url，否则为空使用默认api。  
> 说明: TG 推送的Token, xxx/bot{这是token部分}/xxxx  
> 说明: TG 推送的用户/群组/频道 ID

```ini
; Tele机器人|url(可选)|token|chatid|依赖USE_NOTIFY
[notify_telegram]
url = https://*.*.workers.dev/bot
bottoken = 1640****:AAGlV3****_FscZ-****
chatid = 390****
```

**PUSH PLUS**

> 文档: https://www.pushplus.plus/doc/guide/api.html  
> 说明: push plus++ 推送的 `token`

```ini
; Pushplus酱|token|依赖USE_NOTIFY
[notify_pushplus]
token = 566cc69da782ec****
```

**Server酱(Turbo版)**

> 文档: https://sct.ftqq.com/  
> 说明: Server 酱 Turbo 版本 key，SCT 开头的

```ini
; Server酱(Turbo版)|令牌Key|依赖USE_NOTIFY
[notify_sct]
sctkey = SCT566cc69da782ec****
```

**GoCqhttp**

> 文档: https://docs.go-cqhttp.org/api/  
> 说明: 推送的完整api, 包含`/send_private_msg`、`/send_group_msg` 等等完整后缀  
> 说明: 推送的AccessToken   
> 说明: 目标QQ号或者QQ群号，根据API调整

```ini
; GoCqhttp|url|token|目标qq|依赖USE_NOTIFY
[notify_gocqhttp]
url = "http://127.0.0.1:5700/send_private_msg"
token = 566cc69da782ec****
target_qq = 10086
```

**Debug(个人用)**

> 文档: https://localhost:8921/doc

```ini
; Debug|个人调试推送|url|token|
[notify_debug]
url = "https://localhost:8921/notify"
token = 566cc69da782ec****
```

**企业微信群机器人**

> 文档: https://developer.work.weixin.qq.com/document/path/99110  
> 说明: 推送的AccessToken

```ini
; 企业微信群机器人|token
[notify_we_com]
token = ec971f1d-****-4700-****-d9461e76****
```

**企业微信应用**

> 文档: https://developer.work.weixin.qq.com/document/90000/90135/91039 | https://developer.work.weixin.qq.com/document/90001/90143/90372  
> 说明: 企业 id    
> 说明: 应用的凭证密钥  
> 说明: 企业应用的 id
> 说明: 指定接收消息的成员，成员 ID 列表 默认为@all

```ini
; 企业微信应用消息|corp_id|corp_secret|agent_id|to_user
[notify_we_com_app]
corp_id = ****
corp_secret = ****
agent_id = ****
to_user = UserId1|UserId2|UserId3
```

**飞书**

> 文档: https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot  
> 说明: 飞书自定义机器人 webhook 的 `token`  
> 说明: 若机器人开启了签名校验，当前实现暂不支持

```ini
; 飞书机器人/依赖USE_NOTIFY
[notify_feishu]
token =
```

**Bark**

> 文档: https://github.com/Finb/Bark | https://bark.day.app/#/en-us/  
> 说明: Bark 推送使用的设备 `key`

```ini
; Bark/Token
[notify_bark]
token =
```

**PushDeer**

> 文档: https://www.pushdeer.com/official.html  
> 说明: `url` 为完整 push API 地址，可留空使用官方在线版默认地址  
> 说明: `token` 为 PushDeer 的 `pushkey`

```ini
; PushDeer/服务器地址/token
[notify_push_deer]
url =
token =
```

### 调试

https://github.com/lkeme/BiliHelper-personal/blob/eb06f55fa0fa6cb07bbeffc7e85c6ac0bfaa67b3/data/latest_version.json#L8

改成与线上不同的版本即可，检查新版本就会推送一次。  
