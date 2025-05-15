<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\LLM;
use JetBrains\PhpStorm\Deprecated;

trait HandlesRequest
{
    /**
     * Initializes an Instructor instance with a specified connection.
     *
     * @param string $connection The connection string to be used.
     * @return StructuredOutput An instance of StructuredOutput with the specified connection.
     */
    public static function using(string $connection) : static {
        return (new StructuredOutput)->withConnection($connection);
    }

    public static function fromDSN(string $dsn) : static {
        return (new StructuredOutput)->withDSN($dsn);
    }

    // PUBLIC /////////////////////////////////////////////////////////////////////

    public function withDSN(string $dsn) : static {
        $llm = LLM::fromDSN($dsn);
        $this->llm = $llm;
        return $this;
    }

    public function withLLM(LLM $llm) : static {
        $this->llm = $llm;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config) : static {
        $this->llm->withConfig($config);
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : static {
        $this->llm->withDriver($driver);
        return $this;
    }

    public function withHttpClient(CanHandleHttpRequest $httpClient) : static {
        $this->llm->withHttpClient($httpClient);
        return $this;
    }

    public function withConnection(string $connection) : static {
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
    public function withClient(string $client) : static {
        $this->llm->withConnection($client);
        return $this;
    }
}
