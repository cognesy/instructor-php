<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Utils\Tokenization\Drivers\Gpt3TokenizerDriver;
use Composer\InstalledVersions;
use JsonException;

/**
 * Read-only projection of the exact canonical history state a Tell turn starts from.
 *
 * The bundled tokenizer is deliberately selected instead of Tokenizer::default():
 * default selection may download and cache a vocabulary, which would violate the
 * no-write contract of context inspection.
 */
final readonly class WorkspaceContextInspector
{
    private const float WARNING_RATIO = 0.80;

    private const float CRITICAL_RATIO = 0.95;

    public function __construct(private Gpt3TokenizerDriver $tokenizer = new Gpt3TokenizerDriver) {}

    /** @return array<string, mixed> */
    public function inspect(
        WorkspaceConversationInspection $conversation,
        AgentDefinition $definition,
        string $connection,
    ): array {
        $history = $conversation->history();
        $state = (new DefinitionStateFactory)
            ->instantiateAgentState($definition)
            ->withMessages($history->messages);
        $systemPrompt = $state->context()->systemPrompt();
        $messages = $state->messages();
        $compiledMessages = $this->compiledMessages($messages);
        $content = $this->content($systemPrompt, $compiledMessages);
        $tokenEstimate = $this->tokenizer->tokenCount($content);
        $config = $state->llmConfig();
        $configuredLimit = $this->configuredLimit($config);
        $compactedFrom = $this->compactedFrom($history);

        return [
            'selector' => $conversation->selector(),
            'head' => $conversation->head()?->toString(),
            'root' => $history->root?->toString(),
            'compaction' => [
                'turnCount' => count($compactedFrom),
                'compactedFrom' => $compactedFrom,
            ],
            'configuration' => [
                'agent' => $definition->name,
                'connection' => $connection,
                'driver' => $this->label($config->driver),
                'model' => $this->label($config->model),
            ],
            'compiled' => [
                'messageCount' => $messages->count(),
                'toolCallCount' => $this->toolCallCount($history),
                'toolResultCount' => $this->toolResultCount($history),
                'systemPromptCharacters' => mb_strlen($systemPrompt),
                'messageCharacters' => mb_strlen($messages->toString()),
                'compiledMessageCharacters' => mb_strlen($compiledMessages),
                'inputCharacters' => mb_strlen($content),
            ],
            'tokens' => [
                'context' => [
                    'value' => $tokenEstimate,
                    'status' => 'estimated',
                    'estimator' => $this->estimator(),
                ],
                'modelCapacity' => [
                    'value' => null,
                    'status' => 'unknown',
                    'reason' => 'Tell has no model-specific capacity catalogue.',
                ],
                'configuredLimit' => $this->configuredLimitRow($configuredLimit),
                'remainingConfiguredLimit' => $this->remainingLimitRow($configuredLimit, $tokenEstimate),
            ],
            'warningThresholds' => $this->warningThresholds($configuredLimit),
            'help' => [
                'Context tokens are a local estimate over the same canonical AgentState history used before the next prompt is appended.',
                'Model capacity is intentionally unknown unless Tell gains verified model-specific metadata.',
            ],
        ];
    }

    private function content(string $systemPrompt, string $messages): string
    {
        return match (true) {
            $systemPrompt === '' => $messages,
            $messages === '' => "system\n{$systemPrompt}",
            default => "system\n{$systemPrompt}\n{$messages}",
        };
    }

    private function compiledMessages(Messages $messages): string
    {
        $compiled = [];
        foreach ($messages->all() as $message) {
            $parts = [$message->role()->value, $message->content()->toString()];
            foreach ($message->toolCalls()->all() as $call) {
                try {
                    $arguments = json_encode(
                        $call->arguments(),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    );
                } catch (JsonException $exception) {
                    throw new WorkspaceException(
                        'Tell canonical tool arguments cannot be estimated safely.',
                        previous: $exception,
                    );
                }
                $parts[] = "tool-call\n{$call->idString()}\n{$call->name()}\n{$arguments}";
            }
            $result = $message->toolResult();
            if ($result !== null) {
                $parts[] = "tool-result\n{$result->callIdString()}\n{$result->toolName()}\n".($result->isError() ? 'error' : 'ok');
            }
            $compiled[] = implode("\n", $parts);
        }

        return implode("\n", $compiled);
    }

    private function configuredLimit(?LLMConfig $config): ?int
    {
        return match (true) {
            $config === null,
            $config->contextLength < 1 => null,
            default => $config->contextLength,
        };
    }

    /** @return array{value: ?int, status: 'exact'|'unknown', source: string} */
    private function configuredLimitRow(?int $limit): array
    {
        return match ($limit) {
            null => [
                'value' => null,
                'status' => 'unknown',
                'source' => 'No positive contextLength is configured.',
            ],
            default => [
                'value' => $limit,
                'status' => 'exact',
                'source' => 'Selected LLM configuration contextLength.',
            ],
        };
    }

    /** @return array{value: ?int, status: 'estimated'|'unknown', source: string} */
    private function remainingLimitRow(?int $limit, int $estimate): array
    {
        return match ($limit) {
            null => [
                'value' => null,
                'status' => 'unknown',
                'source' => 'A configured context limit is unavailable.',
            ],
            default => [
                'value' => max(0, $limit - $estimate),
                'status' => 'estimated',
                'source' => 'Configured limit minus estimated context tokens.',
            ],
        };
    }

    /** @return array{warning: array<string, int|string|null>, critical: array<string, int|string|null>} */
    private function warningThresholds(?int $limit): array
    {
        return match ($limit) {
            null => [
                'warning' => ['percent' => 80, 'tokens' => null, 'status' => 'unknown'],
                'critical' => ['percent' => 95, 'tokens' => null, 'status' => 'unknown'],
            ],
            default => [
                'warning' => [
                    'percent' => 80,
                    'tokens' => (int) floor($limit * self::WARNING_RATIO),
                    'status' => 'exact',
                ],
                'critical' => [
                    'percent' => 95,
                    'tokens' => (int) floor($limit * self::CRITICAL_RATIO),
                    'status' => 'exact',
                ],
            ],
        };
    }

    /** @return array{identity: string, implementation: string, encoding: string, version: ?string} */
    private function estimator(): array
    {
        return [
            'identity' => 'gpt3-bpe',
            'implementation' => Gpt3TokenizerDriver::class,
            'encoding' => $this->tokenizer->encoding(),
            'version' => InstalledVersions::isInstalled('gioni06/gpt3-tokenizer')
                ? InstalledVersions::getPrettyVersion('gioni06/gpt3-tokenizer')
                : null,
        ];
    }

    /** @return list<string> */
    private function compactedFrom(ArenaHistory $history): array
    {
        $hashes = [];
        foreach ($history->turns as $entry) {
            foreach ($entry->turn->lineage()->compactedFrom() as $hash) {
                $hashes[$hash->toString()] = true;
            }
        }

        return array_keys($hashes);
    }

    private function toolCallCount(ArenaHistory $history): int
    {
        return array_sum(array_map(
            static fn (ArenaHistoryTurn $entry): int => count($entry->turn->toolCalls()),
            $history->turns,
        ));
    }

    private function toolResultCount(ArenaHistory $history): int
    {
        return array_sum(array_map(
            static fn (ArenaHistoryTurn $entry): int => count($entry->turn->toolResults()),
            $history->turns,
        ));
    }

    private function label(string $value): string
    {
        return mb_substr($value, 0, 160);
    }
}
