<?php

namespace Cognesy\Addons\Image;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Messages\Utils\Image as ImageUtil;

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
     * Get structured output from the image via Instructor.
     *
     * @param string|array|object $responseModel The response model.
     * @param string $prompt The prompt to extract data from the image.
     * @param string $preset The connection string.
     * @param string $model The model to use.
     * @param string $system The system string.
     * @param array $examples Examples for the request.
     * @param int $maxRetries The maximum number of retries.
     * @param array $options Additional options for the request.
     * @param OutputMode $mode The mode to use.
     * @return mixed
     */
    public function toData(
        string|array|object $responseModel,
        string              $prompt,
        string              $connection = '',
        string              $model = '',
        string              $system = '',
        array               $examples = [],
        int                 $maxRetries = 0,
        array               $options = [],
        OutputMode          $mode = OutputMode::Tools,
    ) : mixed {
        return (new StructuredOutput)->using($connection)->with(
            messages: $this->toMessages(),
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
}
