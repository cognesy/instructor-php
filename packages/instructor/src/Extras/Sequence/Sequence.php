<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Sequence;

use ArrayAccess;
use ArrayIterator;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use IteratorAggregate;
use Traversable;

class Sequence implements
    Sequenceable,
    IteratorAggregate,
    CanProvideSchema,
    CanDeserializeSelf,
    CanValidateSelf,
    ArrayAccess
{
    use Traits\HandlesArrayAccess;
    use Traits\HandlesDeserialization;
    use Traits\HandlesSequenceable;
    use Traits\HandlesValidation;

    private string $class;
    private string $name;
    private string $description;
    public array $list = [];

    public function __construct(
        string $class = '',
        string $name = '',
        string $description = '',
        //array $list = [],
    ) {
        $this->class = $class;
        $this->name = $name;
        $this->description = $description;
        //$this->list = $list;

        $this->deserializer = new SymfonyDeserializer();
        $this->validator = new SymfonyValidator();
    }

    public function getIterator() : Traversable {
        return new ArrayIterator($this->list);
    }

    public function toSchema(): Schema {
        $collectionSchema = Schema::collection(
            nestedType: $this->class,
            name: 'list',
        );
        $nestedTypeDetails = TypeDetails::fromTypeName($this->class);
        return Schema::object(
            class: Sequence::class,
            name: $this->name ?: ('collectionOf' . $nestedTypeDetails->classOnly()),
            description: $this->description ?: ('A collection of ' . $this->class),
            properties: ['list' => $collectionSchema],
            required: ['list'],
        );
    }
}
