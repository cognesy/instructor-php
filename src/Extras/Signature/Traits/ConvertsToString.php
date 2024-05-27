<?php

namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Contracts\DataModel\CanHandleDataField;
use Cognesy\Instructor\Utils\Template;

trait ConvertsToString
{
    public function toString() : string {
        $inputs = array_map(fn(CanHandleDataField $field) => $this->fieldSignature($field), $this->getInputFields());
        $outputs = array_map(fn(CanHandleDataField $field) => $this->fieldSignature($field), $this->getOutputFields());
        return implode(', ', $inputs)
            . ' ' . self::ARROW . ' '
            . implode(', ', $outputs);
    }

    private function fieldSignature(CanHandleDataField $field) : string {
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
