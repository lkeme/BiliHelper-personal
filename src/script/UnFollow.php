<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Script;

use BiliHelper\Core\Env;
use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use Exception;

class UnFollow extends BaseTask
{
    public static string $description = '批量清理选定分组关注，默认单次最大清理600个关注.';

    /**
     * @use run
     * @throws Exception
     */
    public static function run()
    {
        self::init();
        // 获取关注分组
        $tag_id = self::relationTags();
        // 获取分组关注
        $target_ups = self::relationTag($tag_id);
        // 取消关注状态
        self::relationModify($target_ups, $tag_id);
        // Todo 优化
        Log::notice('任务执行完毕~');
    }

    /**
     * @use 取消关注
     * @param $target_ups
     * @param $tag_id
     * @throws Exception
     */
    private static function relationModify($target_ups, $tag_id)
    {
        self::confirm('请确认以上输入信息，是否继续取关操作?');

        $url = 'https://api.bilibili.com/x/relation/modify';

        foreach ($target_ups as $up_uid => $up_uname) {
            $payload = [
                'fid' => $up_uid,
                'act' => 2,
                're_src' => 11,
                'csrf' => getCsrf(),
            ];
            $headers = [
                'origin' => 'https://space.bilibili.com',
                'referer' => 'https://space.bilibili.com/' . getUid() . '/fans/follow?tagid=' . $tag_id,
            ];
            $raw = Curl::post('pc', $url, $payload, $headers);
            // {"code":0,"message":"0","ttl":1}
            $data = json_decode($raw, true);
            if ($data['code'] == 0) {
                Log::notice("UP.$up_uid - $up_uname 取关成功");
            } else {
                Log::error("UP.$up_uid - $up_uname 取关失败 CODE -> {$data['code']} MSG -> {$data['message']} ");
                break;
            }
            sleep(random_int(5, 10));
        }
    }


    /**
     * @use 获取分组关注
     * @param $tag_id
     * @param int $max_pn
     * @param int $max_ps
     * @return array
     * @throws Exception
     */
    private static function relationTag($tag_id, int $max_pn = 60, int $max_ps = 20): array
    {
        $following = [];
        $url = 'https://api.bilibili.com/x/relation/tag';
        for ($pn = 1; $pn <= $max_pn; $pn++) {
            $payload = [
                'mid' => getUid(),
                'tagid' => $tag_id,
                'pn' => $pn,
                'ps' => $max_ps,
            ];
            $headers = [
                'referer' => 'https://space.bilibili.com/' . getUid() . '/fans/follow',
            ];
            $raw = Curl::get('pc', $url, $payload, $headers);
            $data = json_decode($raw, true);
            if ($data['code'] == 0 && isset($data['data'])) {
                // 循环添加到 following
                foreach ($data['data'] as $up) {
                    $following[$up['mid']] = $up['uname'];
                }
                // 打印和延迟
                Log::info("已获取分组 $tag_id 页码 $pn");
                sleep(random_int(4, 8));
                // 如果页面不等于 max_ps  跳出
                if (count($data['data']) != $max_ps) {
                    break;
                }
            } else {
                Log::error("获取分组关注失败 CODE -> {$data['code']} MSG -> {$data['message']} ");
                break;
            }
        }
        $following_num = count($following);
        Log::notice("已获取分组 $tag_id 有效关注数 $following_num");
        return $following;
    }


    /**
     * @use 获取分组
     * @return mixed
     */
    private static function relationTags(): mixed
    {
        $url = 'https://api.bilibili.com/x/relation/tags';
        $payload = [];
        $headers = [
            'referer' => 'https://space.bilibili.com/',
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $data = json_decode($raw, true);
        if ($data['code'] == 0 && isset($data['data'])) {
            $options = [];
            foreach ($data['data'] as $tag) {
                $options[$tag['tagid']] = "分组:{$tag['name']} - 关注数:{$tag['count']}";
            }
            $option = self::choice($options);
            Log::notice("已获取分组 $option - $options[$option]");
            return $option;
        } else {
            Env::failExit("获取关注分组失败 CODE -> {$data['code']} MSG -> {$data['message']} ");
        }
    }

}