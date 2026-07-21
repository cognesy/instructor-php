<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\RequestRedactor;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\ExactHashMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\LegacyExactHashMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\RecordingStream;

/**
 * Transitional adapter for the pre-cassette RequestRecords implementation.
 *
 * The adapter is deliberately not part of the documented API. It lets the new
 * immutable facade ship before the versioned cassette store replaces the legacy
 * one-file persistence in the next task.
 */
final class LegacyCassetteStore implements CassetteStore
{
    public function __construct(
        private readonly RequestRecords $records,
    ) {
    }

    public static function fromDirectory(
        string $directory,
        RequestRedactor $redactor,
        \Cognesy\Http\Extras\Support\RecordReplay\Matching\RequestMatcher $matcher,
    ): self {
        $legacyMatcher = $matcher instanceof ExactHashMatcher
            ? new LegacyExactHashMatcher()
            : $matcher;

        return new self(new RequestRecords($directory, $legacyMatcher, $redactor));
    }

    #[\Override]
    public function record(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        if (!$response->isStreamed()) {
            $this->records->save($request, $response);
            return $response;
        }

        return HttpResponse::streaming(
            statusCode: $response->statusCode(),
            headers: $response->headers(),
            stream: new RecordingStream(
                source: $response->rawStream(),
                onCompleted: function (iterable $chunks) use ($request, $response): void {
                    $this->records->saveStreamed($request, $response, $chunks);
                },
            ),
        );
    }

    #[\Override]
    public function replay(HttpRequest $request): ?HttpResponse
    {
        $record = $this->records->find($request);

        return $record?->toResponse($request->isStreamed());
    }
}
