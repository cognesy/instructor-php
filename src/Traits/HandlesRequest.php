<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Features\Core\Data\StructuredOutputRequest;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\LLM;
use Cognesy\Instructor\Instructor;
use JetBrains\PhpStorm\Deprecated;

trait HandlesRequest
{
    private LLM $llm;
    private StructuredOutputRequest $request;
    private array $cachedContext = [];

    // PUBLIC /////////////////////////////////////////////////////////////////////

    /**
     * Initializes an Instructor instance with a specified connection.
     *
     * @param string $connection The connection string to be used.
     * @return Instructor An instance of Instructor with the specified connection.
     */
    public static function using(string $connection) : Instructor {
        return (new Instructor)->withConnection($connection);
    }

    // PUBLIC /////////////////////////////////////////////////////////////////////

    public function withLLM(LLM $llm) : static {
        $this->llm = $llm;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config) : self {
        $this->llm->withConfig($config);
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : self {
        $this->llm->withDriver($driver);
        return $this;
    }

    public function withHttpClient(CanHandleHttp $httpClient) : self {
        $this->llm->withHttpClient($httpClient);
        return $this;
    }

    public function withConnection(string $connection) : self {
        $this->llm->withConnection($connection);
        return $this;
    }

    public function getRequest() : StructuredOutputRequest {
        return $this->request;
    }

    public function withCachedContext(
        string|array $messages = '',
        string|array|object $input = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ) : ?self {
        $this->cachedContext = [
            'messages' => $messages,
            'input' => $input,
            'system' => $system,
            'prompt' => $prompt,
            'examples' => $examples,
        ];
        return $this;
    }

    #[Deprecated]
    public function withClient(string $client) : self {
        $this->llm->withConnection($client);
        return $this;
    }
}
