<?php

namespace Cognesy\Experimental\Module\Signature\Traits\Signature;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;

trait ConvertsToSignatureString
{
    public function toShortSignature(): string {
        return $this->shortSignature;
    }

    public function toSignatureString(): string {
        return $this->fullSignature;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    protected function makeShortSignatureString() : string {
        return $this->renderSignature($this->shortPropertySignature(...));
    }

    protected function makeSignatureString() : string {
        return $this->renderSignature($this->propertySignature(...));
    }

    private function renderSignature(callable $nameRenderer) : string {
        $inputs = $this->mapProperties($this->input->getPropertySchemas(), $nameRenderer);
        $outputs = $this->mapProperties($this->output->getPropertySchemas(), $nameRenderer);
        return implode('', [
            implode(', ', $inputs),
            (" " . Signature::ARROW . " "),
            implode(', ', $outputs)
        ]);
    }

    private function mapProperties(array $properties, callable $nameRenderer) : array {
        return array_map(
            fn(Schema $propertySchema) => $nameRenderer($propertySchema),
            $properties
        );
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