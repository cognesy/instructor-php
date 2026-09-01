<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Host;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanBuildTellConsoleApplication;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellExtensions;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanContributeTellCommands;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Execution\CanDisposeTellResources;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellWorkspace;
use Cognesy\Tell\Core\Contract\Observation\CanObserveTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanReadTellBranchConfiguration;
use Cognesy\Tell\Core\Contract\Configuration\CanResolveTellConfiguration;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Paths\CanResolveTellPaths;
use Cognesy\Tell\Core\Contract\Secrets\CanResolveTellSecrets;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Adapter\Protocol\OneRun\Contract\CanRunTellProtocol;
use Cognesy\Tell\Data\TellHostDescription;
use Cognesy\Tell\Core\Contract\Execution\CanReadTellClock;
use LogicException;

/** Booted static capability host with named accessors and explicit ownership. */
final class TellHost implements CanDisposeTellResources
{
    private bool $disposed = false;

    /**
     * @param  array<class-string, list<object>>  $providers
     * @param  list<array{id: string, instance: object}>  $constructed
     *
     * @internal Construct through TellHostBuilder.
     */
    public function __construct(
        private readonly array $providers,
        private readonly array $constructed,
        private readonly TellHostDescription $description,
    ) {}

    public function runner(): CanRunTell {
        return $this->singleton(CanRunTell::class);
    }

    public function runtimeFactory(): CanCreateTellRuntime {
        return $this->singleton(CanCreateTellRuntime::class);
    }

    public function agents(): CanBuildTellAgent {
        return $this->singleton(CanBuildTellAgent::class);
    }

    public function model(): CanResolveTellModel {
        return $this->singleton(CanResolveTellModel::class);
    }

    public function secrets(): CanResolveTellSecrets {
        return $this->singleton(CanResolveTellSecrets::class);
    }

    public function workspace(): CanManageTellWorkspace {
        return $this->singleton(CanManageTellWorkspace::class);
    }

    public function conversations(): CanAccessTellConversations {
        return $this->singleton(CanAccessTellConversations::class);
    }

    public function branchConfiguration(): ?CanReadTellBranchConfiguration {
        return $this->optionalSingleton(CanReadTellBranchConfiguration::class);
    }

    public function configuration(): CanResolveTellConfiguration {
        return $this->singleton(CanResolveTellConfiguration::class);
    }

    public function paths(): CanResolveTellPaths {
        return $this->singleton(CanResolveTellPaths::class);
    }

    public function extensions(): CanCatalogueTellExtensions {
        return $this->singleton(CanCatalogueTellExtensions::class);
    }

    public function providerCatalogue(): CanCatalogueTellProviders {
        return $this->singleton(CanCatalogueTellProviders::class);
    }

    public function tools(): CanDispatchTellTool {
        return $this->singleton(CanDispatchTellTool::class);
    }

    public function observer(): CanObserveTellExecution {
        return $this->singleton(CanObserveTellExecution::class);
    }

    /** @return list<CanContributeTellCommands> */
    public function commandContributors(): array {
        return $this->contributors(CanContributeTellCommands::class);
    }

    public function application(): CanBuildTellConsoleApplication {
        return $this->singleton(CanBuildTellConsoleApplication::class);
    }

    public function protocol(): CanRunTellProtocol {
        return $this->singleton(CanRunTellProtocol::class);
    }

    public function cancellation(): CanProvideCancellationSignal {
        return $this->singleton(CanProvideCancellationSignal::class);
    }

    public function clock(): CanReadTellClock {
        return $this->singleton(CanReadTellClock::class);
    }

    public function describe(): TellHostDescription {
        $this->assertActive();

        return $this->description;
    }

    #[\Override]
    public function dispose(): void {
        if ($this->disposed) {
            return;
        }
        $this->disposed = true;
        $errors = TellModuleCleanup::dispose($this->constructed);
        if ($errors !== []) {
            throw new TellHostDisposalException($errors);
        }
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $capability
     * @return T
     */
    private function singleton(string $capability): object {
        $this->assertActive();
        $provider = $this->providers[$capability][0] ?? null;
        if (!$provider instanceof $capability) {
            throw new LogicException("Tell host does not provide {$capability}.");
        }

        return $provider;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $capability
     * @return T|null
     */
    private function optionalSingleton(string $capability): ?object {
        $this->assertActive();
        $provider = $this->providers[$capability][0] ?? null;
        if ($provider !== null && !$provider instanceof $capability) {
            throw new LogicException("Tell host has an invalid provider for {$capability}.");
        }

        return $provider;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $capability
     * @return list<T>
     */
    private function contributors(string $capability): array {
        $this->assertActive();
        $providers = $this->providers[$capability] ?? [];
        foreach ($providers as $provider) {
            if (!$provider instanceof $capability) {
                throw new LogicException("Tell host has an invalid contributor for {$capability}.");
            }
        }

        return $providers;
    }

    private function assertActive(): void {
        if ($this->disposed) {
            throw new TellHostDisposedException();
        }
    }
}
