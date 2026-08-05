<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Support\Redaction;

use Cognesy\Http\Data\HttpRequest;
use Throwable;

/**
 * The redaction shaping that both base drivers apply before an HTTP request or response
 * reaches an event payload (instructor-eexl.12).
 *
 * These five methods were byte-identical in `BaseInferenceRequestDriver` and
 * `BaseEmbedDriver` -- verified by diffing the extracted regions, not by reading them. That
 * is the shape that produced the `attemptId` drift in `packages/instructor`: two copies of a
 * security-relevant rule, either of which can be tightened without the other.
 *
 * `SensitiveDataRedactor` decides WHAT is sensitive; this trait decides WHERE the drivers
 * apply it. The split matters -- the redactor is a pure, testable rule set, while the shaping
 * below knows about `HttpRequest`'s payload structure.
 *
 * ERROR PATH ONLY. Nothing here runs on a successful request, let alone per delta, so it is
 * outside every cost tier. `$payload['body']` is masked wholesale rather than walked: a
 * request body is caller content, and no key-based rule can be trusted to find a credential
 * a user embedded in a prompt.
 */
trait RedactsHttpPayloads
{
    /**
     * @return array<string,mixed>
     */
    private function redactedRequest(HttpRequest $request): array {
        $payload = $request->toArray();
        $payload['url'] = $this->redactedUrl($request->url());
        $payload['headers'] = $this->redactedHeaders($request->headers());
        $payload['body'] = '[REDACTED]';
        if (isset($payload['options']) && is_array($payload['options'])) {
            $payload['options'] = $this->redactedValues($payload['options']);
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $headers
     * @return array<string,mixed>
     */
    private function redactedHeaders(array $headers): array {
        return SensitiveDataRedactor::redactHeaders($headers);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactedValues(array $data): array {
        return SensitiveDataRedactor::redactValues($data);
    }

    private function redactedUrl(string $url): string {
        return SensitiveDataRedactor::redactUrl($url);
    }

    private function redactedExceptionMessage(Throwable $source, HttpRequest $request): string {
        return SensitiveDataRedactor::redactUrlInText($source->getMessage(), $request->url());
    }
}
