<?php
namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Enums\Mode;

trait HandlesPrompts
{
    private array $defaultPrompts = [
        Mode::MdJson->value => "Respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n<|json_schema|>\n",
        Mode::Json->value => "Respond correctly with strict JSON object. Response must follow JSONSchema:\n<|json_schema|>\n",
        Mode::Tools->value => "Extract correct and accurate data from the input using provided tools. Response must be JSON object following provided tool schema.\n<|json_schema|>\n",
    ];
    private string $dataAckPrompt = "Input acknowledged.";
    private string $prompt;

    public function dataAckPrompt() : string {
        return $this->dataAckPrompt;
    }

    public function prompt() : string {
        return $this->prompt ?: $this->defaultPrompts[$this->mode()->value] ?? '';
    }

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }
}