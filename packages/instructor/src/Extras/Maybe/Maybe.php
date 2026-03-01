<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Maybe;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Schema\JsonSchemaRenderer;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use ReflectionClass;

final class Maybe implements CanProvideJsonSchema, CanDeserializeSelf
{
    /** @var class-string */
    private string $class = '';
    private string $name = '';
    private string $description = '';

    private mixed $value = null;
    private bool $hasValue = false;
    private string $error = '';

    public function __construct(
        private readonly SchemaFactory $schemaFactory = new SchemaFactory(useObjectReferences: false),
        private readonly CanDeserializeClass $deserializer = new SymfonyDeserializer(),
    ) {}

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

    #[\Override]
    public function toJsonSchema(): array {
        $schema = $this->schemaFactory->schema($this->class);
        $schemaData = (new JsonSchemaRenderer)->toArray($schema);
        $schemaData['x-title'] = $this->name ?: (new ReflectionClass($this->class))->getShortName();
        $schemaData['description'] = $this->description ?: ('Correctly extracted values of ' . $schemaData['x-title']);
        $schemaData['x-php-class'] = $this->class;

        return [
            'type' => 'object',
            'x-php-class' => self::class,
            'properties' => [
                'hasValue' => ['type' => 'boolean', 'description' => 'True if value extracted, false if data not available'],
                'value' => $schemaData,
                'error' => ['type' => 'string', 'description' => 'Obligatory if no value extracted - provide reason'],
            ],
            'required' => ['hasValue'],
        ];
    }

    #[\Override]
    public function fromArray(array $data): static {
        $this->hasValue = (bool) ($data['hasValue'] ?? false);
        $this->error = (string) ($data['error'] ?? '');

        if ($this->hasValue && isset($data['value']) && is_array($data['value'])) {
            /** @var class-string $class */
            $class = $this->class;
            $this->value = $this->deserializer->fromArray($data['value'], $class);
        }

        return $this;
    }
}
