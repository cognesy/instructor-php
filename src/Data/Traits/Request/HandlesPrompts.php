<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Core\MessageBuilder;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Template;
use Exception;

trait HandlesPrompts
{
    private array $defaultPrompts = [
        Mode::MdJson->value => "\nRespond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n{json_schema}\n",
        Mode::Json->value => "\nRespond correctly with strict JSON object. Response must follow JSONSchema:\n{json_schema}\n",
        Mode::Tools->value => "\nExtract correct and accurate data from the input using provided tools. Response must be JSON object following provided tool schema.\n",
    ];
    private string $dataAcknowledgedPrompt = "Input acknowledged.";
    private string $prompt;

    public function prompt() : string {
        return $this->prompt ?: $this->defaultPrompts[$this->mode->value] ?? '';
    }

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function makeOptions() : array {
        if (empty($this->messages)) {
            throw new Exception('Messages cannot be empty - you have to provide the content for processing.');
        }

        if (empty($this->client())) {
            throw new Exception('Client is required to render request body.');
        }

        $body = MessageBuilder::requestBody(
            clientClass: get_class($this->client()),
            mode: $this->mode(),
            messages: $this->messages(),
            responseModel: $this->responseModel(),
            dataAcknowledgedPrompt: $this->dataAcknowledgedPrompt,
            prompt: Template::render($this->prompt(), ['json_schema' => $this->jsonSchema()]),
            examples: $this->examples(),
        );

        return array_merge(
            $this->options,
            $body,
        );
    }
}