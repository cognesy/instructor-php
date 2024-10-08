<?php

namespace Cognesy\Instructor\Features\Schema\Factories;

use Cognesy\Instructor\Extras\Maybe\Maybe;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Data\TypeDetails;
use Cognesy\Instructor\Features\Schema\PropertyMap;
use Cognesy\Instructor\Features\Schema\SchemaMap;
use Exception;

/**
 * Factory for creating schema objects from class names
 *
 * NOTE: Currently, OpenAI models do not comprehend well object references for
 * complex structures, so it's safer to return the full object schema with all
 * properties inlined.
 *
 */
class SchemaFactory
{
    use \Cognesy\Instructor\Features\Schema\Factories\Traits\SchemaFactory\HandlesClassInfo;
    use \Cognesy\Instructor\Features\Schema\Factories\Traits\SchemaFactory\HandlesFactoryMethods;
    use \Cognesy\Instructor\Features\Schema\Factories\Traits\SchemaFactory\HandlesTypeDetails;

    /** @var bool switches schema rendering between inlined or referenced object properties */
    protected bool $useObjectReferences;

    protected SchemaMap $schemaMap;
    protected PropertyMap $propertyMap;
    protected TypeDetailsFactory $typeDetailsFactory;

    private array $warnOnInstancesOf = [
        Structure::class,
        Sequence::class,
        Scalar::class,
        Maybe::class,
    ];

    public function __construct(
        bool $useObjectReferences = false,
    ) {
        $this->useObjectReferences = $useObjectReferences;
        //
        $this->schemaMap = new SchemaMap;
        $this->propertyMap = new PropertyMap;
        $this->typeDetailsFactory = new TypeDetailsFactory;
    }

    /**
     * Extracts the schema from a class and constructs a function call
     *
     * @param string $anyType - class name, enum name or type name string OR TypeDetails object OR any object instance
     */
    public function schema(string|object $anyType) : Schema
    {
        if ($this->isAnyOf($anyType)) {
            throw new Exception('You are trying to get static schema for a known dynamic schema provider: ' . get_class($anyType) . ' directly');
        }

        $type = match(true) {
            $anyType instanceof TypeDetails => $anyType,
            is_string($anyType) => $this->typeDetailsFactory->fromTypeName($anyType),
            is_object($anyType) => $this->typeDetailsFactory->fromTypeName(get_class($anyType)),
            default => throw new \Exception('Unknown input type: '.gettype($anyType)),
        };

        $typeString = (string) $type;

        // if schema is not registered, create it, register it and return it
        if (!$this->schemaMap->has($type)) {
            $this->schemaMap->register(
                typeName: $typeString,
                schema: $this->makeSchema($type));
        }
        return $this->schemaMap->get($anyType);
    }

    /**
     * Creates schema for a property with provided parameters
     * @param \Cognesy\Instructor\Features\Schema\Data\TypeDetails $type
     * @param string $name
     * @param string $description
     * @return \Cognesy\Instructor\Features\Schema\Data\Schema\Schema
     */
    public function propertySchema(TypeDetails $type, string $name, string $description) : Schema {
        return $this->makePropertySchema($type, $name, $description);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function isAnyOf(string|object $anyType) : bool {
        return array_reduce(
            array: $this->warnOnInstancesOf,
            callback: function ($carry, $item) use ($anyType) {
                return $carry || ($anyType instanceof $item);
            },
            initial: false
        );
    }
}
