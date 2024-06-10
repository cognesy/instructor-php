<?php

namespace Cognesy\Instructor\Extras\Image;

class Image
{
    private string $base64bytes = '';
    private string $url = '';
    private string $mimeType = '';

    public function __construct(
        string $imageUrl,
        string $mimeType,
    ) {
        $this->mimeType = $mimeType;
        if (substr($imageUrl, 0, 4) === 'http') {
            $this->url = $imageUrl;
        } else {
            $this->base64bytes = $imageUrl;
        }
    }

    public static function fromFile(string $imagePath): self {
        $mimeType = mime_content_type($imagePath);
        $imageBase64 = 'data:' . $mimeType. ';base64,' . base64_encode(file_get_contents($imagePath));
        return new self($imageBase64, $mimeType);
    }

    public static function fromUrl(string $imageUrl, string $mimeType): self {
        return new self($imageUrl, $mimeType);
    }

    public function toMessages(string $prompt = 'Extract data from the image'): array {
        return [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => $this->url ?: $this->base64bytes],
            ],
        ]];
    }

    public function getImageUrl(): string {
        return $this->url ?: $this->base64bytes;
    }

    public function getBase64Bytes(): string {
        return $this->base64bytes;
    }

    public function getMimeType(): string {
        return $this->mimeType;
    }
}
