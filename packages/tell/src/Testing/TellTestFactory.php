<?php

declare(strict_types=1);

namespace Cognesy\Tell\Testing;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tell;
use InvalidArgumentException;

/**
 * Builds a real Tell runtime around a deterministic in-process agent driver.
 *
 * Request compilation, tools, policies, events, workspaces, and persistence
 * stay real. Only provider inference and its credential are replaced.
 */
final readonly class TellTestFactory
{
    private function __construct(private FakeAgentDriver $driver) {}

    public static function responses(string ...$responses): self {
        return new self(FakeAgentDriver::fromResponses(...$responses));
    }

    public static function steps(ScenarioStep ...$steps): self {
        return new self(FakeAgentDriver::fromSteps(...$steps));
    }

    public static function driver(FakeAgentDriver $driver): self {
        return new self($driver);
    }

    public function open(
        string $directory,
        ?CanProvideCancellationSignal $cancellation = null,
    ): Tell {
        $directory = rtrim($directory, '/\\');
        if ($directory === '' || !is_dir($directory)) {
            throw new InvalidArgumentException("Testing directory does not exist: {$directory}");
        }

        $paths = new TellPaths(
            packageAgents: dirname(__DIR__, 2) . '/resources/agents',
            home: $directory . '/.tell-testing',
        );
        $agents = new TellAgentFactory(
            paths: $paths,
            driver: $this->driver,
        );

        return Tell::open($directory, $agents, $cancellation);
    }
}
