<?php

namespace Cognesy\Instructor\Extras\ToolUse\Tools;

use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Extras\ToolUse\Contracts\ToolInterface;
use Cognesy\Instructor\Utils\Result\Result;
use Throwable;

abstract class BaseTool implements ToolInterface
{
    protected string $name;
    protected string $description;
    protected array $jsonSchema;

    public function name(): string {
        return $this->name ?? static::class;
    }

    public function description(): string {
        return $this->description ?? '';
    }

    public function use(mixed ...$args): Result {
        try {
            $value = $this->__invoke(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return Result::success($value);
    }

    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function toJsonSchema(): array {
        if (!isset($this->jsonSchema)) {
            $this->jsonSchema = StructureFactory::fromMethodName(static::class, '__invoke')
                ->toSchema()
                ->toJsonSchema();
        }
        return $this->jsonSchema;
    }
}