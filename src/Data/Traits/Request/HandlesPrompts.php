<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Template;
use Exception;

trait HandlesPrompts
{
    private array $defaultPrompts = [
        Mode::MdJson->value => "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n{json_schema}\n",
        Mode::Json->value => "\nRespond correctly with JSON object. Response must follow JSONSchema:\n{json_schema}\n",
        Mode::Tools->value => "\nExtract correct and accurate data from the input using provided tools. Response must be JSON object following provided schema.\n",
    ];
    private string $dataPrompt = "Input acknowledged.";
    private $instructionsCallback = null;

    private string $prompt;

    public function prompt() : string {
        return $this->prompt ?: $this->defaultPrompts[$this->mode->value];
    }

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function makeInstructions() : array {
        if (!empty($this->instructionsCallback)) {
            $instructions = ($this->instructionsCallback)($this);
        } else {
            $instructions = $this->addInstructions();
        }
        return $instructions;
    }

    protected function addInstructions() : array {
        if (empty($this->messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }
        $content = '';
        if (!empty($this->prompt())) {
            $content .= Template::render($this->prompt(), ['json_schema' => $this->jsonSchema()]);
        }
        if (!empty($this->examples)) {
            foreach ($this->examples as $example) {
                $content .= $example->toString() . "\n\n";
            }
        }
        return array_merge(
            $this->normalizeMessages($this->messages),
            [['role' => 'assistant', 'content' => $this->dataPrompt]],
            [['role' => 'user', 'content' => $content]],
        );
    }
}