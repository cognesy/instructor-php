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

    public function toContentPart(): ContentPart {
        // Same nested-empty-field problem as File::toContentPart() (instructor-r50t.9):
        // an empty format/data must be omitted rather than sent as "".
        $inputAudio = array_filter([
            'format' => $this->format,
            'data' => $this->base64bytes,
        ], fn(string $value) => $value !== '');
        return new ContentPart(ContentType::Audio->value, [
            'input_audio' => $inputAudio,
        ]);
    }
}
