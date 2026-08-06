<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Redaction;

use Cognesy\Http\Extras\Support\RecordReplay\FixtureSanitizer;
use Cognesy\Http\Extras\Support\RecordReplay\SensitiveDataNames;

/**
 * Masks credential-bearing request headers. The header set is provider-aware:
 * different providers carry the API key under different names (Authorization for
 * most, x-api-key for Anthropic, x-goog-api-key for Gemini) — a redactor keyed
 * only on Authorization would leak Gemini's key. Header matching is
 * case-insensitive; matched values are replaced with a fixed mask.
 *
 * Bodies are left untouched by default (for LLM providers the credential lives in
 * headers, and the body participates in request matching); redact known sensitive
 * body fields via a custom RequestRedactor when a provider puts secrets there.
 */
final class DefaultRequestRedactor implements FixtureSanitizer
{
    public const MASK = '[REDACTED]';

    /** @deprecated Use SensitiveDataNames::QUERY_PARAMETERS. */
    public const SENSITIVE_QUERY_PARAMS = SensitiveDataNames::QUERY_PARAMETERS;

    private const STREAM_TAIL_BYTES = 256;
    private const BEARER_PATTERN = '/Bearer\s+/i';

    #[\Override]
    public function redact(array $requestData): array {
        $headers = $requestData['headers'] ?? null;
        if (is_array($headers)) {
            $requestData['headers'] = self::redactHeaders($headers);
        }

        // Some providers (e.g. Gemini) pass the API key as a URL query parameter,
        // not a header — mask those values too so no key reaches disk.
        if (isset($requestData['url']) && is_string($requestData['url'])) {
            $requestData['url'] = self::redactUrl($requestData['url']);
        }

        return $requestData;
    }

    #[\Override]
    public function redactResponse(array $responseData): array {
        if (is_array($responseData['headers'] ?? null)) {
            $responseData['headers'] = self::redactHeaders($responseData['headers']);
        }

        if (is_string($responseData['body'] ?? null)) {
            $responseData['body'] = self::redactBody($responseData['body']);
        }

        if (is_array($responseData['chunks'] ?? null)) {
            $responseData['chunks'] = self::redactChunks($responseData['chunks']);
        }

        return $responseData;
    }

    /**
     * @param iterable<string> $chunks
     * @return iterable<string>
     */
    public function redactStream(iterable $chunks): iterable {
        $pending = '';
        $mode = 'normal';
        $quote = null;
        $escaped = false;

        foreach ($chunks as $chunk) {
            $pending .= $chunk;
            $output = self::redactBuffer($pending, $mode, $quote, $escaped, false);
            if ($output !== '' || $chunk === '') {
                yield $output;
            }
        }

        $output = self::redactBuffer($pending, $mode, $quote, $escaped, true);
        if ($output !== '') {
            yield $output;
        }
    }

