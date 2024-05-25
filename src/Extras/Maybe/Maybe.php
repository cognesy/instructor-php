<?php
namespace Cognesy\Instructor\Extras\Maybe;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

class Maybe implements CanProvideJsonSchema, CanDeserializeSelf
{
    private string $class;
    private string $name;
    private string $description;

    private mixed $value = null;
    private bool $hasValue = false;
    /** If no value, provide reason */
    private string $error = '';

    private SchemaFactory $schemaFactory;
    private TypeDetailsFactory $typeDetailsFactory;
    private Deserializer $deserializer;

    public function __construct() {
        $this->schemaFactory = new SchemaFactory(false);
        $this->typeDetailsFactory = new TypeDetailsFactory();
        $this->deserializer = new Deserializer();
    }

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

    public function error() : string {
        return $this->error;
    }

    public function hasValue() : bool {
        return $this->hasValue;
    }

    public function toJsonSchema(): array {
        $schema = $this->schemaFactory->schema($this->class);
        $schemaData = $schema->toArray();
        $schemaData['title'] = $this->name ?: $this->typeDetailsFactory->fromTypeName($this->class)->classOnly();
        $schemaData['description'] = $this->description ?: "Correctly extracted values of ".$schemaData['title'];
        $schemaData['$comment'] = $this->class;
        return [
            'type' => 'object',
            '$comment' => Maybe::class,
            'properties' => [
                'value' => $schemaData,
                'hasValue' => ['type' => 'boolean'],
                'error' => ['type' => 'string', "description" => "Obligatory if no value extracted - provide reason"],
            ],
            'required' => ['hasValue'],
        ];
    }

    public function fromJson(string $jsonData, string $toolName = '') : static {
        $data = json_decode($jsonData, true);
        $this->hasValue = $data['hasValue'] ?? false;
        $this->error = $data['error'] ?? '';
        if ($this->hasValue) {
            $this->value = $this->deserializer->fromJson(json_encode($data['value']), $this->class);
        }
        return $this;
    }
}
