<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;

/**
 * Executes a skill in an isolated subagent context (context: fork).
 *
 * Creates a minimal AgentLoop, passes the rendered skill body as the task,
 * and returns the final response text.
 */
final readonly class SkillForkExecutor
{
    public function __construct(
        private CanUseTools $driver,
        private Tools $parentTools,
    ) {}

    public function execute(
        Skill $skill,
        ?string $arguments = null,
        ?LLMConfig $parentLLMConfig = null,
    ): string {
        $body = $skill->render($arguments);
        // Strip the <skill> tags — the body is the task prompt
        $body = preg_replace('/<\/?skill[^>]*>/', '', $body);
        $body = trim($body);

        $tools = $this->resolveTools($skill);
        $llmConfig = $this->resolveLLMConfig($skill, $parentLLMConfig);

        $subDriver = $this->driver;
        if ($subDriver instanceof CanAcceptLLMConfig && $llmConfig !== null) {
            $subDriver = $subDriver->withLLMConfig($llmConfig);
        }

        $builder = AgentBuilder::base()
            ->withCapability(new UseTools(...$tools->all()))
            ->withCapability(new UseDriver($subDriver))
            ->withCapability(new UseGuards(maxSteps: 10, maxTokens: 16384, maxExecutionTime: 120));

        $loop = $builder->build();

        $state = AgentState::empty()->withMessages(Messages::fromArray([
            ['role' => 'user', 'content' => $body],
        ]));

        $finalState = $loop->execute($state);

        if ($finalState->status() === ExecutionStatus::Failed) {
            $errors = [];
            foreach ($finalState->errors() as $error) {
                $errors[] = $error->getMessage();
            }
            return "Skill fork execution failed: " . implode('; ', $errors);
        }

        $response = trim($finalState->finalResponse()->toString());
        return $response !== '' ? $response : '(no response from forked skill)';
    }

    private function resolveTools(Skill $skill): Tools {
        if ($skill->allowedTools === []) {
            return $this->parentTools;
        }

        $filtered = new Tools();
        foreach ($skill->allowedTools as $name) {
            if ($this->parentTools->has($name)) {
                $filtered = $filtered->withTool($this->parentTools->get($name));
            }
        }
        return $filtered;
    }

    private function resolveLLMConfig(Skill $skill, ?LLMConfig $parentConfig): ?LLMConfig {
        if ($skill->model !== null && $parentConfig !== null) {
            return new LLMConfig(
                apiUrl: $parentConfig->apiUrl,
                apiKey: $parentConfig->apiKey,
                endpoint: $parentConfig->endpoint,
                queryParams: $parentConfig->queryParams,
                metadata: $parentConfig->metadata,
                model: $skill->model,
                maxTokens: $parentConfig->maxTokens,
                contextLength: $parentConfig->contextLength,
                maxOutputLength: $parentConfig->maxOutputLength,
                driver: $parentConfig->driver,
                options: $parentConfig->options,
                pricing: $parentConfig->pricing,
            );
        }
        if ($skill->model !== null) {
            return new LLMConfig(model: $skill->model);
        }
        return $parentConfig;
    }
}
