<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Contracts\CanDeserialize;
use Cognesy\Instructor\Contracts\CanValidate;
use Cognesy\Instructor\Schema\FunctionCallSchema;
use Exception;

class Instructor {
    private Deserializer $deserializer;
    private Validator $validator;
    private LLM $llm;
    private string $functionName = 'extract_data';
    private string $functionDescription = 'Extract data from provided content';
    private $retryPrompt = "Recall function correctly, fix following errors:";

    public function __construct(
        CanCallFunction $llm = null,
        CanDeserialize $deserializer = null,
        CanValidate $validator = null
    ) {
        $this->llm = $llm ?? new LLM();
        $this->deserializer = $deserializer ?? new Deserializer();
        $this->validator = $validator ?? new Validator();
    }

    public function respond(
        array $messages,
        string|object $responseModel,
        string $model = 'gpt-4-0125-preview',
        int $maxRetries = 0,
        array $options = []
    ) : ?object {
        $schema = (new FunctionCallSchema)->withClass(
            $responseModel,
            $this->functionName,
            $this->functionDescription
        );
        $retries = 0;
        while ($retries <= $maxRetries) {
            $json = $this->llm->callFunction($messages, $this->functionName, $schema, $model, $options);
            $object = $this->deserializer->deserialize($json, $responseModel);
            if ($this->validator->validate($object)) {
                return $object;
            }
            $messages[] = ['role' => 'assistant', 'content' => $json];
            $messages[] = ['role' => 'user', 'content' => $this->retryPrompt . '\n' . $this->validator->errors()];
            $retries++;
        }
        throw new Exception("Failed to extract data due to validation constraints: ", $this->validator->errors());
    }

    public function json() : string {
        return $this->llm->data();
    }

    public function response() : array {
        return $this->llm->response();
    }
}
