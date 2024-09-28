<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\PartialApiResponse;
use Cognesy\Instructor\Utils\Json\Json;

class PartialApiResponseReceived extends Event
{
    public function __construct(
        public PartialApiResponse $partialApiResponse
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->partialApiResponse);
    }
}
