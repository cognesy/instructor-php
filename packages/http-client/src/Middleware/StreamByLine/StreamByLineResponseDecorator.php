<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\StreamByLine;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;
use Cognesy\Polyglot\Inference\Utils\EventStreamReader;
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

    #[\Override]
    public function stream(?int $chunkSize = null): \Generator
    {
        $stream = $this->response->stream($chunkSize);
        yield from $this->eventStreamReader->eventsFrom($stream);
    }
}