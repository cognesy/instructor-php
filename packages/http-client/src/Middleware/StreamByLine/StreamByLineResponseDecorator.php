<?php

namespace Cognesy\Http\Middleware\StreamByLine;

use Cognesy\Http\BaseResponseDecorator;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\EventStreamReader;
use Cognesy\Utils\Events\EventDispatcher;
use Generator;

class StreamByLineResponseDecorator extends BaseResponseDecorator
{
    private EventStreamReader $eventStreamReader;

    public function __construct(
        HttpClientRequest $request,
        HttpClientResponse $response,
        ?callable $parser,
        EventDispatcher $events,
    ) {
        parent::__construct($request, $response);
        $this->eventStreamReader = new EventStreamReader($parser, $events);
    }

    public function stream(int $chunkSize = 1): Generator
    {
        $stream = $this->response->stream($chunkSize);
        yield from $this->eventStreamReader->eventsFrom($stream);
    }
}