<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone;

use Closure;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Adapter\Console\Symfony\TellCommands;
use Cognesy\Tell\Adapter\Console\Symfony\TellConsoleApplication;
use Cognesy\Tell\Core\Agent\TellAgentContributions;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Agent\CanContributeTellAgent;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Tell;
use Cognesy\Utils\Container\SimpleContainer;
use LogicException;
use Symfony\Component\Console\Command\Command;

/** Configures and resolves one isolated standalone Tell object graph. */
final class StandaloneTellBuilder
{
    private bool $built = false;
    private TellAgentContributions $additionalContributions;
    private ?TellAgentContributions $replacementContributions = null;
    private TellCommands $additionalCommands;

    private function __construct(
        private readonly string $directory,
        ?TellPaths $paths,
        private readonly SimpleContainer $services,
    ) {
        $this->additionalContributions = TellAgentContributions::empty();
        $this->additionalCommands = TellCommands::empty();
        StandardTellServices::registerRuntime(
            services: $this->services,
            directory: $this->directory,
            paths: $paths,
            selectContributions: fn (TellAgentContributions $standard): TellAgentContributions
                => $this->selectContributions($standard),
        );
    }

    public static function in(string $directory, ?TellPaths $paths = null): self {
        return new self($directory, $paths, new SimpleContainer());
    }

    /** @param callable(): CanUseTools $factory */
    public function withDriverFactory(callable $factory): self {
        $this->assertConfigurable();
        StandardTellServices::registerAgent(
            services: $this->services,
            directory: $this->directory,
            selectContributions: fn (TellAgentContributions $standard): TellAgentContributions
                => $this->selectContributions($standard),
            driverFactory: Closure::fromCallable($factory),
        );

        return $this;
    }

    public function withAgentBuilder(CanBuildTellAgent $agents): self {
        $this->assertConfigurable();
        $this->services->instance(CanBuildTellAgent::class, $agents);

        return $this;
    }

    public function withCancellation(CanProvideCancellationSignal $cancellation): self {
        $this->assertConfigurable();
        $this->services->instance(CanProvideCancellationSignal::class, $cancellation);

        return $this;
    }

    public function withModelResolver(CanResolveTellModel $modelResolver): self {
        $this->assertConfigurable();
        $this->services->instance(CanResolveTellModel::class, $modelResolver);

        return $this;
    }

    public function withObserver(CanObserveTellExecution $observer): self {
        $this->assertConfigurable();
        $this->services->instance(CanObserveTellExecution::class, $observer);

        return $this;
    }

    public function withWorkspace(CanProvideTellWorkspace $workspace): self {
        $this->assertConfigurable();
        StandardTellServices::registerWorkspace($this->services, $workspace);

        return $this;
    }

    public function withAgentContribution(CanContributeTellAgent $contribution): self {
        $this->assertConfigurable();
        $this->additionalContributions = $this->additionalContributions->with($contribution);

        return $this;
    }

    public function withAgentContributions(TellAgentContributions $contributions): self {
        $this->assertConfigurable();
        $this->replacementContributions = $contributions;
        $this->additionalContributions = TellAgentContributions::empty();

        return $this;
    }

    public function withCommand(Command $command): self {
        $this->assertConfigurable();
        $this->additionalCommands = $this->additionalCommands->with($command);

        return $this;
    }

    public function build(): Tell {
        $this->beginBuild();

        return $this->get(Tell::class);
    }

    public function buildCli(): TellConsoleApplication {
        $this->beginBuild();
        StandardTellServices::registerCli($this->services, $this->directory, $this->additionalCommands);

        return $this->get(TellConsoleApplication::class);
    }

    private function beginBuild(): void {
        $this->assertConfigurable();
        $this->built = true;
    }

    private function assertConfigurable(): void {
        if ($this->built) {
            throw new LogicException('A Tell builder cannot be reused after build.');
        }
    }

    private function selectContributions(TellAgentContributions $standard): TellAgentContributions {
        $selected = $this->replacementContributions ?? $standard;

        return $selected->with(...$this->additionalContributions->all());
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private function get(string $id): object {
        $service = $this->services->get($id);
        if (!$service instanceof $id) {
            throw new LogicException("Tell root {$id} resolved an invalid implementation.");
        }

        return $service;
    }
}
