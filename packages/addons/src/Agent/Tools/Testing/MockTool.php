<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools\Testing;

use Cognesy\Addons\Agent\Contracts\ToolInterface;
use Cognesy\Utils\Result\Result;

final readonly class MockTool implements ToolInterface
{
    /** @var \Closure(mixed ...): mixed */
    private \Closure $handler;
    private array $schema;
    private array $metadata;
    private array $fullSpec;

    /**
     * @param callable(mixed ...): mixed $handler
     */
    public function __construct(
        private string $name,
        private string $description,
        callable $handler,
        array $schema = [],
        array $metadata = [],
        array $fullSpec = [],
    ) {
        $this->handler = \Closure::fromCallable($handler);
        $this->schema = $schema;
        $this->metadata = $metadata;
        $this->fullSpec = $fullSpec;
    }

    public static function returning(string $name, string $description, mixed $value): self {
        return new self(
            name: $name,
            description: $description,
            handler: static fn(mixed ...$args): mixed => $value,
        );
    }

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
        return Result::from(($this->handler)(...$args));
    }

    #[\Override]
    public function toToolSchema(): array {
        if ($this->schema !== []) {
            return $this->schema;
        }
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];
    }

    #[\Override]
    public function metadata(): array {
        return array_merge([
            'name' => $this->name,
            'summary' => $this->description,
        ], $this->metadata);
    }

    #[\Override]
    public function fullSpec(): array {
        return array_merge([
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => [],
            'usage' => [],
            'examples' => [],
            'errors' => [],
            'notes' => [],
        ], $this->fullSpec);
    }
}
