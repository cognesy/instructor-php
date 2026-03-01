<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Traits;

use Cognesy\Dynamic\CallableSchemaFactory;
use Cognesy\Schema\SchemaFactory;

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
            $schema = (new CallableSchemaFactory())->fromMethodName(static::class, '__invoke');
            $this->cachedParamsJsonSchema = SchemaFactory::default()->toJsonSchema($schema);
        }

        return $this->cachedParamsJsonSchema;
    }
}
