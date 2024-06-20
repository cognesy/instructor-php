<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Utils\Json;

class ApiStreamResponseReceived extends ApiResponseReceived
{
    public function __construct(
        public int $status,
    ) {
        parent::__construct($status);
    }

    public function __toString() : string {
        return Json::encode([
            'status' => $this->status,
        ]);
    }
}
