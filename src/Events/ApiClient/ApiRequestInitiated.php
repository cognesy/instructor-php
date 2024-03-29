<?php

namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Saloon\Http\Request;

class ApiRequestInitiated extends Event
{
    public function __construct(
        public Request $request,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->request);
    }
}
