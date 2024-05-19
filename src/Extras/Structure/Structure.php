<?php
namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Exception;

class Structure implements CanProvideSchema, CanDeserializeSelf, CanValidateSelf, CanTransformSelf
{
    use Traits\HandlesValidation;
    use Traits\ProvidesSchema;
    use Traits\HandlesDeserialization;
    use Traits\HandlesSerialization;
    use Traits\HandlesTransformation;
    use Traits\HandlesFieldAccess;
    use Traits\HandlesStructureInfo;

    public function __construct() {
        $this->schemaFactory = new SchemaFactory(false);
        $this->typeDetailsFactory = new TypeDetailsFactory();
        $this->deserializer = new Deserializer();
    }

    static public function define(
        string $name,
        array|callable $fields,
        string $description = '',
        string $instructions = '',
    ) : self {
        $structure = new Structure();
        $structure->name = $name;
        $structure->description = $description;
        $structure->instructions = $instructions;

        if (is_callable($fields)) {
            $fields = $fields($structure);
        }

        /** @var Field[] $fields */
        foreach ($fields as $field) {
            $fieldName = $field->name();
            if ($structure->has($fieldName)) {
                throw new Exception("Duplicate field `$fieldName` definition in structure `$name`");
            }
            $structure->fields[$fieldName] = $field;
        }
        return $structure;
    }
}
