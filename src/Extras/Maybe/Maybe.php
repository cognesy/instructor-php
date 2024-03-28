<?php
namespace Cognesy\Instructor\Extras\Maybe;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

class Maybe implements CanProvideJsonSchema {
    private string $class;
    private string $name;
    private string $description;

    public mixed $value;
    public bool $hasValue = false;
    /** If no value, provide reason */
    public ?string $errorMessage = '';

    public static function is(string $class, string $name = '', string $description = '') : self {
        $instance = new self();
        $instance->class = $class;
        $instance->name = $name;
        $instance->description = $description;
        return $instance;
    }

    public function get() : mixed {
        return $this->hasValue ? $this->value : null;
    }

    public function toJsonSchema(): array {
        $schemaFactory = new SchemaFactory(false);
        $typeDetailsFactory = new TypeDetailsFactory();
        $schema = $schemaFactory->schema($this->class);
        $schemaData = $schema->toArray();
        $schemaData['title'] = $this->name ?: $typeDetailsFactory->fromTypeName($this->class)->classOnly();
        $schemaData['description'] = $this->description ?: "Correctly extracted values of ".$schemaData['title'];
        $schemaData['$comment'] = $this->class;
        return [
            'type' => 'object',
            '$comment' => Maybe::class,
            'properties' => [
                'value' => $schemaData,
                'hasValue' => ['type' => 'boolean'],
                'errorMessage' => ['type' => 'string'],
            ],
            'required' => ['hasValue'],
        ];
    }
}