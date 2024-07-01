<?php
namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

trait HandlesConversion
{
    public function toInputSchema(): Schema {
        return $this->input;
    }

    public function toOutputSchema(): Schema {
        return $this->output;
    }

    public function toSchema(): Schema {
        return $this->output;
    }

    public function toShortSignature(): string {
        return $this->shortSignature;
    }

    public function toSignatureString(): string {
        return $this->fullSignature;
    }

    public function toTemplate() : string {
        return <<<TEMPLATE
            YOUR TASK:
            {$this->toSignatureString()}
            {$this->getDescription()}
            
            INPUT DATA:
            <|input|>
            
            RESPONSE:
        TEMPLATE;
    }

    public function toStructuredTemplate() : string {
        return <<<TEMPLATE
            YOUR TASK:
            {$this->toSignatureString()}
            {$this->getDescription()}
            
            INPUT DATA:
            <|input|>
            
            OUTPUT JSON SCHEMA:
            {$this->toOutputSchema()->toJsonSchema()}
            
            RESPONSE IN JSON:
        TEMPLATE;
    }
}
