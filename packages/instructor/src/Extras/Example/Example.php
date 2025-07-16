<?php declare(strict_types=1);
namespace Cognesy\Instructor\Extras\Example;

use Cognesy\Instructor\Data\Traits;
use Cognesy\Instructor\Extras\Example\Traits\HandlesAccess;
use Cognesy\Instructor\Extras\Example\Traits\HandlesConversion;
use Cognesy\Instructor\Extras\Example\Traits\HandlesCreation;
use Cognesy\Instructor\Extras\Example\Traits\HandlesTemplates;
use Cognesy\Utils\Messages\Contracts\CanProvideMessages;
use JsonSerializable;

class Example implements CanProvideMessages, JsonSerializable
{
    use HandlesAccess;
    use HandlesCreation;
    use HandlesConversion;
    use HandlesTemplates;

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

    public function clone() : self {
        return new static(
            input: clone $this->input,
            output: clone $this->output,
            isStructured: $this->isStructured,
            template: $this->template
        );
    }
}
