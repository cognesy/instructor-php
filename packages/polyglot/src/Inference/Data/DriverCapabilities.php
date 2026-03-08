<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

/**
 * Describes the capabilities of an inference driver.
 *
 * This value object is used to query what features a driver supports,
 * enabling capability-based filtering of test cases and runtime decisions.
 */
readonly class DriverCapabilities
{
    /**
     * @param bool $streaming Whether streaming responses are supported
     * @param bool $toolCalling Whether tool/function calling is supported
     * @param bool $toolChoice Whether explicit tool choice is supported
     * @param bool $responseFormatJsonObject Whether native JSON object response format is supported
     * @param bool $responseFormatJsonSchema Whether native JSON schema response format is supported
     * @param bool $responseFormatWithTools Whether response_format works alongside tools
     * @param ?int $maxContextTokens Maximum supported input context, if known
     * @param ?int $maxOutputTokens Maximum supported output tokens, if known
     */
    public function __construct(
        public bool $streaming = true,
        public bool $toolCalling = true,
        public bool $toolChoice = true,
        public bool $responseFormatJsonObject = true,
        public bool $responseFormatJsonSchema = true,
        public bool $responseFormatWithTools = true,
        public ?int $maxContextTokens = null,
        public ?int $maxOutputTokens = null,
    ) {}

    /**
     * Check if streaming responses are supported.
     */
    public function supportsStreaming(): bool {
        return $this->streaming;
    }

    /**
     * Check if tool/function calling is supported.
     */
    public function supportsToolCalling(): bool {
        return $this->toolCalling;
    }

    /**
     * Check if explicit tool choice is supported.
     */
    public function supportsToolChoice(): bool {
        return $this->toolChoice;
    }

    /**
     * Check if native JSON object response format is supported.
     */
    public function supportsResponseFormatJsonObject(): bool {
        return $this->responseFormatJsonObject;
    }

    /**
     * Check if native JSON schema response format is supported.
     */
    public function supportsResponseFormatJsonSchema(): bool {
        return $this->responseFormatJsonSchema;
    }

    /**
     * Check if response_format can be used alongside tools.
     */
    public function supportsResponseFormatWithTools(): bool {
        return $this->responseFormatWithTools;
    }
}
