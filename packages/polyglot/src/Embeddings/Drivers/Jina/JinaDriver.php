<?php

namespace Cognesy\Polyglot\Embeddings\Drivers\Jina;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\BaseEmbedDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIUsageFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class JinaDriver extends BaseEmbedDriver
{
    protected EmbedRequestAdapter  $requestAdapter;
    protected EmbedResponseAdapter $responseAdapter;

    public function __construct(
        protected EmbeddingsConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->requestAdapter = new JinaRequestAdapter(
            $config,
            new JinaBodyFormat($config)
        );
        $this->responseAdapter = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
    }
}
