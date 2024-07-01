<?php

namespace Cognesy\Instructor\Extras\Image;

use Cognesy\Instructor\Contracts\CanProvideMessages;
use Cognesy\Instructor\Data\Messages\Messages;
use Exception;

class Image implements CanProvideMessages
{
    private string $base64bytes = '';
    private string $url = '';
    private string $mimeType;
    private string $prompt;

    public function __construct(
        string $imageUrl,
        string $mimeType,
        string $prompt = 'Extract data from the image',
    ) {
        $this->mimeType = $mimeType;
        if (substr($imageUrl, 0, 4) === 'http') {
            $this->url = $imageUrl;
        } else {
            $this->base64bytes = $imageUrl;
        }
        $this->prompt = $prompt;
    }

    public static function fromFile(string $imagePath): self {
        $mimeType = mime_content_type($imagePath);
        $imageBase64 = 'data:' . $mimeType. ';base64,' . base64_encode(file_get_contents($imagePath));
        return new self($imageBase64, $mimeType);
    }

    public static function fromBase64(string $base64string, string $mimeType): self {
        $prefix = 'data:{$mimeType};base64,';
        if (substr($base64string, 0, 5) !== 'data:') {
            throw new Exception("Base64 encoded string has to start with: {$prefix}");
        }
        return new self($base64string, $mimeType);
    }

    public static function fromUrl(string $imageUrl, string $mimeType): self {
        return new self($imageUrl, $mimeType);
    }

    public function toMessages(): Messages {
        $messages = [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $this->prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $this->url ?: $this->base64bytes]],
            ],
        ]];
        if (empty($this->prompt)) {
            unset($messages[0]['content'][0]);
        }
        return Messages::fromArray($messages);
    }

    public function toImageUrl(): string {
        return $this->url ?: $this->base64bytes;
    }

    public function getBase64Bytes(): string {
        return $this->base64bytes;
    }

    public function getMimeType(): string {
        return $this->mimeType;
    }
}
