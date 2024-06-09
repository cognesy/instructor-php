<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Core\Factories\ResponseModelFactory;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Logging\EventLogger;
use Cognesy\Instructor\Utils\Env;
use Psr\Log\LoggerInterface;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    use Events\Traits\HandlesEvents;
    use Events\Traits\HandlesEventListeners;

    use Traits\HandlesEnv;
    use Traits\HandlesTimer;

    use Traits\Instructor\HandlesApiClient;
    use Traits\Instructor\HandlesCaching;
    use Traits\Instructor\HandlesConfig;
    use Traits\Instructor\HandlesDebug;
    use Traits\Instructor\HandlesErrors;
    use Traits\Instructor\HandlesPartialUpdates;
    use Traits\Instructor\HandlesQueuedEvents;
    use Traits\Instructor\HandlesRequest;
    use Traits\Instructor\HandlesSchema;
    use Traits\Instructor\HandlesSequenceUpdates;
    use Traits\Instructor\HandlesUserAPI;

    private LoggerInterface $logger;
    private EventLogger $eventLogger;
    private ApiRequestConfig $apiRequestConfig;

    public function __construct(array $config = []) {
        $this->queueEvent(new InstructorStarted($config));
        // try loading .env (if paths are set)
        Env::load();
        $this->config = Configuration::fresh($config);
        $this->events = $this->config->get(EventDispatcher::class);
        $this->clientFactory = $this->config->get(ApiClientFactory::class);
        $this->clientFactory->setDefault($this->config->get(CanCallApi::class));
        $this->requestFactory = /** @var RequestFactory */ $this->config->get(RequestFactory::class);
        $this->responseModelFactory = $this->config->get(ResponseModelFactory::class);
        $this->apiRequestConfig = $this->config->get(ApiRequestConfig::class);
        //$this->logger = $this->config->get(LoggerInterface::class);
        //$this->eventLogger = $this->config->get(EventLogger::class);
        //$this->events->wiretap($this->eventLogger->eventListener(...));
        $this->queueEvent(new InstructorReady($this->config));
    }
}
