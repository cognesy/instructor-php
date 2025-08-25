<?php declare(strict_types=1);

namespace Cognesy\Messages\Utils;

use Cognesy\Messages\ContentPart;

class Audio {
    public function __construct(
        protected string $format,
        protected string $base64bytes
    ) {}

    public function format(): string {
        return $this->format;
    }

    public function getByte64Bytes(): string {
        return $this->base64bytes;
    }

    public function toContentPart(): ContentPart {
        return new ContentPart('input_audio', ['input_audio' => [
            'format' => $this->format,
            'data' => $this->base64bytes,
        ]]);
    }
}