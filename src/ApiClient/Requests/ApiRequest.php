<?php

namespace Cognesy\Instructor\ApiClient\Requests;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\ApiClient\Traits\HandlesApiRequestContext;
use Cognesy\Instructor\Traits\HandlesApiCaching;
use Cognesy\Instructor\Utils\Json;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

abstract class ApiRequest extends Request implements HasBody, Cacheable
{
    use HasJsonBody;
    use HandlesApiCaching;
    use HandlesApiRequestContext;

    protected string $endpoint;
    protected Method $method = Method::POST;
    protected bool $debug = false;

    public function __construct(
        public array $options = []
    ) {
        $this->debug = $this->options['debug'] ?? false;
        unset($this->options['debug']);

        $this->cachingEnabled = $this->options['cache'] ?? false;
        unset($this->options['cache']);

        if ($this->cachingEnabled) {
            if ($this->isStreamed()) {
                throw new \Exception('Cannot use cache with streamed requests');
            }
        }
        $this->body()->setJsonFlags(JSON_UNESCAPED_SLASHES);
    }

    public function isStreamed(): bool {
        return $this->options['stream'] ?? false;
    }

    public function isDebug(): bool {
        return $this->debug;
    }

    public function resolveEndpoint() : string {
        return $this->endpoint;
    }

    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }

    protected function appendInstructions(array $messages, string $prompt, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $prompt . Json::encode($jsonSchema);
        return $messages;
    }

    abstract protected function defaultBody(): array;
    abstract public function toApiResponse(Response $response) : ApiResponse;
    abstract public function toPartialApiResponse(string $partialData) : PartialApiResponse;
}