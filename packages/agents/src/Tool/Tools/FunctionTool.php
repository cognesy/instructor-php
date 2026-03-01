<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Tools;

use Closure;
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Dynamic\CallableSchemaFactory;
use Cognesy\Schema\SchemaFactory;

class FunctionTool extends ReflectiveSchemaTool
{
    /** @var Closure(mixed...): mixed */
    private Closure $callback;

    /**
     * @param Closure(mixed...): mixed $callback
     */
    private function __construct(
        string $name,
        string $description,
        array $jsonSchema,
        Closure $callback,
    ) {
        parent::__construct(new ToolDescriptor(
            name: $name,
            description: $description,
        ));
        $this->cachedParamsJsonSchema = $jsonSchema;
        $this->callback = $callback;
    }

    /**
     * @param callable(mixed...): mixed $function
     */
    public static function fromCallable(callable $function): self {
        $schema = (new CallableSchemaFactory())->fromCallable($function);
        return new self(
            name: $schema->name(),
            description: $schema->description(),
            jsonSchema: SchemaFactory::default()->toJsonSchema($schema),
            callback: $function instanceof Closure
                ? $function
                : Closure::fromCallable($function)
        );
    }

    /**
     * @return Closure(mixed...): mixed
     */
    public function function(): Closure {
        return $this->callback;
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed {
        return ($this->callback)(...$args);
    }

    // override to provide the cached JSON schema

    #[\Override]
    protected function paramsJsonSchema(): array {
        return $this->cachedParamsJsonSchema ?? [];
    }
}
