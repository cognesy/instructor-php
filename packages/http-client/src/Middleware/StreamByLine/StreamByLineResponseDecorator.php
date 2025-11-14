<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\StreamByLine;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;
use Cognesy\Polyglot\Inference\Streaming\EventStreamReader;
use Psr\EventDispatcher\EventDispatcherInterface;

class StreamByLineResponseDecorator extends BaseResponseDecorator
{
    private EventStreamReader $eventStreamReader;

    /**
     * @param callable(string): (bool|string)|null $parser
     */
    public function __construct(
        HttpRequest              $request,
        HttpResponse             $response,
        ?callable                $parser,
        EventDispatcherInterface $events,
    ) {
        parent::__construct($request, $response);
        $this->eventStreamReader = new EventStreamReader(
            events: $events,
            parser: $parser !== null ? \Closure::fromCallable($parser) : null,
        );
    }

    /**
     * Transform underlying stream into event-stream chunks using EventStreamReader.
     *
     * @param iterable<string> $source
     * @return iterable<string>
     */
    protected function transformStream(iterable $source): iterable {
        yield from $this->eventStreamReader->eventsFrom($source);
    }
}
