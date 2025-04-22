<?php

namespace Cognesy\Addons\ToolUse\Tools;

use Closure;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Utils\Result\Result;
use Throwable;

class FunctionTool implements ToolInterface
{
    private string $name;
    private string $description;
    private array $jsonSchema;

    private Closure $callback;

    public function __construct(
        string $name,
        string $description,
        array $jsonSchema,
        Closure $callback,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->jsonSchema = $jsonSchema;
        $this->callback = $callback;
    }

    public static function fromCallable(callable $function): self {
        $structure = StructureFactory::fromCallable($function);
        return new self(
            name: $structure->name(),
            description: $structure->description(),
            jsonSchema: $structure->toJsonSchema(),
            callback: Closure::fromCallable($function)
        );
    }

    public function name(): string {
        return $this->name;
    }

    public function description(): string {
        return $this->description;
    }

    public function function(): Closure {
        return $this->callback;
    }

    public function withName(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function withDescription(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function use(mixed ...$args): Result {
        try {
            $result = $this->__invoke(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return Result::success($result);
    }

    public function __invoke(mixed ...$args): mixed {
        return ($this->callback)(...$args);
    }

    public function toJsonSchema() : array {
        return $this->jsonSchema;
    }

    public function toToolSchema() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }
}
