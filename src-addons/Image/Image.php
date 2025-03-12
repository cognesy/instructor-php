<?php

namespace Cognesy\Addons\Image;

use Cognesy\Instructor\Instructor;
use Cognesy\Polyglot\LLM\Enums\Mode;
use Cognesy\Utils\Messages\Contracts\CanProvideMessages;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Exception;

/**
 * The Image class.
 *
 * Represent an image in LLM calls. Provides convenience methods to extract data from the image.
 */
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

    /**
     * Create an Image object from a file.
     *
     * @param string $imagePath The path to the image file.
     * @return Image
     */
    public static function fromFile(string $imagePath): self {
        $mimeType = mime_content_type($imagePath);
        $imageBase64 = 'data:' . $mimeType. ';base64,' . base64_encode(file_get_contents($imagePath));
        return new self($imageBase64, $mimeType);
    }

    /**
     * Create an Image object from a base64 encoded string.
     *
     * @param string $base64string The base64 encoded string.
     * @param string $mimeType The MIME type of the image.
     * @return Image
     */
    public static function fromBase64(string $base64string, string $mimeType): self {
        $prefix = 'data:{$mimeType};base64,';
        if (substr($base64string, 0, 5) !== 'data:') {
            throw new Exception("Base64 encoded string has to start with: {$prefix}");
        }
        return new self($base64string, $mimeType);
    }

    /**
     * Create an Image object from an image URL.
     *
     * @param string $imageUrl The URL of the image.
     * @param string $mimeType The MIME type of the image.
     * @return Image
     */
    public static function fromUrl(string $imageUrl, string $mimeType): self {
        return new self($imageUrl, $mimeType);
    }

    /**
     * Get the image as Messages object.
     *
     * @return \Cognesy\Utils\Messages\Messages
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
                ['type' => 'text', 'text' => $this->prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $this->url ?: $this->base64bytes]],
            ],
        ];
        if (empty($this->prompt)) {
            unset($array['content'][0]);
        }
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
     * Get structured output from the image via Instructor.
     *
     * @param string|array|object $responseModel The response model.
     * @param string $prompt The prompt to extract data from the image.
     * @param string $connection The connection string.
     * @param string $model The model to use.
     * @param string $system The system string.
     * @param array $examples Examples for the request.
     * @param int $maxRetries The maximum number of retries.
     * @param array $options Additional options for the request.
     * @param \Cognesy\Polyglot\LLM\Enums\Mode $mode The mode to use.
     * @return mixed
     */
    public function toData(
        string|array|object $responseModel,
        string $prompt,
        string $connection = '',
        string $model = '',
        string $system = '',
        array $examples = [],
        int $maxRetries = 0,
        array $options = [],
        Mode $mode = Mode::Tools,
    ) : mixed {
        return (new Instructor)->withConnection($connection)->request(
            input: $this,
            responseModel: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            mode: $mode,
        )->get();
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
}
