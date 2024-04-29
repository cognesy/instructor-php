<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Utils\Json;
use Saloon\Http\Response;

class ApiStreamResponseReceived extends ApiResponseReceived
{
    public function __construct(
        public Response $response,
    ) {
        parent::__construct($response);
    }

    public function __toString() : string {
        return Json::encode([
            'status' => $this->response->status(),
            'body' => $this->response->body(),
            'headers' => $this->response->headers(),
        ]);
    }
}
