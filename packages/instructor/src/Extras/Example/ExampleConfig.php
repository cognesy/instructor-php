<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Example;

final readonly class ExampleConfig
{
    public string $textTemplate;
    public string $structuredTemplate;

    public function __construct(
        ?string $textTemplate = null,
        ?string $structuredTemplate = null,
    ) {
        $this->textTemplate = $textTemplate ?? $this->defaultTextTemplate();
        $this->structuredTemplate = $structuredTemplate ?? $this->defaultStructuredTemplate();
    }

    // SERIALIZATION ////////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'textTemplate' => $this->textTemplate,
            'structuredTemplate' => $this->structuredTemplate,
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            textTemplate: $data['textTemplate'] ?? null,
            structuredTemplate: $data['structuredTemplate'] ?? null,
        );
    }

    // INTERNAL /////////////////////////////////////////////////////////

    private function defaultTextTemplate() : string {
        return <<<TEMPLATE
            EXAMPLE INPUT:
            <|input|>
            
            EXAMPLE OUTPUT:
            <|output|>
        TEMPLATE;
    }

    private function defaultStructuredTemplate() : string {
        return <<<TEMPLATE
            EXAMPLE INPUT:
            <|input|>
            
            EXAMPLE OUTPUT:
            ```json
            <|output|>
            ```
        TEMPLATE;
    }
}