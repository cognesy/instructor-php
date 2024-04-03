<?php

namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
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
        return Json::encode($this->request);
    }
}
