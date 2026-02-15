<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Traits;

use Cognesy\Dynamic\StructureFactory;

trait HasReflectiveSchema
{
    protected ?array $cachedParamsJsonSchema = null;

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->paramsJsonSchema(),
            ],
        ];
    }

    protected function paramsJsonSchema(): array {
        if (!isset($this->cachedParamsJsonSchema)) {
            $this->cachedParamsJsonSchema = StructureFactory::fromMethodName(static::class, '__invoke')
                ->toSchema()
                ->toJsonSchema();
        }

        return $this->cachedParamsJsonSchema;
    }
}
