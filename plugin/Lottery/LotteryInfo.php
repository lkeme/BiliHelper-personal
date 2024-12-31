<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Cache\Cache;
use Bhp\Util\DesignPattern\SingleTon;

class LotteryInfo extends SingleTon
{
    /**
     * @var array 抽奖信息列表
     */
    protected array $info_list = [];

    /**
     * 初始化数据
     * @return void
     */
    public function init(): void
    {
        Cache::initCache();
        $this->info_list = ($tmp = Cache::get('lottery_infos')) ? $tmp : [];
    }

    /**
     * 获取已开奖列表
     * @return array
     */
    public static function getHasLotteryList(): array
    {
        return array_filter(self::getInstance()->info_list, function (array $i) {
            return $i['lottery_time'] < (time() + 30 * 60);
        });
    }

    /**
     * 抽奖是否存在
     * @param int $lottery_id
     * @return bool
     */
    public static function isExist(int $lottery_id): bool
    {
        foreach (self::getInstance()->info_list as $item) {
            if ($item['lottery_id'] === $lottery_id) return true;
        }
        return false;
    }

    /**
     * 新增抽奖信息
     * @param int $lottery_id
     * @param string $dynamic_id
     * @param int $lottery_time
     * @param int $uid
     * @param int $group_id
     * @return void
     */
    public static function add(int $lottery_id, string $dynamic_id, int $lottery_time, int $uid, int $group_id): void
    {
        self::getInstance()->info_list[] = [
            'lottery_id' => $lottery_id,
            'dynamic_id' => $dynamic_id,
            'lottery_time' => $lottery_time,
            'uid' => $uid,
            'group_id' => $group_id,
        ];
        Cache::set('lottery_infos', self::getInstance()->info_list);
    }

    /**
     * 删除抽奖信息
     * @param int $lottery_id
     * @return void
     */
    public static function delete(int $lottery_id): void
    {
        self::getInstance()->info_list = array_filter(self::getInstance()->info_list, function (array $i) use ($lottery_id) {
            return $i['lottery_id'] === $lottery_id;
        });
        Cache::set('lottery_infos', self::getInstance()->info_list);
    }
}
