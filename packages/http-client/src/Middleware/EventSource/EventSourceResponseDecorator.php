<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\EventSource;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

class EventSourceResponseDecorator extends BaseResponseDecorator
{
    public function __construct(
        HttpRequest $request,
        HttpResponse $response,
        private readonly array $listeners,
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Transform underlying stream; notify listeners on chunk and assembled events.
     *
     * @param iterable<string> $source
     * @return iterable<string>
     */
    protected function transformStream(iterable $source): iterable {
        $buffer = '';
        foreach ($source as $chunk) {
            $buffer .= $chunk;
            foreach ($this->listeners as $listener) {
                $listener->onStreamChunkReceived($this->request, $this->response, $chunk);
            }
            if (strpos($buffer, "\n") !== false) {
                $buffer = trim($buffer);
                if ($buffer !== '') {
                    foreach ($this->listeners as $listener) {
                        $listener->onStreamEventAssembled($this->request, $this->response, $buffer);
                    }
                }
                $buffer = '';
            }
            yield $chunk;
        }
    }
}
