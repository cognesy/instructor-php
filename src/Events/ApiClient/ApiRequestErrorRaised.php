<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Exception;
use Psr\Log\LogLevel;

class ApiRequestErrorRaised extends Event
{
    public $logLevel = LogLevel::ERROR;

    public function __construct(
        public Exception $exception
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->exception->getMessage();
    }
}