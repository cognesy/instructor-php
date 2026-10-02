<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers\Perplexity;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\BaseEmbedDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIUsageFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class PerplexityDriver extends BaseEmbedDriver
{
    protected EmbedRequestAdapter  $requestAdapter;
    protected EmbedResponseAdapter $responseAdapter;

    public function __construct(
        protected EmbeddingsConfig $config,
        protected CanSendHttpRequests $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $requestAdapter = new PerplexityRequestAdapter(
            $config,
            new PerplexityBodyFormat($config)
        );
        $responseAdapter = new PerplexityResponseAdapter(
            new OpenAIUsageFormat()
        );
        parent::__construct($config, $httpClient, $events, $requestAdapter, $responseAdapter);
    }
}
