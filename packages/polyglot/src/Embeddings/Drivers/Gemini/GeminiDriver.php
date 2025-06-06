<?php

namespace Cognesy\Polyglot\Embeddings\Drivers\Gemini;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\BaseEmbedDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeminiDriver extends BaseEmbedDriver
{
    protected EmbedRequestAdapter  $requestAdapter;
    protected EmbedResponseAdapter $responseAdapter;
    protected GeminiUsageFormat    $usageFormat;

    public function __construct(
        protected EmbeddingsConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->usageFormat = new GeminiUsageFormat();
        $this->requestAdapter = new GeminiRequestAdapter(
            $config,
            new GeminiBodyFormat($config)
        );
        $this->responseAdapter = new GeminiResponseAdapter(
            $this->usageFormat
        );
    }
}