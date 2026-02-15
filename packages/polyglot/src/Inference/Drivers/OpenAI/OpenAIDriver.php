<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

class OpenAIDriver extends BaseInferenceRequestDriver
{
    public function __construct(
        LLMConfig $config,
        HttpClient $httpClient,
        EventDispatcherInterface $events,
    ) {
        parent::__construct(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: new OpenAIRequestAdapter(
                $config,
                new OpenAIBodyFormat(
                    $config,
                    new OpenAIMessageFormat(),
                )
            ),
            responseTranslator: new OpenAIResponseAdapter(
                new OpenAIUsageFormat()
            ),
        );
    }
}