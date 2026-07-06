<?php declare(strict_types=1);

namespace Cognesy\Http\Data;

/**
 * Class HttpRequestBody
 *
 * Represents the body of an HTTP request
 */
class HttpRequestBody
{
    public string $body;
    private ?string $decodedBodySource = null;

    /** @var array<string,mixed>|array<int,mixed> */
    private array $decodedBody = [];

    public function __construct(
        string|array $body,
    ) {
        if (is_array($body)) {
            $this->body = $this->encodeJsonBody($body);
            $this->decodedBodySource = $this->body;
            $this->decodedBody = $body;
            return;
        }

        $this->body = $body;
    }

    /**
     * Get the request body as a string
     *
     * @return string
     */
    public function toString() : string {
        return $this->body;
    }

    /**
     * Get the request body as an array
     *
     * @return array
     */
    public function toArray() : array {
        if ($this->decodedBodySource === $this->body) {
            return $this->decodedBody;
        }

        $this->decodedBody = $this->decodeJsonBody($this->body);
        $this->decodedBodySource = $this->body;

        return $this->decodedBody;
    }

    private function decodeJsonBody(string $body) : array {
        if ($body === '') {
            return [];
        }

        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /** @param array<string,mixed>|array<int,mixed> $body */
    private function encodeJsonBody(array $body) : string {
        try {
            return json_encode($body, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                'Failed to encode request body as JSON: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }
}
