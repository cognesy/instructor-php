<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Support;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use InvalidArgumentException;
use RuntimeException;

final readonly class BoundedHttpClient
{
    public function __construct(
        private CanSendHttpRequests $http,
        private string $backend,
        private int $maxResponseBytes = 8_388_608,
    ) {
        if ($backend === '' || $maxResponseBytes < 1) {
            throw new InvalidArgumentException('HTTP backend name and positive response limit are required');
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<int>  $acceptedStatuses
     */
    public function send(
        string $method,
        string $url,
        array $headers = [],
        string $body = '',
        array $acceptedStatuses = [200],
    ): BoundedHttpResponse {
        $pending = $this->http->send(new HttpRequest(
            url: $url,
            method: $method,
            headers: $headers,
            body: $body,
            options: ['stream' => true],
        ));
        $status = $pending->statusCode();
        $content = '';
        foreach ($pending->stream() as $chunk) {
            if (strlen($content) + strlen($chunk) > $this->maxResponseBytes) {
                throw new RuntimeException("{$this->backend} response exceeds configured byte limit");
            }
            $content .= $chunk;
        }
        if (! in_array($status, $acceptedStatuses, true)) {
            throw new RuntimeException("{$this->backend} request failed with HTTP {$status}");
        }

        return new BoundedHttpResponse($status, $content);
    }
}
