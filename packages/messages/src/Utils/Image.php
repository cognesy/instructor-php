<?php declare(strict_types=1);

namespace Cognesy\Messages\Utils;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Contracts\CanProvideMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Exception;

/**
 * The Image class.
 *
 * Represent an image in LLM calls.
 * Provides convenience methods to extract data from the image.
 */
class Image implements CanProvideMessages
{
    protected string $base64bytes = '';
    protected string $url = '';
    protected string $mimeType;

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

    /**
     * Create an Image object from a file.
     *
     * @param string $imagePath The path to the image file.
     * @return Image
     */
    public static function fromFile(string $imagePath): static {
        $mimeType = mime_content_type($imagePath);
        $imageBase64 = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($imagePath));
        return new static($imageBase64, $mimeType);
    }

    /**
     * Create an Image object from a base64 encoded string.
     *
     * @param string $base64string The base64 encoded string.
     * @param string $mimeType The MIME type of the image.
     * @return Image
     */
    public static function fromBase64(string $base64string, string $mimeType): static {
        $prefix = 'data:{$mimeType};base64,';
        if (substr($base64string, 0, 5) !== 'data:') {
            throw new Exception("Base64 encoded string has to start with: {$prefix}");
        }
        return new static($base64string, $mimeType);
    }

    /**
     * Create an Image object from an image URL.
     *
     * @param string $imageUrl The URL of the image.
     * @param string $mimeType The MIME type of the image.
     * @return Image
     */
    public static function fromUrl(string $imageUrl, string $mimeType): static {
        return new static($imageUrl, $mimeType);
    }

    /**
     * Get the image as Messages object.
     *
     * @return Messages
     */
    public function toMessages(): Messages {
        return Messages::fromMessages([$this->toMessage()]);
    }

    /**
     * Get the image as a Message object.
     *
     * @return Message
     */
    public function toMessage(): Message {
        return Message::fromArray($this->toArray());
    }

    /**
     * Get OpenAI API compatible array representation of the image.
     *
     * @return array
     */
    public function toArray(): array {
        $array = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => $this->url ?: $this->base64bytes]
                ],
            ],
        ];
        return $array;
    }

    /**
     * Get the image URL or base64 string.
     *
     * @return string
     */
    public function toImageUrl(): string {
        return $this->url ?: $this->base64bytes;
    }

    /**
     * Get the base64 encoded bytes of the image.
     *
     * @return string
     */
    public function getBase64Bytes(): string {
        return $this->base64bytes;
    }

    /**
     * Get the MIME type of the image.
     *
     * @return string
     */
    public function getMimeType(): string {
        return $this->mimeType;
    }

    public function toContentPart() : ContentPart {
        return new ContentPart('image_url', [
            'url' => $this->toImageUrl(),
        ]);
    }

    public function toContent() : Content {
        return (new Content)->addContentPart($this->toContentPart());
    }
}
