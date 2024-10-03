<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\LLMApiResponse;
use Cognesy\Instructor\Utils\Json\Json;

class ApiResponseReceived extends Event
{
    public function __construct(
        public LLMApiResponse $apiResponse,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->apiResponse);
    }
}
