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

> 文档: https://developers.dingtalk.com/document/robots/custom-robot-access  
> 说明: 钉钉推送的密钥

```ini
; Dingtalk机器人|token|依赖USE_NOTIFY
[notify.dingtalk]
token = 566cc69da782ec****
```

**Telegram**

> 文档: https://core.telegram.org/bots/api#sendmessage  
> 说明: 如果开启 TGbot API 反代，填写url，否则为空使用默认api。  
> 说明: TG 推送的Token, xxx/bot{这是token部分}/xxxx  
> 说明: TG 推送的用户/群组/频道 ID

```ini
; Tele机器人|url(可选)|token|chatid|依赖USE_NOTIFY
[notify.telegram]
url = https://*.*.workers.dev/bot
bottoken = 1640****:AAGlV3****_FscZ-****
chatid = 390****
```

**PUSH PLUS**

> 文档: http://www.pushplus.plus/doc/  
> 说明: push plus++ 推送的 `token`

```ini
; Pushplus酱|token|依赖USE_NOTIFY
[notify.pushplus]
token = 566cc69da782ec****
```

**Sever酱(原版)**

> 文档: https://sc.ftqq.com/  
> 说明: Server 酱老版本 key，SCU 开头的

```ini
; Sever酱(原版)|令牌Key|依赖USE_NOTIFY
[notify.sc]
sckey = SCU566cc69da782ec****
```

**Server酱(Turbo版)**

> 文档: https://sct.ftqq.com/  
> 说明: Server 酱 Turbo 版本 key，SCT 开头的

```ini
; Server酱(Turbo版)|令牌Key|依赖USE_NOTIFY
[notify.sct]
sctkey = SCT566cc69da782ec****
```

**GoCqhttp**

> 文档: https://docs.go-cqhttp.org/api/  
> 说明: 推送的完整api, 包含`/send_private_msg`、`/send_group_msg` 等等完整后缀  
> 说明: 推送的AccessToken   
> 说明: 目标QQ号或者QQ群号，根据API调整

```ini
; GoCqhttp|url|token|目标qq|依赖USE_NOTIFY
[notify.gocqhttp]
url = "http://127.0.0.1:5700/send_private_msg"
token = 566cc69da782ec****
target_qq = 10086
```

**Debug(个人用)**

> 文档: https://localhost:8921/doc

```ini
; Debug|个人调试推送|url|token|
[notify.debug]
url = "https://localhost:8921/notify"
token = 566cc69da782ec****
```

**企业微信群机器人**

> 文档: https://open.work.weixin.qq.com/api/doc/90000/90136/91770 | https://weibanzhushou.com/blog/330  
> 说明: 推送的AccessToken

```ini
; 企业微信群机器人|token
[notify.we_com]
token = ec971f1d-****-4700-****-d9461e76****
```

**企业微信应用**

> 文档: https://open.work.weixin.qq.com/wwopen/devtool/interface?doc_id=10167  
> 说明: 企业 id    
> 说明: 应用的凭证密钥  
> 说明: 企业应用的 id
> 说明: 指定接收消息的成员，成员 ID 列表 默认为@all

```ini
; 企业微信应用消息|corp_id|corp_secret|agent_id|to_user
[notify.we_com_app]
corp_id = ****
corp_secret = ****
agent_id = ****
to_user = UserId1|UserId2|UserId3
```

### 调试

https://github.com/lkeme/BiliHelper-personal/blob/eb06f55fa0fa6cb07bbeffc7e85c6ac0bfaa67b3/data/latest_version.json#L8

改成与线上不同的版本即可，检查新版本就会推送一次。  