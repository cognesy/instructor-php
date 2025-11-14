<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay\Events;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

/**
 * Event fired when an HTTP interaction is recorded
 */
final class HttpInteractionRecorded extends \Cognesy\Events\Event
{
    public function __construct(
        public readonly HttpRequest  $request,
        public readonly HttpResponse $response
    ) {
        parent::__construct();
    }

    public function toConsole(): string
    {
        return sprintf(
            "[RECORDED] %s %s => HTTP %d",
            $this->request->method(),
            $this->request->url(),
            $this->response->statusCode()
        );
    }
}
