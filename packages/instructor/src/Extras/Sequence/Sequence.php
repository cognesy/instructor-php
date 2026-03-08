<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Sequence;

use ArrayAccess;
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
use Cognesy\Utils\Collection\ArrayList;
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

    /** @var ArrayList<object> */
    private ArrayList $items;

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
        $this->items = ArrayList::empty();
    }

    #[\Override]
    public static function of(string $class, string $name = '', string $description = '') : static {
        return new static($class, $name, $description);
    }

    #[\Override]
    public function toArray() : array {
        return $this->items->toArray();
    }

    public function all() : array {
        return $this->items->toArray();
    }

    #[\Override]
    public function count(): int {
        return $this->items->count();
    }

    #[\Override]
    public function get(int $index) : mixed {
        return $this->items->getOrNull($index);
    }

    public function first() : mixed {
        return $this->items->first();
    }

    public function last() : mixed {
        return $this->items->last();
    }

    #[\Override]
    public function push(mixed $item) : void {
        $this->items = $this->items->withAppended($item);
    }

    #[\Override]
    public function pop() : mixed {
        if ($this->items->isEmpty()) {
            return null;
        }

        $lastIndex = $this->items->count() - 1;
        $item = $this->items->last();
        $this->items = $this->items->withRemovedAt($lastIndex);
        return $item;
    }

    #[\Override]
    public function isEmpty() : bool {
        return $this->items->isEmpty();
    }

    #[\Override]
    public function getIterator() : Traversable {
        return $this->items->getIterator();
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
            $this->items = ArrayList::empty();
            return $this;
        }

        /** @var class-string<object> $class */
        $class = $this->class;
        $list = [];

        foreach ($returnedList as $item) {
            if (is_array($item)) {
                $list[] = $this->deserializer->fromArray($item, $class);
            }
        }

        $this->items = ArrayList::fromArray($list);
        return $this;
    }

    #[\Override]
    public function validate(): ValidationResult {
        $validationErrors = [];

        foreach ($this->items->toArray() as $item) {
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

        return $this->items->getOrNull($offset) !== null;
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed {
        if (!is_int($offset)) {
            return null;
        }

        return $this->items->getOrNull($offset);
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void {
        if ($offset === null) {
            $this->push($value);
            return;
        }

        if (!is_int($offset)) {
            return;
        }

        $items = $this->items->toArray();
        $items[$offset] = $value;
        $this->items = ArrayList::fromArray(array_values($items));
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void {
        if (!is_int($offset)) {
            return;
        }

        $items = $this->items->toArray();
        if (!array_key_exists($offset, $items)) {
            return;
        }

        unset($items[$offset]);
        $this->items = ArrayList::fromArray(array_values($items));
    }
}
