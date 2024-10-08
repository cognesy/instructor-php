<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Structure;

use Exception;

trait HandlesDefinition
{
    static public function define(
        string $name,
        array|callable $fields,
        string $description = '',
    ) : self {
        $structure = new \Cognesy\Instructor\Extras\Structure\Structure();
        $structure->name = $name;
        $structure->description = $description;

        if (is_callable($fields)) {
            $fields = $fields($structure);
        }

        /** @var \Cognesy\Instructor\Extras\Structure\Field[] $fields */
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