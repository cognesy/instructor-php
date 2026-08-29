<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Contracts\CanAccessTellConversations;
use Cognesy\Tell\Contracts\CanBuildTellAgent;
use Cognesy\Tell\Contracts\CanBuildTellApplication;
use Cognesy\Tell\Contracts\CanCatalogueTellExtensions;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Contracts\CanContributeTellExtensions;
use Cognesy\Tell\Contracts\CanContributeTellTools;
use Cognesy\Tell\Contracts\CanDispatchTellTool;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Contracts\CanReadTellBranchConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellConfiguration;
use Cognesy\Tell\Contracts\CanResolveTellModel;
use Cognesy\Tell\Contracts\CanResolveTellPaths;
use Cognesy\Tell\Contracts\CanResolveTellSecrets;
use Cognesy\Tell\Contracts\CanRunTell;
use Cognesy\Tell\Contracts\CanRunTellProtocol;
use Cognesy\Tell\Data\TellHostDescription;
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\TellAgentFactory;
use LogicException;

/** Booted static capability host with named accessors and explicit ownership. */
final class TellHost
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

    /** @param callable(): CanUseTools|null $driverFactory */
    public static function standard(
        ?string $directory = null,
        ?TellPaths $paths = null,
        ?callable $driverFactory = null,
        ?TellAgentFactory $agentFactory = null,
        ?CanProvideCancellationSignal $cancellation = null,
    ): TellHostBuilder {
        $cwd = getcwd();
        $directory ??= is_string($cwd) ? $cwd : '.';

        return TellHostBuilder::fromProfile(StandardTellProfile::runtime(
            $directory,
            $paths,
            $driverFactory,
            $agentFactory,
            $cancellation,
        ));
    }

    public function runner(): CanRunTell {
        return $this->singleton(CanRunTell::class);
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

    /** @return list<CanContributeTellExtensions> */
    public function extensionContributors(): array {
        return $this->contributors(CanContributeTellExtensions::class);
    }

    /** @return list<CanContributeTellTools> */
    public function toolContributors(): array {
        return $this->contributors(CanContributeTellTools::class);
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

    public function application(): CanBuildTellApplication {
        return $this->singleton(CanBuildTellApplication::class);
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
