<?php declare(strict_types=1);

namespace Cognesy\Messages\Utils;

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Contracts\CanProvideMessages;
use Cognesy\Messages\Enums\ContentType;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
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
        // Empty string means "no inline data" (e.g. a file referenced only by fileId);
        // anything else must be a data: URI - silently dropping a malformed value here
        // would hide the mistake, so fail the same way fromBase64() already does.
        if ($fileData !== '' && !str_starts_with($fileData, 'data:')) {
            throw new Exception("File data has to start with: data:{$mimeType};base64,");
        }
        $this->base64bytes = $fileData;
        $this->fileName = $fileName;
        $this->fileId = $fileId;
    }

    /**
     * Create a File object from a file.
     *
     * @param string $imagePath The path to the file.
     * @return static
     */
    public static function fromFile(string $imagePath): static {
        $mimeType = mime_content_type($imagePath);
        if ($mimeType === false) {
            // mime_content_type() returns string|false; feeding false into the
            // string $mimeType constructor param would TypeError under strict_types
            // instead of naming the file that caused the problem.
            throw new \RuntimeException("Failed to detect MIME type for file: {$imagePath}");
        }
        $contents = file_get_contents($imagePath);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read file: {$imagePath}");
        }
        $fileData = 'data:' . $mimeType . ';base64,' . base64_encode($contents);
        // OpenAI expects a filename alongside file_data; derive it from the path
        // so callers don't have to pass it separately.
        return new static(fileData: $fileData, fileName: basename($imagePath), mimeType: $mimeType);
    }

    /**
     * Create a File object from a base64 encoded string.
     *
     * @param string $base64string The base64 encoded string.
     * @param string $mimeType The MIME type of the file.
     * @return static
     */
    public static function fromBase64(string $base64string, string $mimeType): static {
        $prefix = "data:{$mimeType};base64,";
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
    #[\Override]
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
        // Providers reject/ignore empty keys outright (e.g. OpenAI still receives
        // "file_id": "" verbatim), so only emit whichever of the three is actually set.
        $file = array_filter([
            'file_data' => $this->base64bytes,
            'file_name' => $this->fileName,
            'file_id' => $this->fileId,
        ], fn(string $value) => $value !== '');
        return new ContentPart(ContentType::File->value, [
            'file' => $file,
        ]);
    }
}
