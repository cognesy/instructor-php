<?php declare(strict_types=1);

namespace Cognesy\Http\Data;

use Cognesy\Utils\Metadata;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Class HttpRequest
 *
 * Represents an HTTP request
 */
class HttpRequest
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;
    public Metadata $metadata;

    private string $url;
    private string $method;
    private array $headers;
    private array $options;
    private HttpRequestBody $body;

    public function __construct(
        string $url,
        string $method,
        array $headers,
        string|array $body,
        array $options,
        //
        ?string $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?Metadata $metadata = null,
    ) {
        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;
        $this->options = $options;
        $this->body = new HttpRequestBody($body);
        //
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->metadata = $metadata ?? Metadata::empty();
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////////

    /**
     * Get the request URL
     *
     * @return string
     */
    public function url() : string {
        return $this->url;
    }

    /**
     * Get the request method
     */
    public function method() : string {
        return $this->method;
    }

    /**
     * Get the request headers
     */
    public function headers(?string $key = null) : mixed {
        return match(true) {
            ($key !== null) => $this->headers[$key] ?? [],
            default => $this->headers
        };
    }

    /**
     * Get the request body
     */
    public function body() : HttpRequestBody {
        return $this->body;
    }

    public function options() : array {
        return $this->options;
    }

    /**
     * Check if the request is streamed
     */
    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    // MUTATORS /////////////////////////////////////////////////////////////////////

    public function withHeader(string $key, string $value) : self {
        $copy = clone $this;
        $copy->headers[$key] = $value;
        $copy->updatedAt = new DateTimeImmutable();
        return $copy;
    }

    /**
     * Set the request URL
     */
    public function withStreaming(bool $streaming) : self {
        $copy = clone $this;
        $copy->options['stream'] = $streaming;
        $copy->updatedAt = new DateTimeImmutable();
        return $copy;
    }

    // SERIALIZATION ////////////////////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'body' => $this->body->toArray(),
            'options' => $this->options,
            //
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): HttpRequest {
        return new self(
            url: $data['url'],
            method: $data['method'],
            headers: $data['headers'],
            body: $data['body'],
            options: $data['options'],
            id: $data['id'],
            createdAt: DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $data['createdAt']) ?: null,
            updatedAt: DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $data['updatedAt']) ?: null,
            metadata: Metadata::fromArray($data['metadata']),
        );
    }
}
