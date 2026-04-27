<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\BigPointExchange\Internal;

use Bhp\Api\Api\X\VipPoint\ApiMall;
use Bhp\Api\Api\X\VipPoint\ApiTask;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Util\AsciiTable\AsciiTable;
use RuntimeException;

final class BigPointExchangeWorkflow
{
    /**
     * @var array<int, string>|null
     */
    private ?array $categories = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $catalogIndex = null;

    /**
     * @param \Closure(string, array<string, mixed>): void $infoLogger
     * @param \Closure(string, array<string, mixed>): void $noticeLogger
     * @param \Closure(string, array<string, mixed>): void $warningLogger
     */
    public function __construct(
        private readonly ApiTask $homepageApi,
        private readonly ApiMall $mallApi,
        private readonly BigPointCatalogService $catalogService,
        private readonly BigPointPromptGateway $prompts,
        private readonly AuthFailureClassifier $authFailureClassifier,
        private readonly \Closure $infoLogger,
        private readonly \Closure $noticeLogger,
        private readonly \Closure $warningLogger,
    ) {
    }

    public function run(): void
    {
        $homepage = $this->loadHomepage();
        if (!$this->ensureVip($homepage)) {
            return;
        }

        while (true) {
            $this->showOverview($homepage);
            $action = (string)$this->prompts->selectKey('请选择操作', [
                'browse' => '浏览兑换商城',
                'search' => '搜索商品',
                'records' => '查看积分消费记录',
                'refresh' => '刷新积分与商城信息',
                'exit' => '退出',
            ], 'browse', '使用方向键或输入序号选择');

            switch ($action) {
                case 'browse':
                    $this->browseCatalog();
                    break;

                case 'search':
                    $this->searchCatalog();
                    break;

                case 'records':
                    $this->showPointRecords();
                    break;

                case 'refresh':
                    $homepage = $this->loadHomepage();
                    $this->catalogIndex = null;
                    $this->categories = null;
                    break;

                case 'exit':
                default:
                    return;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadHomepage(): array
    {
        $response = $this->homepageApi->homepageCombine();
        $this->assertNotAuthFailure($response, '大积分兑换: 获取首页信息时账号未登录');
        if ((int)($response['code'] ?? -1) !== 0) {
            $message = trim((string)($response['message'] ?? $response['msg'] ?? '获取首页信息失败'));
            throw new RuntimeException($message);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $homepage
     */
    private function ensureVip(array $homepage): bool
    {
        $status = (int)($homepage['data']['vip_info']['status'] ?? 0);
        if ($status === 1) {
            return true;
        }

        $this->warning('大积分兑换: 当前账号不是有效大会员，无法继续兑换');
        return false;
    }

    /**
     * @param array<string, mixed> $homepage
     */
    private function showOverview(array $homepage): void
    {
        $point = max(0, (int)($homepage['data']['point_info']['point'] ?? 0));
        $categoryNames = implode('、', array_values($this->categories($homepage)));
        $this->info(sprintf(
            '大积分兑换: 当前拥有 %d 积分，可用分类：%s',
            $point,
            $categoryNames !== '' ? $categoryNames : '无',
        ));
    }

    private function browseCatalog(): void
    {
        $homepage = $this->loadHomepage();
        $categoryId = $this->prompts->selectKey(
            '选择分类',
            $this->categories($homepage) + ['__back' => '返回上一级'],
            array_key_first($this->categories($homepage)),
            '进入分类后可选择具体商品',
        );
        if ($categoryId === null || $categoryId === '__back') {
            return;
        }

        $categoryName = $this->categories($homepage)[(int)$categoryId] ?? '';
        if ($categoryName === '') {
            $this->warning('大积分兑换: 选择的分类无效');
            return;
        }

        $items = $this->fetchCategoryCatalog((int)$categoryId, $categoryName);
        if ($items === []) {
            $this->warning("大积分兑换: 分类「{$categoryName}」当前没有可展示商品");
            return;
        }

        $options = ['__back' => '返回上一级'];
        foreach ($items as $item) {
            $options[(string)$item['token']] = (string)$item['option_label'];
        }

        $token = $this->prompts->selectKey(
            "分类「{$categoryName}」商品列表",
            $options,
            '__back',
            '选择商品后可查看详情并决定是否兑换',
        );
        if ($token === null || $token === '__back') {
            return;
        }

        $this->inspectSku((string)$token);
    }

    private function searchCatalog(): void
    {
        $catalog = $this->catalogIndex();
        if ($catalog === []) {
            $this->warning('大积分兑换: 当前没有可搜索的商品');
            return;
        }

        $token = $this->prompts->searchKey(
            '输入商品名关键字搜索',
            function (string $query) use ($catalog): array {
                $query = trim($query);
                $matches = ['__back' => '返回上一级'];

                foreach ($catalog as $itemToken => $item) {
                    $title = (string)($item['title'] ?? '');
                    if ($query !== '' && !str_contains(mb_strtolower($title), mb_strtolower($query))) {
                        continue;
                    }

                    $matches[$itemToken] = (string)($item['option_label'] ?? $title);
                    if (count($matches) >= 31) {
                        break;
                    }
                }

                return $matches;
            },
            '输入关键字后回车搜索，返回项可直接退出',
            '支持按标题关键字搜索',
        );

        if ($token === null || $token === '__back') {
            return;
        }

        $this->inspectSku((string)$token);
    }

    private function showPointRecords(): void
    {
        $response = $this->mallApi->pointList(changeType: 2, pn: 1, ps: 10);
        $this->assertNotAuthFailure($response, '大积分兑换: 获取积分消费记录时账号未登录');
        if ((int)($response['code'] ?? -1) !== 0) {
            $message = trim((string)($response['message'] ?? $response['msg'] ?? '获取积分消费记录失败'));
            $this->warning("大积分兑换: {$message}");
            return;
        }

        $records = (array)($response['data']['big_point_list'] ?? []);
        if ($records === []) {
            $this->info('大积分兑换: 暂无积分消费记录');
            return;
        }

        $rows = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $rows[] = [
                'point' => (string)($record['point'] ?? ''),
                'remark' => trim((string)($record['remark'] ?? '')),
                'order_no' => trim((string)($record['order_no'] ?? '')),
                'change_time' => $this->formatTimestamp((int)($record['change_time'] ?? 0)),
            ];
        }

        foreach (AsciiTable::array2table($rows, '最近积分消费记录') as $line) {
            fwrite(STDOUT, $line . PHP_EOL);
        }

        $this->prompts->pause();
    }

    private function inspectSku(string $token): void
    {
        $detail = $this->skuDetail($token);
        $this->info(sprintf(
            "大积分兑换: 商品「%s」\n分类：%s\n价格：%s\n状态：%s\n库存：%d\n限购：%s",
            $detail['title'],
            $detail['category_name'],
            $detail['display_price'],
            $detail['availability_label'],
            $detail['surplus_num'],
            $this->formatExchangeLimit($detail),
        ));

        $hasUpcoming = (int)$detail['next_sold_time'] > (int)$detail['server_time'];
        $canExchangeNow = (string)$detail['availability_label'] === '可兑换';

        $options = ['__back' => '返回上一级'];
        if ($canExchangeNow) {
            $options['exchange_now'] = '立即兑换';
        }
        if ($hasUpcoming) {
            $options['exchange_wait'] = '等待开售后兑换';
        }

        $action = (string)$this->prompts->selectKey(
            '请选择商品操作',
            $options,
            '__back',
            '兑换前会再次做库存与价格校验',
        );

        if ($action === '__back' || $action === '') {
            return;
        }

        if ($action === 'exchange_now') {
            $this->exchangeSku($detail, false);
            return;
        }

        if ($action === 'exchange_wait') {
            $this->exchangeSku($detail, true);
        }
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function exchangeSku(array $detail, bool $waitForSale): void
    {
        $title = (string)$detail['title'];
        $token = (string)$detail['token'];
        $salePrice = (int)$detail['sale_price'];

        if ($waitForSale && (int)$detail['next_sold_time'] > (int)$detail['server_time']) {
            $waitSeconds = max(0, (int)$detail['next_sold_time'] - time());
            if ($waitSeconds > 0) {
                if (!$this->prompts->confirm(
                    sprintf('商品「%s」将在 %s 开售，确认等待后再继续兑换？', $title, $this->formatTimestamp((int)$detail['next_sold_time'])),
                    false,
                    sprintf('预计等待 %d 秒', $waitSeconds),
                )) {
                    return;
                }

                $this->info(sprintf('大积分兑换: 商品「%s」将在 %s 开售，等待 %d 秒后继续', $title, $this->formatTimestamp((int)$detail['next_sold_time']), $waitSeconds));
                sleep($waitSeconds);
            }
        }

        $homepage = $this->loadHomepage();
        $currentPoint = max(0, (int)($homepage['data']['point_info']['point'] ?? 0));
        if ($currentPoint < $salePrice) {
            $this->warning(sprintf('大积分兑换: 当前积分不足，商品「%s」需要 %d 积分，当前仅有 %d', $title, $salePrice, $currentPoint));
            return;
        }

        if (!$this->prompts->confirm(sprintf('确认兑换「%s」？将消耗 %d 积分', $title, $salePrice), false, '确认后会执行验证、建单和支付')) {
            return;
        }

        $verifyResponse = $this->mallApi->verifyOrder($token, $salePrice);
        $this->assertNotAuthFailure($verifyResponse, '大积分兑换: 校验订单时账号未登录');
        if ((int)($verifyResponse['code'] ?? -1) !== 0) {
            $message = trim((string)($verifyResponse['message'] ?? $verifyResponse['msg'] ?? '校验订单失败'));
            $this->warning("大积分兑换: {$message}");
            return;
        }
        if (!(bool)($verifyResponse['data']['can_purchase'] ?? false)) {
            $reason = trim((string)($verifyResponse['data']['reject_reason'] ?? '当前不可兑换'));
            $this->warning("大积分兑换: {$reason}");
            return;
        }

        $createResponse = $this->mallApi->createOrder($token, $salePrice);
        $this->assertNotAuthFailure($createResponse, '大积分兑换: 创建订单时账号未登录');
        if ((int)($createResponse['code'] ?? -1) !== 0) {
            $message = trim((string)($createResponse['message'] ?? $createResponse['msg'] ?? '创建订单失败'));
            $this->warning("大积分兑换: {$message}");
            return;
        }

        $orderNo = trim((string)($createResponse['data']['order']['order_no'] ?? ''));
        if ($orderNo === '') {
            $this->warning('大积分兑换: 创建订单成功但未返回订单号，流程中止');
            return;
        }

        $paymentResponse = $this->mallApi->paymentOrder($orderNo, $token);
        $this->assertNotAuthFailure($paymentResponse, '大积分兑换: 支付订单时账号未登录');
        if ((int)($paymentResponse['code'] ?? -1) !== 0) {
            $message = trim((string)($paymentResponse['message'] ?? $paymentResponse['msg'] ?? '支付失败'));
            $this->warning("大积分兑换: {$message}");
            return;
        }

        $paymentState = (int)($paymentResponse['data']['state'] ?? -1);
        if (!in_array($paymentState, [2, 4], true)) {
            $this->warning(sprintf('大积分兑换: 支付返回状态异常 state=%d，订单号=%s', $paymentState, $orderNo));
            return;
        }

        if ($this->confirmConsumptionRecord($orderNo)) {
            $this->notice(sprintf('大积分兑换: 商品「%s」兑换成功，订单号=%s', $title, $orderNo));
            return;
        }

        $this->warning(sprintf('大积分兑换: 支付已返回成功状态，但未在积分记录中及时确认订单 %s，请手动核实', $orderNo));
    }

    /**
     * @return array<int, string>
     */
    private function categories(array $homepage): array
    {
        if ($this->categories !== null) {
            return $this->categories;
        }

        return $this->categories = $this->catalogService->extractCategories($homepage);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCategoryCatalog(int $categoryId, string $categoryName): array
    {
        $page = 1;
        $pageSize = 20;
        $all = [];

        while (true) {
            $response = $this->mallApi->skuList($page, $pageSize, $categoryId);
            $this->assertNotAuthFailure($response, '大积分兑换: 获取分类商品列表时账号未登录');
            if ((int)($response['code'] ?? -1) !== 0) {
                $message = trim((string)($response['message'] ?? $response['msg'] ?? '获取分类商品列表失败'));
                throw new RuntimeException($message);
            }

            $items = $this->catalogService->extractPageItems(
                $categoryId,
                $categoryName,
                $response,
                time(),
            );
            $all = array_merge($all, $items);

            $total = max(0, (int)($response['data']['page']['total'] ?? count($all)));
            if (count($all) >= $total || $items === []) {
                break;
            }

            $page++;
        }

        return $all;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogIndex(): array
    {
        if ($this->catalogIndex !== null) {
            return $this->catalogIndex;
        }

        $homepage = $this->loadHomepage();
        $catalog = [];
        foreach ($this->categories($homepage) as $categoryId => $categoryName) {
            $items = $this->fetchCategoryCatalog($categoryId, $categoryName);
            $catalog = array_replace($catalog, $this->catalogService->indexByToken($items));
        }

        return $this->catalogIndex = $catalog;
    }

    /**
     * @return array<string, mixed>
     */
    private function skuDetail(string $token): array
    {
        $response = $this->mallApi->skuInfo($token);
        $this->assertNotAuthFailure($response, '大积分兑换: 获取商品详情时账号未登录');
        if ((int)($response['code'] ?? -1) !== 0) {
            $message = trim((string)($response['message'] ?? $response['msg'] ?? '获取商品详情失败'));
            throw new RuntimeException($message);
        }

        $catalogItem = $this->catalogIndex()[$token] ?? [
            'category_id' => 0,
            'category_name' => '未知',
        ];
        $raw = (array)($response['data'] ?? []);
        $salePrice = max(0, (int)($raw['price']['sale'] ?? $raw['price']['origin'] ?? 0));
        $originPrice = max(0, (int)($raw['price']['origin'] ?? $salePrice));
        $availabilityLabel = trim((string)($raw['inventory']['next_sold_text'] ?? ''));
        if ($availabilityLabel === '') {
            $availabilityLabel = ((bool)($raw['inventory']['is_sold_out'] ?? false) || max(0, (int)($raw['inventory']['countdown_time'] ?? 0)) > 0 && max(0, (int)($raw['inventory']['surplus_num'] ?? 0)) <= 0)
                ? '暂兑完'
                : '可兑换';
        }
        if ($availabilityLabel === '暂兑完' && (int)($raw['inventory']['next_sold_time'] ?? 0) > (int)($raw['server_time'] ?? time())) {
            $availabilityLabel = trim((string)($raw['inventory']['next_sold_text'] ?? '')) ?: '待开售';
        }

        return [
            'token' => trim((string)($raw['token'] ?? $token)),
            'title' => trim((string)($raw['title'] ?? ($catalogItem['title'] ?? ''))),
            'category_id' => (int)($catalogItem['category_id'] ?? 0),
            'category_name' => trim((string)($catalogItem['category_name'] ?? '未知')),
            'origin_price' => $originPrice,
            'sale_price' => $salePrice > 0 ? $salePrice : $originPrice,
            'display_price' => $salePrice > 0 && $salePrice < $originPrice
                ? sprintf('%d/%d 大积分', $salePrice, $originPrice)
                : sprintf('%d 大积分', max($salePrice, $originPrice)),
            'surplus_num' => max(0, (int)($raw['inventory']['surplus_num'] ?? $catalogItem['surplus_num'] ?? $raw['inventory']['count'] ?? 0)),
            'availability_label' => $availabilityLabel !== '' ? $availabilityLabel : '可兑换',
            'next_sold_time' => max(0, (int)($raw['inventory']['next_sold_time'] ?? 0)),
            'server_time' => max(0, (int)($raw['server_time'] ?? time())),
            'exchange_limit_type' => (int)($raw['exchange_limit_type'] ?? 0),
            'exchange_limit_num' => (int)($raw['exchange_limit_num'] ?? 0),
            'raw' => $raw,
        ];
    }

    private function confirmConsumptionRecord(string $orderNo): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->mallApi->pointList(changeType: 2, pn: 1, ps: 20);
            $this->assertNotAuthFailure($response, '大积分兑换: 校验积分消费记录时账号未登录');
            if ((int)($response['code'] ?? -1) !== 0) {
                continue;
            }

            foreach ((array)($response['data']['big_point_list'] ?? []) as $record) {
                if (!is_array($record)) {
                    continue;
                }

                if (trim((string)($record['order_no'] ?? '')) === $orderNo) {
                    return true;
                }
            }

            if ($attempt < 2) {
                sleep(2);
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function formatExchangeLimit(array $detail): string
    {
        $num = max(0, (int)($detail['exchange_limit_num'] ?? 0));
        if ($num <= 0) {
            return '未知';
        }

        return match ((int)($detail['exchange_limit_type'] ?? 0)) {
            2 => "累计限兑 {$num} 次",
            3 => "周期限兑 {$num} 次",
            4 => "单次限兑 {$num} 次",
            default => "限兑 {$num} 次",
        };
    }

    private function formatTimestamp(int $timestamp): string
    {
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '-';
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertNotAuthFailure(array $response, string $message): void
    {
        $this->authFailureClassifier->assertNotAuthFailure($response, $message);
    }

    private function info(string $message): void
    {
        ($this->infoLogger)($message, []);
    }

    private function notice(string $message): void
    {
        ($this->noticeLogger)($message, []);
    }

    private function warning(string $message): void
    {
        ($this->warningLogger)($message, []);
    }
}
