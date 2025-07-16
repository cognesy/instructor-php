<?php declare(strict_types=1);
namespace Cognesy\Instructor\Extras\Example\Traits;

trait HandlesTemplates
{
    public string $defaultTextTemplate = <<<TEMPLATE
        EXAMPLE INPUT:
        <|input|>
        
        EXAMPLE OUTPUT:
        <|output|>
        TEMPLATE;

    public string $defaultStructuredTemplate = <<<TEMPLATE
        EXAMPLE INPUT:
        <|input|>
        
        EXAMPLE OUTPUT:
        ```json
        <|output|>
        ```
        TEMPLATE;

    public function template() : string {
        return match(true) {
            !empty($this->template) => $this->template,
            default => match(true) {
                $this->isStructured => $this->defaultStructuredTemplate,
                default => $this->defaultTextTemplate,
            },
        };
    }
}