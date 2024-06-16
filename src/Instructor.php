<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\Configs\InstructorConfig;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Core\Factories\RequestFactory;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorReady;
use Cognesy\Instructor\Events\Instructor\InstructorStarted;
use Cognesy\Instructor\Utils\Env;

//use Cognesy\Instructor\Logging\EventLogger;
//use Psr\Log\LoggerInterface;

/**
 * Main access point to Instructor.
 *
 * Use respond() method to generate structured responses from LLM calls.
 */
class Instructor {
    use Events\Traits\HandlesEvents;
    use Events\Traits\HandlesEventListeners;

    use Traits\HandlesEnv;

    use Traits\Instructor\HandlesApiClient;
    use Traits\Instructor\HandlesCaching;
    use Traits\Instructor\HandlesConfig;
    use Traits\Instructor\HandlesDebug;
    use Traits\Instructor\HandlesErrors;
    use Traits\Instructor\HandlesPartialUpdates;
    use Traits\Instructor\HandlesQueuedEvents;
    use Traits\Instructor\HandlesRequest;
    use Traits\Instructor\HandlesSequenceUpdates;
    use Traits\Instructor\HandlesUserAPI;

    //private LoggerInterface $logger;
    //private EventLogger $eventLogger;
    private ApiRequestConfig $apiRequestConfig;

    public function __construct(
        EventDispatcher $events = null,
        Configuration $config = null,
    ) {
        // queue 'STARTED' event, to dispatch it after user is ready to handle it
        $this->queueEvent(new InstructorStarted());

        // try loading .env (if paths are set)
        Env::load();

        // main event dispatcher
        $this->events = $events ?? new EventDispatcher('instructor');

        // wire up core components
        $this->config = $config ?? Configuration::fresh($this->events);
        $this->config->external(
            class: EventDispatcher::class,
            reference: $this->events
        );
        $this->config->external(
            class: Instructor::class,
            reference: $this
        );
        $this->config->fromConfigProvider(new InstructorConfig());

        // wire up logging
        //$this->logger = $this->config->get(LoggerInterface::class);
        //$this->eventLogger = $this->config->get(EventLogger::class);
        //$this->events->wiretap($this->eventLogger->eventListener(...));

        // get other components from configuration
        $this->requestFactory = $this->config->get(RequestFactory::class);
        $this->apiRequestConfig = $this->config->get(ApiRequestConfig::class);
        $this->clientFactory = $this->config->get(ApiClientFactory::class);
        $this->clientFactory->setDefault($this->config->get(CanCallApi::class));

        // queue 'READY' event
        $this->queueEvent(new InstructorReady($this->config));
    }
}
