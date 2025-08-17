<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\OpenAI;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\BaseEmbedDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class OpenAIDriver extends BaseEmbedDriver
{
    protected EmbedRequestAdapter  $requestAdapter;
    protected EmbedResponseAdapter $responseAdapter;

    public function __construct(
        protected EmbeddingsConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $this->requestAdapter = new OpenAIRequestAdapter(
            $config,
            new OpenAIBodyFormat($config)
        );
        $this->responseAdapter = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
    }
}