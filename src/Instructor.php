<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Core\EventDispatcher;
use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Events\Instructor\RequestReceived;
use Cognesy\Instructor\Events\Instructor\ResponseSent;
use Cognesy\Instructor\Utils\Configuration;

/**
 * Main access point to Instructor.
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    public Configuration $config;

    public function withOverride(array $config) : self {
        $this->config = Configuration::fresh($config);
        return $this;
    }

    public function withConfiguration(array $config) : self {
        $this->config = Configuration::fresh($config);
        return $this;
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
        // create request event immediately, so we get accurate timestamp
        $request = new Request($messages, $responseModel, $model, $maxRetries, $options);
        // initialize config - if not already done
        if (!isset($this->config)) {
            $this->config = Configuration::fresh();
        }
        // get event dispatcher and wiretap it
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->config->get(EventDispatcher::class);
        $eventDispatcher->wiretap(function ($event) {
//            if (!($event instanceof ResponseSent)) {
//                return;
//            }
            dump((string) $event);
        });
        // ...only now we dispatch the request received event
        $eventDispatcher->dispatch(new RequestReceived($request));
        // now we can handle the request
        $requestHandler = $this->config->get(RequestHandler::class);
        $response = $requestHandler->respond($request);
        $eventDispatcher->dispatch(new ResponseSent($response));
        // ...and return the response
        return $response;
    }
}
