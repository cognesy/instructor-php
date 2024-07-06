<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Signature;

trait HandlesTemplates
{
    public function toTemplate(bool $textMode = false) : string {
        return match($textMode) {
            true => $this->toTextTemplate(),
            default => $this->toStructuredTemplate()
        };
    }

    public function toTextTemplate() : string {
        return <<<TEMPLATE
            YOUR TASK:
            <|instructions|>
            
            INPUT DATA:
            <|input|>
            
            RESPONSE:
        TEMPLATE;
    }

    public function toStructuredTemplate() : string {
        return <<<TEMPLATE
            YOUR TASK:
            <|instructions|>
            
            INPUT DATA:
            ```json
            <|input|>
            ```
            
            OUTPUT JSON SCHEMA:
            ```json
            <|json_schema|>
            ```
            
            RESPONSE IN JSON:
            ```json
        TEMPLATE;
    }
}
