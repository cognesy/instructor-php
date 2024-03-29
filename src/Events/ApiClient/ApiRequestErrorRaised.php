<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Saloon\Exceptions\Request\RequestException;

class ApiRequestErrorRaised extends Event
{
    public function __construct(
        public RequestException $exception
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->exception->getMessage();
    }
}