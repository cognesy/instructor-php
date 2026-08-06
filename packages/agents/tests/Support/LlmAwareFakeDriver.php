<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanAcceptToolRuntime;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMProvider;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;
use Override;

/**
 * Wraps a `FakeAgentDriver` and additionally implements `CanResolveLLMConfig`,
 * so tests can exercise the `AgentProfile::llm` resolution path (and therefore
 * `AgentRun::llmProfile()` threading) with a concrete, network-free `LLMConfig`.
 * `FakeAgentDriver` itself deliberately does not resolve one, so every other
 * test in this suite keeps exercising the honest "no LLM profile available"
 * path unaffected by this addition.
 */
final class LlmAwareFakeDriver implements
    CanUseTools,
    CanAcceptToolRuntime,
    CanAcceptLLMProvider,
    CanAcceptLLMConfig,
    CanAcceptMessageCompiler,
    CanResolveLLMConfig
{
    public function __construct(
        private readonly FakeAgentDriver $inner,
        private readonly LLMConfig $config,
    ) {}

    #[Override]
    public function resolveConfig(): LLMConfig {
        return $this->config;
    }

    #[Override]
    public function useTools(AgentState $state): AgentState {
        return $this->inner->useTools($state);
    }

    #[Override]
    public function llmProvider(): LLMProvider {
        return $this->inner->llmProvider();
    }

    #[Override]
    public function withLLMProvider(LLMProvider $llm): static {
        return new self($this->inner->withLLMProvider($llm), $this->config);
    }

    #[Override]
    public function withLLMConfig(LLMConfig $config): static {
        return new self($this->inner->withLLMConfig($config), $config);
    }

    #[Override]
    public function messageCompiler(): CanCompileMessages {
        return $this->inner->messageCompiler();
    }

    #[Override]
    public function withMessageCompiler(CanCompileMessages $compiler): static {
        return new self($this->inner->withMessageCompiler($compiler), $this->config);
    }

    #[Override]
    public function withToolRuntime(Tools $tools, CanExecuteToolCalls $executor): static {
        return new self($this->inner->withToolRuntime($tools, $executor), $this->config);
    }
}
