<?php declare(strict_types=1);

namespace Cognesy\Experimental\Module\Config;

final readonly class PredictorConfig
{
    public string $textOutputTemplate;
    public string $structuredOutputTemplate;

    public function __construct(
        ?string $textOutputTemplate = null,
        ?string $structuredOutputTemplate = null,
    ) {
        $this->textOutputTemplate = $textOutputTemplate ?? $this->defaultTextOutputTemplate();
        $this->structuredOutputTemplate = $structuredOutputTemplate ?? $this->defaultStructuredOutputTemplate();
    }

    private function defaultTextOutputTemplate() : string {
        return <<<TEMPLATE
            YOUR TASK:
            <|instructions|>
            
            INPUT DATA:
            <|input|>
            
            RESPONSE:
        TEMPLATE;
    }

    private function defaultStructuredOutputTemplate() : string {
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