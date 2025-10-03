<?php declare(strict_types=1);
namespace Cognesy\Polyglot\Embeddings\Drivers\Azure;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\BaseEmbedDriver;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIUsageFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class AzureOpenAIDriver extends BaseEmbedDriver
{
    protected EmbedRequestAdapter  $requestAdapter;
    protected EmbedResponseAdapter $responseAdapter;

    public function __construct(
        protected EmbeddingsConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {
        $requestAdapter = new AzureRequestAdapter(
            $config,
            new OpenAIBodyFormat($config)
        );
        $responseAdapter = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
        parent::__construct($config, $httpClient, $events, $requestAdapter, $responseAdapter);
    }
}
