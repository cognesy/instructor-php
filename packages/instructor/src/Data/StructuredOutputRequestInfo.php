<?php
namespace Cognesy\Instructor\Data;

class StructuredOutputRequestInfo
{
    use Traits\StructuredOutputRequestInfo\HandlesAccess;
    use Traits\StructuredOutputRequestInfo\HandlesMutation;
    use Traits\StructuredOutputRequestInfo\HandlesSerialization;

    protected string|array $messages = [];
    protected string|array|object $responseModel = [];
    protected string $model = '';
    protected string $system = '';
    protected string $prompt = '';
    protected array $options = [];
    /** @var Example[] */
    protected array $examples = [];

    protected ?StructuredOutputConfig $config = null;
    protected ?CachedContext $cachedContext = null;

    public function __construct(
        string|array $messages = [],
        string|array|object $responseModel = [],
        string $model = '',
        string $system = '',
        string $prompt = '',
        array $options = [],
        array $examples = [],
        ?StructuredOutputConfig $config = null,
        ?CachedContext $cachedContext = null,
    ) {
        $this->messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            is_array($messages) => $messages,
        };
        $this->responseModel = $responseModel;
        $this->model = $model;
        $this->system = $system;
        $this->prompt = $prompt;
        $this->options = $options;
        $this->examples = $examples;
        $this->config = $config;
        $this->cachedContext = $cachedContext;
    }

    public static function fromArray(array $data) : static {
        return new StructuredOutputRequestInfo(
            messages: $data['messages'] ?? '',
            responseModel: $data['responseModel'] ?? '',
            model: $data['model'] ?? '',
            system: $data['system'] ?? '',
            prompt: $data['prompt'] ?? '',
            options: $data['options'] ?? [],
            examples: $data['examples']
                ?? array_map(fn($example) => Example::fromArray($example), $data['examples'] ?? []),
            config: $data['config'] ?? null,
            cachedContext: CachedContext::fromArray($data['cachedContext']),
        );
    }
}
