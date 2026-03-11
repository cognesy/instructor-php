<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Sequence;

use ArrayAccess;
use ArrayIterator;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use IteratorAggregate;
use ReflectionClass;
use Traversable;

/**
 * @implements IteratorAggregate<int, object>
 * @implements ArrayAccess<int, object>
 */
final class Sequence implements
    Sequenceable,
    IteratorAggregate,
    CanProvideSchema,
    CanDeserializeSelf,
    CanValidateSelf,
    ArrayAccess
{
    /** @var class-string */
    private string $class;
    private string $name;
    private string $description;

    /** @var list<object> */
    private array $items = [];

    public function __construct(
        string $class = '',
        string $name = '',
        string $description = '',
        private CanDeserializeClass $deserializer = new SymfonyDeserializer(),
        private CanValidateObject $validator = new SymfonyValidator(),
    ) {
        $this->class = $class;
        $this->name = $name;
        $this->description = $description;
    }

    #[\Override]
    public static function of(string $class, string $name = '', string $description = '') : static {
        return new static($class, $name, $description);
    }

    #[\Override]
    public function toArray() : array {
        return $this->items;
    }

    public function all() : array {
        return $this->items;
    }

    #[\Override]
    public function count(): int {
        return count($this->items);
    }

    #[\Override]
    public function get(int $index) : mixed {
        return $this->items[$index] ?? null;
    }

    public function first() : mixed {
        return $this->items[0] ?? null;
    }

    public function last() : mixed {
        if ($this->items === []) {
            return null;
        }
        return $this->items[array_key_last($this->items)];
    }

    #[\Override]
    public function push(mixed $item) : void {
        $this->items[] = $item;
    }

    #[\Override]
    public function pop() : mixed {
        if ($this->items === []) {
            return null;
        }
        return array_pop($this->items);
    }

    #[\Override]
    public function isEmpty() : bool {
        return $this->items === [];
    }

    #[\Override]
    public function getIterator() : Traversable {
        return new ArrayIterator($this->items);
    }

    #[\Override]
    public function toSchema(): Schema {
        $factory = SchemaFactory::default();
        $collectionSchema = $factory->collection(
            nestedType: $this->class,
            name: 'list',
        );

        $itemLabel = (new ReflectionClass($this->class))->getShortName();

        return $factory->object(
            class: self::class,
            name: $this->name ?: ('collectionOf' . $itemLabel),
            description: $this->description ?: ('A collection of ' . $this->class),
            properties: ['list' => $collectionSchema],
            required: ['list'],
        );
    }

    #[\Override]
    public function fromArray(array $data): static {
        $returnedList = $data['list'] ?? $data['properties']['list'] ?? [];

        if (!is_array($returnedList)) {
            $this->items = [];
            return $this;
        }

        /** @var class-string<object> $class */
        $class = $this->class;
        $this->items = [];

        foreach ($returnedList as $item) {
            if (is_array($item)) {
                $this->items[] = $this->deserializer->fromArray($item, $class);
            }
        }

        return $this;
    }

    #[\Override]
    public function validate(): ValidationResult {
        $validationErrors = [];

        foreach ($this->items as $item) {
            $result = $this->validator->validate($item);
            if ($result->isInvalid()) {
                $validationErrors[] = $result->getErrors();
            }
        }

        $errors = $validationErrors === []
            ? []
            : array_merge(...$validationErrors);

        return ValidationResult::make($errors, 'Sequence validation failed');
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool {
        if (!is_int($offset)) {
            return false;
        }

        return array_key_exists($offset, $this->items);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed {
        if (!is_int($offset)) {
            return null;
        }

        return $this->items[$offset] ?? null;
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void {
        if ($offset === null) {
            $this->items[] = $value;
            return;
        }

        if (!is_int($offset)) {
            return;
        }

        $this->items[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void {
        if (!is_int($offset)) {
            return;
        }

        if (!array_key_exists($offset, $this->items)) {
            return;
        }

        unset($this->items[$offset]);
        $this->items = array_values($this->items);
    }
}