    /** @param array<string, mixed> $headers */
    public static function redactHeaders(array $headers): array {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), SensitiveDataNames::headers(), true)) {
                $headers[$name] = self::MASK;
            }
        }

        return $headers;
    }

    public static function redactUrl(string $url): string {
        $names = implode('|', array_map('preg_quote', SensitiveDataNames::queryParameters()));
        return preg_replace_callback(
            '/([?&](?:' . $names . ')=)([^&#]*)/i',
            static fn(array $m): string => $m[1] . rawurlencode(self::MASK),
            $url,
        ) ?? $url;
    }

    public static function redactBody(string $body): string {
        $body = preg_replace_callback(
            self::bodySensitivePattern(),
            static fn(array $match): string => $match[1] . self::maskedBodyValue($match[2]),
            $body,
        ) ?? $body;

        return preg_replace('/(Bearer\s+)[^\s,]+/i', '$1' . self::MASK, $body) ?? $body;
    }

    /** @param array<array-key, mixed> $chunks */
    public static function redactChunks(array $chunks): array {
        $normalized = [];
        foreach ($chunks as $chunk) {
            if (!is_string($chunk)) {
                throw new \InvalidArgumentException('Response chunks must contain only strings.');
            }
            $normalized[] = $chunk;
        }

        return iterator_to_array((new self())->redactStream($normalized), false);
    }

    /**
     * @param-out string $pending
     * @param-out string $mode
     * @param-out string|null $quote
     */
    private static function redactBuffer(
        string &$pending,
        string &$mode,
        ?string &$quote,
        bool &$escaped,
        bool $final,
    ): string {
        $output = '';

        while ($pending !== '') {
            if ($mode === 'quoted') {
                $closingQuote = self::findClosingQuote($pending, $quote, $escaped);
                if ($closingQuote === null) {
                    $pending = '';
                    if ($final) {
                        $mode = 'normal';
                        $quote = null;
                        $escaped = false;
                    }
                    break;
                }

                $output .= $quote;
                $pending = substr($pending, $closingQuote + 1);
                $mode = 'normal';
                $quote = null;
                $escaped = false;
                continue;
            }

            if ($mode === 'unquoted') {
                $valueWithoutWhitespace = ltrim($pending);
                if ($valueWithoutWhitespace !== ''
                    && strlen($valueWithoutWhitespace) <= strlen('Bearer')
                    && strncasecmp('Bearer', $valueWithoutWhitespace, strlen($valueWithoutWhitespace)) === 0) {
                    break;
                }

                if (preg_match('/^\s*Bearer\s+/i', $pending, $bearerPrefix) === 1) {
                    $pending = substr($pending, strlen($bearerPrefix[0]));
                    continue;
                }

                $delimiter = self::findUnquotedDelimiter($pending);
                if ($delimiter === null) {
                    $pending = '';
                    if ($final) {
                        $mode = 'normal';
                    }
                    break;
                }

                $output .= $pending[$delimiter];
                $pending = substr($pending, $delimiter + 1);
                $mode = 'normal';
                continue;
            }

            $marker = self::nextMarker($pending);
            if ($marker === null) {
                if ($final) {
                    $output .= $pending;
                    $pending = '';
                    break;
                }

                $safeLength = strlen($pending) - self::potentialMarkerSuffixLength($pending);
                if ($safeLength <= 0) {
                    break;
                }

                $output .= substr($pending, 0, $safeLength);
                $pending = substr($pending, $safeLength);
                break;
            }

            if ($marker['offset'] > 0) {
                $output .= substr($pending, 0, $marker['offset']);
                $pending = substr($pending, $marker['offset']);
                continue;
            }

            $markerLength = strlen($marker['text']);
            if (strlen($pending) <= $markerLength) {
                if ($final) {
                    $output .= $pending;
                    $pending = '';
                }
                break;
            }

            $output .= $marker['text'];
            $pending = substr($pending, $markerLength);
            if ($marker['bearer']) {
                $output .= self::MASK;
                $mode = 'unquoted';
                continue;
            }

            $valueStart = $pending[0];
            if ($valueStart === '"' || $valueStart === "'") {
                $output .= $valueStart . self::MASK;
                $pending = substr($pending, 1);
                $mode = 'quoted';
                $quote = $valueStart;
                $escaped = false;
                continue;
            }

            if (preg_match('/^\s*Bearer\s+/i', $pending, $bearerPrefix) === 1) {
                $output .= self::MASK;
                $pending = substr($pending, strlen($bearerPrefix[0]));
                $mode = 'unquoted';
                continue;
            }

            $output .= self::MASK;
            $mode = 'unquoted';
        }

        return $output;
    }

    /** @return array{offset: int, text: string, bearer: bool}|null */
    private static function nextMarker(string $value): ?array {
        $matches = [];
        preg_match(self::bodySensitiveMarkerPattern(), $value, $bodyMatch, PREG_OFFSET_CAPTURE);
        if ($bodyMatch !== []) {
            $matches[] = [
                'offset' => $bodyMatch[0][1],
                'text' => $bodyMatch[0][0],
                'bearer' => false,
            ];
        }

        preg_match(self::BEARER_PATTERN, $value, $bearerMatch, PREG_OFFSET_CAPTURE);
        if ($bearerMatch !== []) {
            $matches[] = [
                'offset' => $bearerMatch[0][1],
                'text' => $bearerMatch[0][0],
                'bearer' => true,
            ];
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn(array $left, array $right): int => $left['offset'] <=> $right['offset']);
        return $matches[0];
    }

    private static function potentialMarkerSuffixLength(string $value): int {
        $prefixes = SensitiveDataNames::bodyFields();
        $maximum = min(self::STREAM_TAIL_BYTES, strlen($value));
        for ($length = $maximum; $length > 0; $length--) {
            $suffix = substr($value, -$length);
            foreach ($prefixes as $prefix) {
                foreach (['', '"', "'"] as $leadingQuote) {
                    foreach (['', '"', "'"] as $trailingQuote) {
                        $markerPrefix = $leadingQuote . $prefix . $trailingQuote;
                        if (strlen($suffix) <= strlen($markerPrefix)
                            && strncasecmp($markerPrefix, $suffix, strlen($suffix)) === 0) {
                            return $length;
                        }

                        if (preg_match(
                            '/^' . preg_quote($markerPrefix, '/') . '\\s*$/i',
                            $suffix,
                        ) === 1) {
                            return $length;
                        }

                        if (preg_match(
                            '/^' . preg_quote($markerPrefix, '/') . '\\s*[=:]\\s*$/i',
                            $suffix,
                        ) === 1) {
                            return $length;
                        }
                    }
                }
            }

            if (strlen($suffix) <= 7 && strncasecmp('Bearer ', $suffix, strlen($suffix)) === 0) {
                return $length;
            }

            if (preg_match('/^Bearer\\s*$/i', $suffix) === 1) {
                return $length;
            }
        }

        return 0;
    }

    private static function findUnquotedDelimiter(string $value): ?int {
        if (preg_match('/[,}\]\s]/', $value, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $match[0][1];
    }

    private static function findClosingQuote(string $value, ?string $quote, bool &$escaped): ?int {
        if ($quote === null) {
            return null;
        }

        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($character === '\\') {
                $escaped = true;
                continue;
            }
            if ($character === $quote) {
                return $index;
            }
        }

        return null;
    }

    private static function maskedBodyValue(string $value): string {
        return match (true) {
            str_starts_with($value, '"') => '"' . self::MASK . '"',
            str_starts_with($value, "'") => "'" . self::MASK . "'",
            default => self::MASK,
        };
    }

    private static function bodySensitivePattern(): string
    {
        $marker = self::bodySensitiveMarkerPattern();
        $marker = substr($marker, 1, -2);

        return '/' . $marker . '("[^"]*"|\'[^\']*\'|[^,}&\s]+)/i';
    }

    private static function bodySensitiveMarkerPattern(): string
    {
        $names = implode('|', array_map('preg_quote', SensitiveDataNames::bodyFields()));

        return '/((?:["\']?)(?:' . $names . ')(?:["\']?)\s*[=:]\s*)/i';
    }
}
