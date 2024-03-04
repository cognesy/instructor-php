<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Contracts\CanDeserialize;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanTransformResponse;
use Cognesy\Instructor\Contracts\CanValidateResponse;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\LLMs\OpenAI\LLM;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Factories\FunctionCallFactory;
use Cognesy\Instructor\Validators\Symfony\Validator;
use Exception;

/**
 * Main access point to Instructor.
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    private LLM $llm;
    public $retryPrompt = "Recall function correctly, fix following errors:";

    public function __construct(
        CanCallFunction $llm = null,
    ) {
        $this->llm = $llm ?? new LLM();
    }

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respond(
        string|array $messages,
        string|object|array $responseModel,
        string $model = 'gpt-4-0125-preview',
        int $maxRetries = 0,
        array $options = [],
    ) : mixed {
        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }
        $responseModelObject = new ResponseModel($responseModel);
        return $this->tryRespond(
            $messages,
            $model,
            $responseModelObject,
            $maxRetries,
            $options
        );
    }

    /**
     * Executes LLM call loop with validation until success or max retries reached
     */
    protected function tryRespond(
        array $messages,
        string $model,
        ResponseModel $responseModel,
        int $maxRetries,
        array $options
    ) : mixed {
        $retries = 0;
        while ($retries <= $maxRetries) {
            $json = $this->llm->callFunction(
                $messages,
                $responseModel->functionName,
                $responseModel->functionCall,
                $model,
                $options
            );
            [$object, $errors] = $responseModel->toResponse($json);
            if (empty($errors)) {
                if ($object instanceof CanTransformResponse) {
                    return $object->transform();
                }
                return $object;
            }
            $messages[] = ['role' => 'assistant', 'content' => $json];
            $messages[] = ['role' => 'user', 'content' => $this->retryPrompt . '\n' . $errors];
            $retries++;
        }
        throw new Exception("Failed to extract data due to validation constraints: " . $errors);
    }

    /**
     * Raw JSON string returned by LLM
     */
    public function json() : string {
        return $this->llm->data();
    }

    /**
     * Response data, see: API client documentation (e.g. OpenAI)
     */
    public function response() : array {
        return $this->llm->response();
    }
}
