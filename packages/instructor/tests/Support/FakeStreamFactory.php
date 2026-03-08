<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Streaming\StreamingUsageState;

final class FakeStreamFactory
{
    /**
     * @return PartialInferenceResponse[]
     */
    public static function from(PartialInferenceResponse ...$responses): array {
        $result = [];
        $content = '';
        $reasoningContent = '';
        $finishReason = '';
        /** @var array<string,array{id:string,name:string,args:string}> $tools */
        $tools = [];
        $toolsCount = 0;
        $lastToolKey = '';
        $usage = new StreamingUsageState();

        foreach ($responses as $response) {
            if (self::isAccumulated($response)) {
                $result[] = $response;
                continue;
            }

            $content .= $response->contentDelta;
            $reasoningContent .= $response->reasoningContentDelta;
            $finishReason = match ($response->finishReason()) {
                '' => $finishReason,
                default => $response->finishReason(),
            };
            $usage->apply($response->usage(), $response->isUsageCumulative());

            [$tools, $toolsCount, $lastToolKey] = self::accumulateToolDelta(
                tools: $tools,
                toolsCount: $toolsCount,
                lastToolKey: $lastToolKey,
                response: $response,
            );

            $result[] = PartialInferenceResponse::fromAccumulatedState(
                contentDelta: $response->contentDelta,
                reasoningContentDelta: $response->reasoningContentDelta,
                toolId: $response->toolId(),
                toolName: $response->toolName(),
                toolArgs: $response->toolArgs(),
                finishReason: $finishReason,
                usage: null,
                usageIsCumulative: $usage->isCumulative(),
                usageInputTokens: $usage->inputTokens(),
                usageOutputTokens: $usage->outputTokens(),
                usageCacheWriteTokens: $usage->cacheWriteTokens(),
                usageCacheReadTokens: $usage->cacheReadTokens(),
                usageReasoningTokens: $usage->reasoningTokens(),
                usagePricing: $usage->pricing(),
                value: $response->value(),
                content: $content,
                reasoningContent: $reasoningContent,
                tools: $tools,
                toolsCount: $toolsCount,
                lastToolKey: $lastToolKey,
            );
        }

        return $result;
    }

    private static function isAccumulated(PartialInferenceResponse $response): bool {
        return $response->hasContent()
            || $response->hasReasoningContent()
            || $response->toolCalls()->hasAny();
    }

    /**
     * @param array<string,array{id:string,name:string,args:string}> $tools
     * @return array{0: array<string,array{id:string,name:string,args:string}>, 1: int, 2: string}
     */
    private static function accumulateToolDelta(
        array $tools,
        int $toolsCount,
        string $lastToolKey,
        PartialInferenceResponse $response,
    ): array {
        $toolId = $response->toolId()?->toString() ?? '';
        $toolName = $response->toolName();
        $toolArgs = $response->toolArgs();

        if ($toolId === '' && $toolName === '' && $toolArgs === '') {
            return [$tools, $toolsCount, $lastToolKey];
        }

        $key = match (true) {
            $toolId !== '' => 'id:' . $toolId,
            $toolName !== '' => 'name:' . $toolName . '#' . ($toolsCount + 1),
            $lastToolKey !== '' && isset($tools[$lastToolKey]) => $lastToolKey,
            default => '',
        };

        if ($toolId !== '' && ($key !== $lastToolKey || !isset($tools[$key]))) {
            $toolsCount += 1;
            $tools[$key] = ['id' => $toolId, 'name' => $toolName, 'args' => ''];
        }

        if ($toolId !== '' && $toolName !== '' && isset($tools[$key])) {
            $tools[$key]['name'] = $toolName;
        }

        if ($toolId === '' && $toolName !== '' && $key !== $lastToolKey) {
            $toolsCount += 1;
            $tools[$key] = ['id' => '', 'name' => $toolName, 'args' => ''];
        }

        $lastToolKey = $key;

        if ($toolArgs === '' || $key === '' || !isset($tools[$key])) {
            return [$tools, $toolsCount, $lastToolKey];
        }

        $tools[$key]['args'] .= $toolArgs;
        return [$tools, $toolsCount, $lastToolKey];
    }
}
