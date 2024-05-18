<?php

namespace Cognesy\Instructor\Data\Traits;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Json;
use Exception;

trait HandlesPrompts
{
    private array $defaultPrompts = [
        Mode::MdJson->value => "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n",
        Mode::Json->value => "\nRespond correctly with JSON object. Response must follow JSONSchema:\n",
        Mode::Tools->value => "\nExtract correct and accurate data from the messages using provided tools. Response must be JSON object following provided schema.\n",
    ];
    private string $dataPrompt = "Provide content for processing.";
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
            $instructions = $this->addInstructions(
                $this->messages,
                $this->prompt,
                $this->responseModel,
                $this->examples
            );
        }
        return $instructions;
    }

    protected function addInstructions(array $messages, string $prompt, ?ResponseModel $responseModel, array $examples) : array {
        if (empty($messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }
        $content = '';
        if (!empty($this->prompt())) {
            $content .= $prompt ?: $this->prompt();
        }
        $jsonSchema = $responseModel?->jsonSchema();
        if (!empty($jsonSchema)) {
            $content .= Json::encode($jsonSchema);
        }
        if (!empty($examples)) {
            foreach ($examples as $example) {
                $content .= $example->toString() . "\n\n";
            }
        }
        return array_merge(
            [['role' => 'user', 'content' => $content]],
            [['role' => 'assistant', 'content' => $this->dataPrompt]],
            $messages
        );
    }
}