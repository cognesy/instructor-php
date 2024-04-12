<?php

namespace Cognesy\Instructor\Extras\Sequences;

use ArrayAccess;
use ArrayIterator;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Validators\Symfony\Validator;
use IteratorAggregate;
use Traversable;
use Cognesy\Instructor\Data\ValidationResult;

class Sequence
    implements Sequenceable, IteratorAggregate, CanProvideSchema, CanDeserializeSelf, CanValidateSelf, ArrayAccess
{
    private string $name;
    private string $description;
    private string $class;
    private $deserializer;
    private $validator;
    public array $list = [];

    public function __construct(string $class = '', string $name = '', string $description = '') {
        $this->class = $class;
        $this->name = $name;
        $this->description = $description;
        $this->deserializer = new Deserializer();
        $this->validator = new Validator();
    }

    public static function of(string $class, string $name = '', string $description = '') : static {
        return new self($class, $name, $description);
    }

    //////////////////////////////////////////////////////////////////////

    public function toSchema(): Schema {
        $schemaFactory = new SchemaFactory(false);
        $typeDetailsFactory = new TypeDetailsFactory();
        $nestedSchema = $schemaFactory->schema($this->class);
        $nestedTypeDetails = $typeDetailsFactory->fromTypeName($this->class);
        $arrayTypeDetails = new TypeDetails(
            type: 'array',
            class: null,
            nestedType: $nestedTypeDetails,
            enumType: null,
            enumValues: null,
        );
        $arraySchema = new ArraySchema(
            type: $arrayTypeDetails,
            name: 'list',
            description: '',
            nestedItemSchema: $nestedSchema,
        );
        $objectSchema = new ObjectSchema(
            type: new TypeDetails(
                type: 'object',
                class: Sequence::class,
                nestedType: null,
                enumType: null,
                enumValues: null,
            ),
            name: $this->name ?: ('sequenceOf' . $nestedTypeDetails->classOnly()),
            description: $this->description ?: ('A sequence of ' . $this->class),
        );
        $objectSchema->properties['list'] = $arraySchema;
        $objectSchema->required = ['list'];
        return $objectSchema;
    }

    public function fromJson(string $jsonData): static {
        $deserializer = $this->deserializer;
        $data = Json::parse($jsonData);
        $returnedList = $data['list'] ?? [];
        $list = [];
        foreach ($returnedList as $item) {
            $list[] = $deserializer->fromJson(Json::encode($item), $this->class);
        }
        $this->list = $list;
        return $this;
    }

    public function validate(): ValidationResult {
        $validationErrors = [];
        foreach ($this->list as $item) {
            $result = $this->validator->validate($item);
            if ($result->isInvalid()) {
                $validationErrors[] = $result->getErrors();
            }
        }
        return ValidationResult::make( array_merge(...$validationErrors), 'Sequence validation failed');
    }

    //////////////////////////////////////////////////////////////////////

    public function toArray() : array {
        return $this->list;
    }

    public function getIterator() : Traversable {
        return new ArrayIterator($this->list);
    }

    public function count(): int {
        return count($this->list);
    }

    public function get(int $index) : mixed {
        return $this->list[$index] ?? null;
    }

    public function first() : mixed {
        return $this->list[0] ?? null;
    }

    public function last() : mixed {
        if (empty($this->list)) {
            return null;
        }
        $count = count($this->list);
        return $this->list[$count - 1];
    }

    //////////////////////////////////////////////////////////////////////

    public function offsetExists(mixed $offset): bool {
        return isset($this->list[$offset]);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->list[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        if (is_null($offset)) {
            $this->list[] = $value;
        } else {
            $this->list[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->list[$offset]);
    }
}
