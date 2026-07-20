<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

class StructuredOutputConfigBuilder
{
    private ?OutputMode $outputMode;
    private ?bool $useObjectReferences;
    private ?int $maxRetries;
    private ?string $retryPrompt;
    private ?array $modePrompts;
    private ?array $modePromptClasses;
    private ?string $retryPromptClass = null;
    private ?string $schemaName;
    private ?string $schemaDescription;
    private ?string $toolName;
    private ?string $toolDescription;
    private ?array $chatStructure;
    private ?bool $defaultToStdClass = null;
    private ?string $deserializationErrorPromptClass = null;
    private ?bool $throwOnTransformationFailure = null;
    private ?ResponseCachePolicy $responseCachePolicy = null;
    private ?int $streamMaterializationInterval = null;

    private ?StructuredOutputConfig $explicitConfig = null;

    public function __construct(
        ?OutputMode       $outputMode = null,
        ?bool             $useObjectReferences = null,
        ?int              $maxRetries = null,
        ?string           $retryPrompt = null,
        ?array            $modePrompts = null,
        ?array            $modePromptClasses = null,
        ?string           $retryPromptClass = null,
        ?string           $schemaName = null,
        ?string           $schemaDescription = null,
        ?string           $toolName = null,
        ?string           $toolDescription = null,
        ?array            $chatStructure = null,
        ?string           $deserializationErrorPromptClass = null,
        ?int              $streamMaterializationInterval = null,
    ) {
        $this->outputMode = $outputMode;
        $this->useObjectReferences = $useObjectReferences;
        $this->maxRetries = $maxRetries;
        $this->retryPrompt = $retryPrompt;
        $this->modePrompts = $modePrompts ?? [];
        $this->modePromptClasses = $modePromptClasses ?? [];
        $this->retryPromptClass = $retryPromptClass;
        $this->schemaName = $schemaName;
        $this->schemaDescription = $schemaDescription;
        $this->toolName = $toolName;
        $this->toolDescription = $toolDescription;
        $this->chatStructure = $chatStructure;
        $this->deserializationErrorPromptClass = $deserializationErrorPromptClass;
        $this->streamMaterializationInterval = $streamMaterializationInterval;
    }

