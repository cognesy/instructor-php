<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Tools;

use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Utils\Result\Result;
use Throwable;

abstract class BaseTool implements ToolInterface
{
    protected string $name;
    protected string $description;
    protected array $cachedParamsJsonSchema;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
    ) {
        $this->name = $name ?? static::class;
        $this->description = $description ?? '';
        $this->cachedParamsJsonSchema = [];
    }

    /**
     * Subclasses must implement __invoke with their specific signature
     */
    abstract public function __invoke(mixed ...$args): mixed;

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
    public function description(): string {
        return $this->description;
    }

    #[\Override]
    public function use(mixed ...$args): Result {
        try {
            $value = $this->__invoke(...$args);
        } catch (Throwable $e) {
            return Result::failure($e);
        }
        return Result::success($value);
    }

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

    // INTERNAL ////////////////////////////////////////////////

    protected function paramsJsonSchema(): array {
        if (!isset($this->cachedParamsJsonSchema)) {
            $this->cachedParamsJsonSchema = StructureFactory::fromMethodName(static::class, '__invoke')
                ->toSchema()
                ->toJsonSchema();
        }
        return $this->cachedParamsJsonSchema;
    }
}
