<?php declare(strict_types=1);

namespace Bhp\Bootstrap;

use Bhp\Profile\ProfileInspectionResult;

final class StartupSelfCheckReport
{
    /**
     * @param array<string, string> $login
     * @param array<string, string> $plugins
     * @param array<string, string> $requestGovernance
     * @param array<string, string> $traffic
     * @param string[] $blockingIssues
     */
    public function __construct(
        public readonly ProfileInspectionResult $profile,
        public readonly array $login,
        public readonly array $plugins,
        public readonly array $requestGovernance,
        public readonly array $traffic,
        public readonly array $blockingIssues = [],
    ) {
    }

    public function hasBlockingIssues(): bool
    {
        return $this->blockingIssues !== [];
    }

    /**
     * @return array<string, string>
     */
    public function diagnostics(): array
    {
        $diagnostics = $this->profile->diagnostics();
        foreach ($this->login as $key => $value) {
            $diagnostics['login_' . $key] = $value;
        }
        foreach ($this->plugins as $key => $value) {
            $diagnostics['plugin_' . $key] = $value;
        }
        foreach ($this->requestGovernance as $key => $value) {
            $diagnostics['request_' . $key] = $value;
        }
        foreach ($this->traffic as $key => $value) {
            $diagnostics['traffic_' . $key] = $value;
        }
        if ($this->blockingIssues !== []) {
            $diagnostics['blocking_issues'] = implode('|', $this->blockingIssues);
        }

        return $diagnostics;
    }
}
