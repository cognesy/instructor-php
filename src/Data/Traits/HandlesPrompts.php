<?php

namespace Cognesy\Instructor\Data\Traits;

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

    private string $prompt;

    public function prompt() : string {
        return $this->prompt ?: $this->defaultPrompts[$this->mode->value];
    }

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }

//    public function appendInstructions(array $messages, array $jsonSchema) : array {
//        $lastIndex = count($messages) - 1;
//        if (!isset($messages[$lastIndex]['content'])) {
//            $messages[$lastIndex]['content'] = '';
//        }
//        $messages[$lastIndex]['content'] .= $this->prompt() . Json::encode($jsonSchema);
//        return $messages;
//    }

    public function appendInstructions(array $messages, string $prompt, array $jsonSchema) : array {
        if (empty($messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }
        $lastIndex = count($messages) - 1;
        if (!empty($this->prompt())) {
            $messages[$lastIndex]['content'] .= $prompt ?: $this->prompt();
        }
        if (!empty($jsonSchema)) {
            $messages[$lastIndex]['content'] .= Json::encode($jsonSchema);
        }
        return $messages;
    }
}