<?php

namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Field\Field;

trait ConvertsToString
{
    public function toString() : string {
        $inputs = array_map(fn(Field $field) => $this->fieldSignature($field), $this->getInputFields());
        $outputs = array_map(fn(Field $field) => $this->fieldSignature($field), $this->getOutputFields());
        return implode(', ', $inputs)
            . ' ' . self::ARROW . ' '
            . implode(',', $outputs);
    }

    private function fieldSignature(Field $field) : string {
        $description = '';
        if (!empty($field->description())) {
            $description = ' (' . $field->description() . ')';
        }
        return $field->name() . ':' . $field->typeDetails()->toString() . $description;
    }
}
