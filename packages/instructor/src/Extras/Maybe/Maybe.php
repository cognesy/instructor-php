<?php
namespace Cognesy\Instructor\Extras\Maybe;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

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
    private SymfonyDeserializer $deserializer;

    public function __construct() {
        $this->schemaFactory = new SchemaFactory(useObjectReferences: false);
        $this->deserializer = new SymfonyDeserializer();
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
        $schemaData = (new SchemaToJsonSchema)->toArray($schema);
        $schemaData['x-title'] = $this->name ?: TypeDetails::fromTypeName($this->class)->classOnly();
        $schemaData['description'] = $this->description ?: "Correctly extracted values of ".$schemaData['x-title'];
        $schemaData['x-php-class'] = $this->class;
        return [
            'type' => 'object',
            'x-php-class' => Maybe::class,
            'properties' => [
                'hasValue' => ['type' => 'boolean', 'description' => 'True if value extracted, false if data not available'],
                'value' => $schemaData,
                'error' => ['type' => 'string', "description" => "Obligatory if no value extracted - provide reason"],
            ],
            'required' => ['hasValue'],
        ];
    }

    public function fromJson(string $jsonData, ?string $toolName = '') : static {
        $data = json_decode($jsonData, true);
        $this->hasValue = $data['hasValue'] ?? false;
        $this->error = $data['error'] ?? '';
        if ($this->hasValue) {
            $this->value = $this->deserializer->fromJson(Json::encode($data['value']), $this->class);
        }
        return $this;
    }
}
