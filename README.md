<p align=center><img width="300px" src="https://user-images.githubusercontent.com/19500576/118621710-36428180-b7f9-11eb-891d-3f5697347cef.png" alt=""></p>

[//]: # (<p align=center><img width="300px" src="https://i.loli.net/2018/04/20/5ad97bd395912.jpeg"></p>)

<div align=center> 

[![](https://img.shields.io/badge/Author-Lkeme-blueviolet "作者")](https://github.com/lkeme/ )
![](https://img.shields.io/badge/dynamic/json?label=GitHub%20Followers&query=%24.data.totalSubs&url=https%3A%2F%2Fapi.spencerwoo.com%2Fsubstats%2F%3Fsource%3Dgithub%26queryKey%3Dlkeme&labelColor=282c34&color=181717&logo=github&longCache=true "关注数量")
![](https://img.shields.io/github/stars/lkeme/BiliHelper-personal.svg?style=plastic&logo=appveyor "Star数量")
![](https://img.shields.io/github/forks/lkeme/BiliHelper-personal.svg?style=plastic&logo=stackshare "Fork数量")
![](https://img.shields.io/github/issues/lkeme/BiliHelper-personal.svg?style=plastic&logo=stackshare "Issues数量")
![](https://img.shields.io/github/contributors/lkeme/BiliHelper-personal "贡献者")
![](https://img.shields.io/github/repo-size/lkeme/BiliHelper-personal?style=flat-square&label=files&color=cf8ef4&labelColor=373e4dl "文件大小")
![](https://img.shields.io/github/languages/code-size/lkeme/BiliHelper-personal?color=blueviolet&style=flat-square "代码大小")
[![Docker Pulls](https://img.shields.io/docker/pulls/lkeme/bilihelper-personal?style=flat-square)](https://hub.docker.com/r/lkeme/bilihelper-personal)

[//]: # (<br>)

[//]: # (<img alt="GitHub Workflow Status" src="https://img.shields.io/github/workflow/status/lkeme/BiliHelper-personal/cron%20update?style=flat-square">)

[//]: # (<img alt="GitHub last commit" src="https://img.shields.io/github/last-commit/lkeme/BiliHelper-personal/main?style=flat-square">)

[//]: # (<img alt="GitHub commit activity" src="https://img.shields.io/github/commit-activity/w/lkeme/BiliHelper-personal/main?style=flat-square">  )

[//]: # (<img alt="GitHub commit activity" src="https://data.jsdelivr.com/v1/package/gh/lkeme/BiliHelper-personal/badge?style=rounded&style=flat-square">  )

[//]: # (<br>)
</div>

<p align=center>

<img src="https://img.shields.io/badge/Version-2.5.1.250904-orange.svg?longCache=true&style=for-the-badge" alt="">
<img src="https://img.shields.io/badge/PHP-8.5+-green.svg?longCache=true&style=for-the-badge" alt="">
<img src="https://img.shields.io/badge/Composer-latest-blueviolet.svg?longCache=true&style=for-the-badge" alt="">
<img src="https://img.shields.io/badge/License-mit-blue.svg?longCache=true&style=for-the-badge" alt="">

</p>

## 📌 公告通知

代码开源，本地化99.9%，项目不收集或使用任何敏感信息，兴趣所致，一切只为学习。

```notice
---- 免费的东西总是得不到人的珍惜。
---- 只有花大价钱去买到的东西，才会令人信任。
---- 本项目仅供学习交流使用，请勿用于非法用途！* 3
```

## 👤 游客访问

<p align=center> 
   <img align=center src="https://count.getloli.com/get/@:BiliHelper-personal"  alt=":BiliHelper-personal"/>
</p>

## 🖨️ 相关文档

有疑问一定要先看看文档或Issue里是否存在相同的问题，再考虑其他渠道咨询。

* [使用文档 / DOC.md](./docs/DOC.md)
* [验证码文档 / CAPTCHA.md](./docs/CAPTCHA.md)
* [推送文档 / NOTIFY.md](./docs/NOTIFY.md)
* [更新日志 / CHANGELOG.md](./docs/CHANGELOG.md)
* [配置文档 / WIKI.md](https://github.com/lkeme/BiliHelper-personal/wiki/%E9%85%8D%E7%BD%AE%E6%96%87%E4%BB%B6%E8%AF%A6%E8%A7%A3)
* [常见问题 / WIKI.md](https://github.com/lkeme/BiliHelper-personal/wiki/%E5%B8%B8%E8%A7%81%E9%97%AE%E9%A2%98)
* [关于项目 / ABOUT.md](./docs/ABOUT.md)

## 当前运行模型

- 入口为 `app.php`，当前启动链为 `AppKernel -> ServiceContainer -> Bootstrap -> Console`
- 当前只保留 `mode:app`、`mode:debug`、`mode:script`
- `mode:app`、`mode:debug`、`mode:script` 都支持 `--reset-cache`，需要同时清理登录态时追加 `--purge-auth`
- `profile/example` 仅作为模板目录使用，不能直接作为运行 profile
- `--help` 与 `mode:script --list` 属于只读命令，不会初始化 profile 或生成缓存文件
- 核心只保留 `Login`，其余业务插件统一从 `plugins/<plugin>/plugin.json` 发现并装配
- 官方随仓库分发的第三方插件位于 `plugins/*`
- `ActivityLottery` 当前通过 `ActivityFlowStore` 将 flow 行级写入 `profile/<name>/cache/cache.sqlite3`
- 示例配置当前包含 `request_governance` 区段，`mode` 只支持 `observe` 与 `enforce`

## Docker 提示

- 生产环境 Docker 运行时默认保持镜像不可变，容器启动时不会再执行依赖刷新。
- 首次生成 profile 请显式执行 `entrypoint.sh init_profile`，或直接挂载已经准备好的 profile。
- 更新镜像请使用 `docker compose pull && docker compose up -d`
- 如需在 Docker 默认启动前清理缓存，可设置环境变量：
  - `RESET_CACHE=1`：等价于追加 `--reset-cache`
  - `RESET_CACHE=1` 且 `PURGE_AUTH=1`：等价于追加 `--reset-cache --purge-auth`
- 上述环境变量只对默认 `entrypoint.sh run` 启动路径生效，不会改写用户自定义命令


## 🎁 打赏支持

如果觉得本项目好用，对你有所帮助，欢迎打赏支持本项目，请作者喝杯奶茶可乐哦。

<p align=center><img width="680px" src="https://user-images.githubusercontent.com/19500576/118621834-55d9aa00-b7f9-11eb-9de2-6cfd5e8f20e6.png" alt=""></p>

[comment]: <> (![Image]&#40;https://i.loli.net/2019/07/13/5d2963e5cc1eb22973.png&#41;)

[comment]: <> (:cherry_blossom: :gift: :gift_heart: :confetti_ball:)

## 💬 交流反馈

Group: [602815575](https://jq.qq.com/?_wv=1027&k=UaalVexM) | **请不要来问如何使用， 文档齐全， 仅用于BUG提交反馈**

## 🧑‍🏭功能组件

以下任务都是按设定周期自动执行，`true`为正常使用，`false`为暂停使用或抛弃。

[//]: # (<details open><summary>点击展开</summary>)
<details><summary><strong><code>已经藏起来啦~~ 点击展开 嘻嘻~</code></strong></summary>

<br>  

| plugin          | version | description    | author            | pid  | cycle     | status |
|-----------------|---------|----------------|-------------------|------|-----------|--------|
| Login              | 0.0.1   | 账号登录、刷新、保活     | Lkeme             | 1000 | 2(小时)     | √      |
| CheckUpdate        | 0.0.1   | 检查版本更新         | Lkeme             | 2000 | 24(小时)    | √      |
| MainSite           | 0.0.1   | 主站任务(观看\分享\投币) | Lkeme             | 2001 | 24(小时)    | √      |
| GameForecast       | 0.0.1   | 游戏赛事预测(破产机)    | Lkeme             | 2002 | 24(小时)    | √      |
| Silver2Coin        | 0.0.1   | 银瓜子兑换硬币        | Lkeme             | 2003 | 24(小时)    | √      |
| Judge              | 0.0.1   | 風機委員投票         | Lkeme             | 2004 | 15-30(分钟) | √      |
| VipPrivilege       | 0.0.1   | 领取大会员权益        | Lkeme             | 2005 | 24(小时)    | √      |
| BpConsumption      | 0.0.1   | 大会员B币券消费       | Lkeme             | 2006 | 24(小时)    | √      |
| LiveReservation    | 0.0.1   | 预约直播有奖         | Lkeme             | 2007 | 1-3(小时)   | √      |
| Manga              | 0.0.1   | 漫画签到/分享        | Lkeme             | 2008 | 24(小时)    | √      |
| VipPoint           | 0.0.1   | 大会员积分          | Lkeme             | 2009 | 5(分钟)     | √      |
| Lottery            | 0.0.2   | 抽奖             | MoeHero/Lkeme     | 2010 | 10-25(分钟) | √      |
| PolishMedal        | 0.0.1   | 直播中点赞点亮勋章   | possible318/Lkeme | 2011 | 30-60(秒)   | √      |
| ActivityLottery    | 0.0.1   | 转盘活动           | Lkeme             | 2012 | 3-7(分钟)   | √      |
| AwardRecords       | 0.0.1   | 获奖记录           | Lkeme             | 2013 | 5(分钟)     | √      |
| BatchUnfollow      | 0.0.1   | 批量取消关注         | Lkeme             | 3000 | manual      | √      |
| ActivityInfoUpdate | 0.0.1   | 更新活动索引         | Lkeme             | 3001 | manual      | √      |

</details>

## 🖥️星图

[//]: # ([![Star History Chart]&#40;https://api.star-history.com/svg?repos=lkeme/BiliHelper-personal&type=Timeline&#41;]&#40;https://star-history.com/#lkeme/BiliHelper-personal&Timeline&#41;)
[![Stargazers over time](https://starchart.cc/lkeme/BiliHelper-personal.svg)](https://starchart.cc/lkeme/BiliHelper-personal)
[![Stargazers over time](https://starchart.cc/lkeme/BiliHelper.svg)](https://starchart.cc/lkeme/BiliHelper)

## 🤭 运行效果

效果图不代表当前版本，请以当前最新版本运行结果为准。

<p align=center><img width="680px" src="https://user-images.githubusercontent.com/19500576/118621918-6853e380-b7f9-11eb-8c73-e041c402a56b.png" alt=""></p>

[comment]: <> (![Image]&#40;https://i.loli.net/2019/07/13/5d296961a4bae41364.png&#41;)

## 🪣 项目相关

* [BilibiliHelper](https://github.com/metowolf/BilibiliHelper)
* [BiliHelper](https://github.com/lkeme/BiliHelper)
* [Github](https://github.com/)

## 🙏 致谢

感谢 `JetBrains` 提供优秀的IDE。

<a href="https://www.jetbrains.com/?from=BiliHelper-personal" target="_blank">
<img src="https://tva1.sinaimg.cn/large/008eGmZEly1gov9g3tzrnj30u00wj0tn.jpg" width="150" alt=""/>
</a>

## 🪪 License

BiliHelper is under the MIT license.

本项目基于 MIT 协议发布，并增加了 SATA 协议。

当你使用了使用 SATA 的开源软件或文档的时候，在遵守基础许可证的前提下，你必须马不停蹄地给你所使用的开源项目 “点赞” ，比如在
GitHub 上
star，然后你必须感谢这个帮助了你的开源项目的作者，作者信息可以在许可证头部的版权声明部分找到。

本项目的所有代码文件、配置项，除另有说明外，均基于上述介绍的协议发布，具体请看分支下的 LICENSE。

此处的文字仅用于说明，条款以 LICENSE 文件中的内容为准。