    public function withOutputMode(?OutputMode $outputMode) : static {
        $this->outputMode = $outputMode;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withSchemaName(string $schemaName) : static {
        $this->schemaName = $schemaName;
        return $this;
    }

    public function withSchemaDescription(string $schemaDescription) : static {
        $this->schemaDescription = $schemaDescription;
        return $this;
    }

    public function withToolName(string $toolName) : static {
        $this->toolName = $toolName;
        return $this;
    }

    public function withToolDescription(string $toolDescription) : static {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function withUseObjectReferences(bool $useObjectReferences) : static {
        $this->useObjectReferences = $useObjectReferences;
        return $this;
    }

    /** @deprecated 2.5 Legacy RequestMaterializer setting; remove with instructor-cxxt in 2.6. */
    public function withRetryPrompt(string $retryPrompt) : static {
        $this->retryPrompt = $retryPrompt;
        return $this;
    }

    /** @deprecated 2.5 Legacy RequestMaterializer setting; remove with instructor-cxxt in 2.6. */
    public function withModePrompt(OutputMode $mode, string $prompt) : static {
        $this->modePrompts[$mode->value] = $prompt;
        return $this;
    }

    /** @deprecated 2.5 Legacy RequestMaterializer setting; remove with instructor-cxxt in 2.6. */
    public function withModePrompts(array $modePrompts) : static {
        $this->modePrompts = $modePrompts;
        return $this;
    }

    public function withModePromptClass(OutputMode $mode, string $promptClass) : static {
        $this->modePromptClasses[$mode->value] = $promptClass;
        return $this;
    }

    public function withModePromptClasses(array $modePromptClasses) : static {
        $this->modePromptClasses = $modePromptClasses;
        return $this;
    }

    public function withRetryPromptClass(string $retryPromptClass) : static {
        $this->retryPromptClass = $retryPromptClass;
        return $this;
    }

    /** @deprecated 2.5 Legacy RequestMaterializer setting; remove with instructor-cxxt in 2.6. */
    public function withChatStructure(array $chatStructure) : static {
        $this->chatStructure = $chatStructure;
        return $this;
    }

    /** @deprecated 2.5 Use per-request intoStdClass(); remove in 3.0. */
    public function withDefaultToStdClass(bool $defaultToStdClass) : self {
        $this->defaultToStdClass = $defaultToStdClass;
        return $this;
    }

    public function withDeserializationErrorPromptClass(string $deserializationErrorPromptClass) : self {
        $this->deserializationErrorPromptClass = $deserializationErrorPromptClass;
        return $this;
    }

    /**
     * @deprecated 2.5 Transformation failures always fail the attempt. This
     *             setter is retained for configuration compatibility only.
     */
    public function withThrowOnTransformationFailure(bool $throwOnTransformationFailure) : self {
        $this->throwOnTransformationFailure = $throwOnTransformationFailure;
        return $this;
    }

    public function withResponseCachePolicy(ResponseCachePolicy $responseCachePolicy): self {
        $this->responseCachePolicy = $responseCachePolicy;
        return $this;
    }

    public function withStreamMaterializationInterval(int $streamMaterializationInterval): self {
        $this->streamMaterializationInterval = $streamMaterializationInterval;
        return $this;
    }

    public function with(
        ?OutputMode $outputMode = null,
        ?bool $useObjectReferences = null,
        ?int $maxRetries = null,
        ?string $retryPrompt = null,
        ?array $modePrompts = null,
        ?array $modePromptClasses = null,
        ?string $retryPromptClass = null,
        ?string $schemaName = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?array $chatStructure = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
        ?string $deserializationErrorPromptClass = null,
        ?int $streamMaterializationInterval = null,
    ) : self {
        $this->outputMode = $outputMode ?? $this->outputMode;
        $this->useObjectReferences = $useObjectReferences ?? $this->useObjectReferences;
        $this->maxRetries = $maxRetries ?? $this->maxRetries;
        $this->retryPrompt = $retryPrompt ?? $this->retryPrompt;
        $this->modePrompts = $modePrompts ?? $this->modePrompts;
        $this->modePromptClasses = $modePromptClasses ?? $this->modePromptClasses;
        $this->retryPromptClass = $retryPromptClass ?? $this->retryPromptClass;
        $this->schemaName = $schemaName ?? $this->schemaName;
        $this->toolName = $toolName ?? $this->toolName;
        $this->toolDescription = $toolDescription ?? $this->toolDescription;
        $this->chatStructure = $chatStructure ?? $this->chatStructure;
        $this->responseCachePolicy = $responseCachePolicy ?? $this->responseCachePolicy;
        $this->deserializationErrorPromptClass = $deserializationErrorPromptClass ?? $this->deserializationErrorPromptClass;
        $this->streamMaterializationInterval = $streamMaterializationInterval ?? $this->streamMaterializationInterval;
        return $this;
    }

    public function withConfig(StructuredOutputConfig $config) : self {
        $this->explicitConfig = $config;
        return $this;
    }

    public function create() : StructuredOutputConfig {
        $defaults = $this->explicitConfig ?? new StructuredOutputConfig();

        $config = new StructuredOutputConfig(
            outputMode: $this->outputMode ?? $defaults->outputMode(),
            useObjectReferences: $this->useObjectReferences ?? $defaults->useObjectReferences(),
            maxRetries: $this->maxRetries ?? $defaults->maxRetries(),
            schemaName: $this->schemaName ?? $defaults->schemaName(),
            schemaDescription: $this->schemaDescription ?? $defaults->schemaDescription(),
            toolName: $this->toolName ?? $defaults->toolName(),
            toolDescription: $this->toolDescription ?? $defaults->toolDescription(),
            modePrompts: array_merge($defaults->modePrompts(), $this->modePrompts ?? []),
            modePromptClasses: array_merge($defaults->modePromptClasses(), $this->modePromptClasses ?? []),
            retryPrompt: $this->retryPrompt ?? $defaults->retryPrompt(),
            retryPromptClass: $this->retryPromptClass ?? $defaults->retryPromptClass(),
            chatStructure: $this->chatStructure ?? $defaults->chatStructure(),
            defaultToStdClass: $this->defaultToStdClass ?? $defaults->defaultToStdClass(),
            deserializationErrorPromptClass: $this->deserializationErrorPromptClass ?? $defaults->deserializationErrorPromptClass(),
            throwOnTransformationFailure: $this->throwOnTransformationFailure ?? $defaults->throwOnTransformationFailure(),
            responseCachePolicy: $this->responseCachePolicy ?? $defaults->responseCachePolicy(),
            streamMaterializationInterval: $this->streamMaterializationInterval ?? $defaults->streamMaterializationInterval(),
        );
        return $config;
    }
}
