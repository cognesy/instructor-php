<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Composition\StandardTellProfile;
use Cognesy\Tell\Composition\TellHostBuilder;
use Cognesy\Tell\Contracts\CanResolveTellModel;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\TellRequest;

it('runs the standard modular host with a replaced driver factory', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $host = TellHostBuilder::fromProfile(StandardTellProfile::runtime(
        directory: $project,
        paths: $paths,
        driverFactory: static fn () => FakeAgentDriver::fromResponses('host response'),
    ))->boot();

    $result = $host->runner()->run(TellRequest::prompt('Run via host')->withDirectory($project));

    expect(trim($result->text()))->toBe('host response')
        ->and(array_column($host->describe()->modules, 'id'))->toBe([
            'paths.standard',
            'secrets.standard',
            'model.polyglot',
            'clock.system',
            'cancellation.memory',
            'agent.cognesy',
            'workspace.filesystem',
            'configuration.standard',
            'extensions.composer',
            'tools.standard',
            'observation.standard',
            'execution.default',
            'protocol.one-run',
            'commands.core',
            'application.symfony',
        ])
        ->and(json_encode($host->describe()->toArray(), JSON_THROW_ON_ERROR))->not->toContain('host response');

    $host->dispose();
});

it('resolves a model once when an immutable definition is handed to loop construction', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $calls = new ArrayObject;
    $resolver = new class($calls) implements CanResolveTellModel
    {
        public function __construct(private ArrayObject $calls) {}

        public function resolve(TellRequest $request): LLMConfig
        {
            $this->calls->append($request->model);

            return LLMConfig::fromArray([
                'driver' => 'openai',
                'model' => 'gpt-4o-mini',
                'apiUrl' => 'https://api.openai.com/v1',
                'apiKey' => 'not-a-real-key',
            ]);
        }
    };
    $factory = new TellAgentFactory(paths: $paths, modelResolver: $resolver);
    $request = TellRequest::prompt('Resolve exactly once')->withDirectory($project);
    $definition = $factory->definition($request->toOptions());

    $factory->build($request->toOptions(), $definition);

    expect($calls)->toHaveCount(1);
});
