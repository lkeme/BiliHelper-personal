<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\BigPointExchange;

use Bhp\Api\Api\X\VipPoint\ApiMall;
use Bhp\Api\Api\X\VipPoint\ApiTask;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Builtin\BigPointExchange\Internal\BigPointCatalogService;
use Bhp\Plugin\Builtin\BigPointExchange\Internal\BigPointExchangeWorkflow;
use Bhp\Plugin\Builtin\BigPointExchange\Internal\BigPointPromptGateway;
use Bhp\Plugin\Plugin;
use Bhp\Util\Exceptions\NoLoginException;

final class BigPointExchangePlugin extends BasePlugin
{
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, false);
    }

    /**
     * @param array<string, mixed> $options
     * @param string[] $argv
     */
    public function execute(array $options = [], array $argv = []): void
    {
        try {
            $workflow = new BigPointExchangeWorkflow(
                homepageApi: new ApiTask($this->appContext()->request()),
                mallApi: new ApiMall($this->appContext()->request()),
                catalogService: new BigPointCatalogService(),
                prompts: new BigPointPromptGateway(),
                authFailureClassifier: new AuthFailureClassifier(),
                infoLogger: \Closure::fromCallable([$this, 'info']),
                noticeLogger: \Closure::fromCallable([$this, 'notice']),
                warningLogger: \Closure::fromCallable([$this, 'warning']),
            );

            $workflow->run();
        } catch (NoLoginException $exception) {
            $this->warning('大积分兑换: ' . $exception->getMessage());
        } catch (\Throwable $throwable) {
            $this->warning('大积分兑换: ' . $throwable->getMessage());
        }
    }
}
