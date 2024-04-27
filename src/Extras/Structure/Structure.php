<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

class Structure implements CanProvideSchema, CanDeserializeSelf, CanValidateSelf, CanTransformSelf
{
    use Traits\HandlesValidation;
    use Traits\ProvidesSchema;
    use Traits\HandlesDeserialization;
    use Traits\HandlesSerialization;
    use Traits\HandlesFieldAccess;
    use Traits\HandlesDescription;

    public function __construct() {
        $this->schemaFactory = new SchemaFactory(false);
        $this->typeDetailsFactory = new TypeDetailsFactory();
        $this->deserializer = new Deserializer();
    }

    static public function define(array|callable $fields, string $name = '', string $description = '') : self {
        $structure = new Structure();
        $structure->name = $name;
        $structure->description = $description;
        if (is_array($fields)) {
            /** @var Field[] $fields */
            foreach ($fields as $fieldName => $fieldType) {
                $structure->fields[$fieldName] = $fieldType->withName($fieldName);
            }
        } else {
            $structure->fields = $fields($structure);
        }
        return $structure;
    }

    public function transform(): mixed {
        return $this->toArray();
    }
}
