<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\XAI;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class XAiDriver extends BaseInferenceRequestDriver
{
    public function __construct(
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
    ) {
        parent::__construct(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: new OpenAIRequestAdapter(
                $config,
                new OpenAICompatibleBodyFormat(
                    $config,
                    new XAiMessageFormat(),
                )
            ),
            responseTranslator: new OpenAIResponseAdapter(
                new OpenAIUsageFormat()
            ),
        );
    }
}