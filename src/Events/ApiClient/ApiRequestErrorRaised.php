<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Exception;
use Psr\Log\LogLevel;

class ApiRequestErrorRaised extends Event
{
    public $logLevel = LogLevel::ERROR;

    public function __construct(
        public Exception $exception,
        public array $request = [],
        public array $response = [],
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'exception' => $this->exception->getMessage(),
            'request' => $this->request,
            'response' => $this->response,
        ]);
    }
}