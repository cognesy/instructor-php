<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

class StructuredOutputRequest
{
    use Traits\StructuredOutputRequest\HandlesAccess;
    use Traits\StructuredOutputRequest\HandlesRetries;
    use Traits\StructuredOutputRequest\HandlesSchema;

    protected Messages $messages;
    protected string $model = '';
    protected string $prompt = '';
    protected string $system = '';
    /** @var Example[] */
    protected array $examples = [];
    protected array $options = [];
    protected CachedContext $cachedContext;
    protected string|array|object $requestedSchema = [];
    protected ?ResponseModel $responseModel = null;
    protected StructuredOutputConfig $config;

    public function __construct(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $requestedSchema = null,
        ?ResponseModel $responseModel = null,
        ?string        $system = null,
        ?string        $prompt = null,
        ?array         $examples = null,
        ?string        $model = null,
        ?array         $options = null,
        ?CachedContext $cachedContext = null,
        ?StructuredOutputConfig $config = null,
    ) {
        $this->messages = Messages::fromAny($messages);
        $this->requestedSchema = $requestedSchema;
        $this->responseModel = $responseModel;

        $this->options = $options ?: [];
        $this->prompt = $prompt ?: '';
        $this->examples = $examples ?: [];
        $this->system = $system ?: '';
        $this->model = $model ?: '';

        $this->config = $config;

        $this->cachedContext = $cachedContext ?: new CachedContext();
    }

    public function toArray() : array {
        return [
            'messages' => $this->messages->toArray(),
            'responseModel' => $this->responseModel,
            'system' => $this->system,
            'prompt' => $this->prompt,
            'examples' => $this->examples,
            'model' => $this->model,
            'options' => $this->options,
            'mode' => $this->mode(),
            'cachedContext' => $this->cachedContext?->toArray() ?? [],
            'config' => $this->config->toArray(),
        ];
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function withStreamed(bool $streamed = true) : static {
        $new = $this->clone();
        $new->options['stream'] = $streamed;
        return $new;
    }

    public function clone() : self {
        return new self(
            messages: $this->messages->clone(),
            requestedSchema: is_object($this->requestedSchema) ? clone $this->requestedSchema : $this->requestedSchema,
            responseModel: $this->responseModel->clone(),
            system: $this->system,
            prompt: $this->prompt,
            examples: $this->cloneExamples(),
            model: $this->model,
            options: $this->options,
            cachedContext: $this->cachedContext->clone(),
            config: $this->config->clone(),
        );
    }

    private function cloneExamples() {
        return is_array($this->examples)
            ? array_map(fn($e) => $e instanceof Example
                ? $e->clone()
                : $e, $this->examples)
            : $this->examples;
    }
}
