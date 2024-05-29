<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait ConvertsToSignatureString
{
    public function toShortSignature() : string {
        $inputs = array_map(
            fn(Schema $propertySchema) => $this->shortPropertySignature($propertySchema),
            $this->data()->getInputSchemas()
        );
        $outputs = array_map(
            fn(Schema $propertySchema) => $this->shortPropertySignature($propertySchema),
            $this->data()->getOutputSchemas()
        );
        return implode(', ', $inputs)
            . ' ' . Signature::ARROW . ' '
            . implode(', ', $outputs);
    }

    public function toSignatureString() : string {
        $inputs = array_map(
            fn(Schema $propertySchema) => $this->propertySignature($propertySchema),
            $this->data()->getInputSchemas()
        );
        $outputs = array_map(
            fn(Schema $propertySchema) => $this->propertySignature($propertySchema),
            $this->data()->getOutputSchemas()
        );
        return implode(', ', $inputs)
            . ' ' . Signature::ARROW . ' '
            . implode(', ', $outputs);
    }

    private function propertySignature(Schema $schema) : string {
        $description = '';
        if (!empty($schema->description())) {
            $description = ' (' . $schema->description() . ')';
        }
        return $schema->name() . ':' . $schema->typeDetails()->toString() . $description;
    }

    private function shortPropertySignature(Schema $schema) : string {
        return $schema->name();
    }
}