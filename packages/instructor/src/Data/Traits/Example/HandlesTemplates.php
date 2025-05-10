<?php
namespace Cognesy\Instructor\Data\Traits\Example;

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