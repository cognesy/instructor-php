<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Utils\Template;

trait ConvertsToString
{
    public function toString() : string {
        $inputs = array_map(fn(Field $field) => $this->fieldSignature($field), $this->getInputFields());
        $outputs = array_map(fn(Field $field) => $this->fieldSignature($field), $this->getOutputFields());
        return implode(', ', $inputs)
            . ' ' . self::ARROW . ' '
            . implode(', ', $outputs);
    }

    private function fieldSignature(Field $field) : string {
        $description = '';
        if (!empty($field->description())) {
            $description = ' (' . $field->description() . ')';
        }
        return $field->name() . ':' . $field->typeDetails()->toString() . $description;
    }

    public function toDefaultPrompt(): string {
        return Template::render($this->prompt, [
            'signature' => $this->toString(),
            'description' => $this->getDescription()
        ]);
    }
}
