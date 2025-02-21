<?php
namespace Cognesy\Polyglot\Http\Events;

use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

class HttpResponseReceived extends \Cognesy\Utils\Events\Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public int $statusCode,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'statusCode' => $this->statusCode
        ]);
    }
}