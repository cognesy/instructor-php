<?php declare(strict_types=1);

namespace Cognesy\Addons\Image;

use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Messages\Utils\Image as ImageUtil;

/**
 * The Image class.
 *
 * Represent an image in LLM calls. Provides convenience methods to extract data from the image.
 */
class Image extends ImageUtil
{
    private string $prompt;

    public function __construct(
        string $imageUrl,
        string $mimeType,
        string $prompt = 'Extract data from the image',
    ) {
        parent::__construct($imageUrl, $mimeType);
        $this->prompt = $prompt;
    }

    /**
     * Create an Image instance from a file path.
     *
     * @param string $imagePath The path to the image file.
     * @return static
     */
    #[\Override]
    public static function fromFile(string $imagePath): static {
        return parent::fromFile($imagePath);
    }

    /**
     * Get OpenAI API compatible array representation of the image.
     *
     * @return array
     */
    #[\Override]
    public function toArray(): array {
        $content = [];
        if (!empty($this->prompt)) {
            $content[] = ['type' => 'text', 'text' => $this->prompt];
        }
        $content[] = ['type' => 'image_url', 'image_url' => ['url' => $this->url ?: $this->base64bytes]];

        return [
            'role' => 'user',
            'content' => $content,
        ];
    }

    /**
     * Get structured output from the image via Instructor.
     *
     * @param string|array|object $responseModel The response model.
     * @param string $prompt The prompt to extract data from the image.
     * @param string $model The model to use.
     * @param string $system The system string.
     * @param array $examples Examples for the request.
     * @param array $options Additional options for the request.
     * @param CanCreateStructuredOutput $structuredOutput Preconfigured creator.
     * @return mixed
     */
    public function toData(
        string|array|object $responseModel,
        string              $prompt,
        CanCreateStructuredOutput $structuredOutput,
        string              $model = '',
        string              $system = '',
        array               $examples = [],
        array               $options = [],
    ) : mixed {
        $request = new StructuredOutputRequest(
            messages: $this->toMessages(),
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );

        return $structuredOutput->create($request)->get();
    }
}
