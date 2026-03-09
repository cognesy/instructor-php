<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Minimaxi;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Psr\EventDispatcher\EventDispatcherInterface;

class MinimaxiDriver extends BaseInferenceRequestDriver
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
                new MinimaxiBodyFormat(
                    $config,
                    new OpenAIMessageFormat(),
                )
            ),
            responseTranslator: new MinimaxiResponseAdapter(
                new OpenAIUsageFormat()
            ),
        );
    }
}
