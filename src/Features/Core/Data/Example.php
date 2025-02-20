<?php
namespace Cognesy\Instructor\Features\Core\Data;

use Cognesy\Instructor\Contracts\CanProvideJson;
use Cognesy\Utils\Messages\Contracts\CanProvideMessages;
use JsonSerializable;

class Example implements CanProvideMessages, CanProvideJson, JsonSerializable
{
    use Traits\Example\HandlesAccess;
    use Traits\Example\HandlesCreation;
    use Traits\Example\HandlesConversion;
    use Traits\Example\HandlesTemplates;

    private mixed $input;
    private mixed $output;
    private bool $isStructured;
    private string $template;

    public function __construct(
        mixed $input,
        mixed $output,
        bool $isStructured = true,
        string $template = '',
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->isStructured = $isStructured;
        $this->template = $template;
    }
}
