<?php declare(strict_types=1);

namespace Cognesy\Utils\Messages\Utils;

use Cognesy\Utils\Messages\ContentPart;
use Cognesy\Utils\Messages\Contracts\CanProvideMessages;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Exception;

class File implements CanProvideMessages
{
    protected string $base64bytes = '';
    protected string $mimeType;
    protected string $fileId = '';
    protected string $fileName = '';

    public function __construct(
        string $fileData = '',
        string $fileName = '',
        string $fileId = '',
        string $mimeType = 'application/octet-stream',
    ) {
        $this->mimeType = $mimeType;
        if (strpos($fileData, 'data:') === 0) {
            $this->base64bytes = $fileData;
        }
        $this->fileName = $fileName;
        $this->fileId = $fileId;
    }

    /**
     * Create an Image object from a file.
     *
     * @param string $imagePath The path to the image file.
     * @return Image
     */
    public static function fromFile(string $imagePath): static {
        $mimeType = mime_content_type($imagePath);
        $fileData = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($imagePath));
        return new static(fileData: $fileData, mimeType: $mimeType);
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
        return new static(fileData: $base64string, mimeType: $mimeType);
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
                $this->toContentPart(),
            ],
        ];
        return $array;
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
        return new ContentPart('file', [
            'file_data' => $this->base64bytes,
            'file_name' => $this->fileName,
            'file_id' => $this->fileId,
        ]);
    }
}