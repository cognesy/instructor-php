<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Utils\Configuration;

/**
 * Main access point to Instructor.
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    public Configuration $config;
    public RequestHandler $requestHandler;

    public function __construct(array $config = []) {
        $this->config = Configuration::fresh($config);
        $this->requestHandler = $this->config->get(RequestHandler::class);
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
        return $this->requestHandler->respond(
            new Request($messages, $responseModel, $model, $maxRetries, $options)
        );
    }
}
