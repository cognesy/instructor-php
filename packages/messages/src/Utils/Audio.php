<?php declare(strict_types=1);

namespace Cognesy\Messages\Utils;

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\ContentType;

class Audio {
    public function __construct(
        protected string $format,
        protected string $base64bytes
    ) {}

    public function format(): string {
        return $this->format;
    }

    public function getBase64Bytes(): string {
        return $this->base64bytes;
    }

    /** @deprecated Use getBase64Bytes(). */
    public function getByte64Bytes(): string {
        return $this->getBase64Bytes();
    }

    public function toContentPart(): ContentPart {
        return new ContentPart(ContentType::Audio->value, ['input_audio' => [
            'format' => $this->format,
            'data' => $this->base64bytes,
        ]]);
    }
}
