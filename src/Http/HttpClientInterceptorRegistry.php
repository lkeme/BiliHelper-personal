<?php declare(strict_types=1);

namespace Bhp\Http;

class HttpClientInterceptorRegistry
{
    /**
     * @var array<string, HttpClientInterceptorProvider>
     */
    private array $providers = [];

    /**
     * @param HttpClientInterceptorProvider[] $providers
     */
    public function __construct(array $providers = [])
    {
        $this->replace($providers);
    }

    /**
     * 处理注册
     * @param HttpClientInterceptorProvider $provider
     * @return void
     */
    public function register(HttpClientInterceptorProvider $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    /**
     * 处理unregister
     * @param string $name
     * @return void
     */
    public function unregister(string $name): void
    {
        unset($this->providers[$name]);
    }

    /**
     * @param HttpClientInterceptorProvider[] $providers
     */
    public function replace(array $providers): void
    {
        $this->providers = [];
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * @return HttpClientInterceptorProvider[]
     */
    public function providers(): array
    {
        $providers = array_values($this->providers);

        usort($providers, static function (HttpClientInterceptorProvider $left, HttpClientInterceptorProvider $right): int {
            $priority = $left->priority() <=> $right->priority();
            if ($priority !== 0) {
                return $priority;
            }

            return $left->name() <=> $right->name();
        });

        return $providers;
    }

    /**
     * @return array<string, string>
     */
    public function diagnosticsSummary(): array
    {
        $providers = $this->providers();

        return [
            'provider_count' => (string)count($providers),
            'provider_chain' => implode(' -> ', array_map(
                static fn(HttpClientInterceptorProvider $provider): string => $provider->name(),
                $providers,
            )),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function diagnosticsRows(): array
    {
        $rows = [];
        foreach ($this->providers() as $index => $provider) {
            $rows[] = [
                'order' => (string)($index + 1),
                'provider' => $provider->name(),
                'priority' => (string)$provider->priority(),
            ];
        }

        return $rows;
    }

    /**
     * @return HttpClientInterceptor[]
     */
    public function resolve(HttpRequestContext $context): array
    {
        $interceptors = [];

        foreach ($this->providers() as $provider) {
            foreach ($provider->provide($context) as $interceptor) {
                $interceptors[] = $interceptor;
            }
        }

        return $interceptors;
    }
}
