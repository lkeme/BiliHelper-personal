<?php declare(strict_types=1);

namespace Bhp\Config;

use Bhp\Console\Cli\RuntimeException as CliRuntimeException;

final class ConfigRuntimeValidator
{
    /**
     * 初始化 ConfigRuntimeValidator
     * @param Config $config
     * @param ConfigSchemaDefinition $schema
     */
    public function __construct(
        private readonly Config $config,
        private readonly ConfigSchemaDefinition $schema,
    ) {
    }

    /**
     * 处理校验
     * @return void
     */
    public function validate(): void
    {
        $errors = [];

        foreach ($this->schema->fieldRules() as $rule) {
            $error = $this->validateRule($rule);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if ($errors === []) {
            return;
        }

        throw new CliRuntimeException("配置校验失败:\n- " . implode("\n- ", $errors));
    }

    /**
     * 校验Rule
     * @param ConfigFieldRule $rule
     * @return ?string
     */
    private function validateRule(ConfigFieldRule $rule): ?string
    {
        if ($this->shouldSkipRule($rule)) {
            return null;
        }

        $value = $this->config->get($rule->key, null);
        $enabledBy = $rule->requiredWhenEnabledBy;

        if ($enabledBy !== null && $this->config->get($enabledBy, false, 'bool')) {
            if ($this->isEmptyValue($value)) {
                return $rule->emptyMessage ?? ($rule->key . ' 不能为空');
            }
        }

        if ($rule->hasEnum()) {
            if ($this->isEmptyValue($value)) {
                return $rule->key . ' 缺少有效配置值';
            }

            if (!in_array($value, $rule->allowedValues, true)) {
                return sprintf(
                    '%s 必须为 [%s] 之一，当前为 %s',
                    $rule->key,
                    implode(', ', array_map($this->stringify(...), $rule->allowedValues)),
                    $this->stringify($value),
                );
            }
        }

        if ($rule->validateUrl && !$this->isEmptyValue($value) && !$this->isValidUrl((string)$value)) {
            return $rule->invalidMessage ?? ($rule->key . ' 不是有效的 URL');
        }

        return null;
    }

    /**
     * 判断SkipRule是否满足条件
     * @param ConfigFieldRule $rule
     * @return bool
     */
    private function shouldSkipRule(ConfigFieldRule $rule): bool
    {
        $enabledBy = $rule->requiredWhenEnabledBy;
        if ($enabledBy !== null && !$this->config->get($enabledBy, false, 'bool')) {
            return true;
        }

        if (!$rule->validateUrl) {
            return false;
        }

        $sectionEnableKey = $this->sectionEnableKey($rule->key);
        if ($sectionEnableKey === null || $sectionEnableKey === $rule->key) {
            return false;
        }

        return $this->config->get($sectionEnableKey, true, 'bool') === false;
    }

    /**
     * 处理sectionEnable键
     * @param string $key
     * @return ?string
     */
    private function sectionEnableKey(string $key): ?string
    {
        $position = strrpos($key, '.');
        if ($position === false) {
            return null;
        }

        return substr($key, 0, $position) . '.enable';
    }

    /**
     * 判断ValidURL是否满足条件
     * @param string $value
     * @return bool
     */
    private function isValidUrl(string $value): bool
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return false;
        }

        $normalized = preg_replace('/\{[^}]+\}/', 'token', $normalized) ?? $normalized;
        $normalized = str_replace(' ', '%20', $normalized);

        return filter_var($normalized, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 判断Empty值是否满足条件
     * @param mixed $value
     * @return bool
     */
    private function isEmptyValue(mixed $value): bool
    {
        return match (true) {
            $value === null => true,
            is_string($value) => trim($value) === '',
            is_array($value) => $value === [],
            default => false,
        };
    }

    /**
     * 处理stringify
     * @param mixed $value
     * @return string
     */
    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value) => (string)$value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: gettype($value),
        };
    }
}
