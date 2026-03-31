<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class VipPointTaskCatalog
{
    /**
     * @param array<int, array<string, mixed>> $modules
     */
    public function __construct(private readonly array $modules)
    {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function fromResponse(array $response): self
    {
        $modules = $response['data']['task_info']['modules'] ?? [];

        return new self(is_array($modules) ? $modules : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function findTask(string $title, string $code): array
    {
        foreach ($this->modules as $module) {
            if (($module['module_title'] ?? '') !== $title) {
                continue;
            }

            foreach ($module['common_task_item'] ?? [] as $item) {
                if (($item['task_code'] ?? '') !== $code) {
                    continue;
                }

                return is_array($item) ? $item : [];
            }
        }

        return [];
    }
}
