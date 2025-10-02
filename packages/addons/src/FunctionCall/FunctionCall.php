<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall;

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema\Schema;

/**
 * Represents a function call that can be inferred from provided context
 * using any of the Instructor's modes (not just tool calling).
 */
final readonly class FunctionCall implements CanDeserializeSelf, CanTransformSelf, CanProvideSchema, CanValidateSelf
{
    private string $name;
    private string $description;
    private Structure $arguments;

    public function __construct(
        string $name,
        string $description,
        Structure $arguments
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->arguments = $arguments;
    }

    // ACCESSORS ///////////////////////////////////////////////////

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function get(string $name) {
        return $this->arguments->get($name);
    }

    /** @return string[] returns array argument names */
    public function getArgumentNames() : array {
        $arguments = [];
        foreach ($this->arguments->fields() as $field) {
            $arguments[] = $field->name();
        }
        return $arguments;
    }

    public function getArgumentInfo(string $name) : Field {
        return $this->arguments->field($name);
    }

    // MUTATORS ////////////////////////////////////////////////////

    public function withName(string $name): static {
        return new static($name, $this->description, $this->arguments);
    }

    public function withDescription(string $description): static {
        return new static($this->name, $description, $this->arguments);
    }

    // SCHEMA //////////////////////////////////////////////////////

    #[\Override]
    public function toSchema(): Schema {
        return $this->arguments->toSchema();
    }

    public function toJsonSchema(): array {
        return $this->arguments->toJsonSchema();
    }

    public function toToolCall() : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }

    // TRANSFORMATION //////////////////////////////////////////////

    #[\Override]
    public function transform() : mixed {
        return $this->toArgs();
    }

    public function toArgs(): array {
        $arguments = [];
        foreach ($this->arguments->fields() as $field) {
            $arguments[$field->name()] = $field->get();
        }
        return $arguments;
    }

    // VALIDATION //////////////////////////////////////////////////

    #[\Override]
    public function validate(): ValidationResult {
        return $this->arguments->validate();
    }

    // SERIALIZATION ///////////////////////////////////////////////

    #[\Override]
    public function fromJson(string $jsonData, ?string $toolName = null): static {
        $arguments = $this->arguments->fromJson($jsonData);
        $name = $toolName ?? $this->name;
        return new static($name, $this->description, $arguments);
    }
}
