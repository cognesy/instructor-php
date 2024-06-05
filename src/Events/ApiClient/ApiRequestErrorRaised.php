<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Psr\Log\LogLevel;
use Saloon\Exceptions\Request\RequestException;

class ApiRequestErrorRaised extends Event
{
    public $logLevel = LogLevel::ERROR;

    public function __construct(
        public RequestException $exception
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->exception->getMessage();
    }
}