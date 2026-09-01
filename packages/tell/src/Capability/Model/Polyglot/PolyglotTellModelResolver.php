<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Model\Polyglot;

use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Core\Secrets\TellCredentialNames;

use Cognesy\Config\Dsn;
use Cognesy\Config\EnvTemplate;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Core\Contract\Secrets\CanResolveTellSecrets;
use Cognesy\Tell\Data\TellRequest;

final readonly class PolyglotTellModelResolver implements CanResolveTellModel
{
    public function __construct(
        private TellPaths $paths,
        private CanResolveTellSecrets $secrets,
    ) {}

    #[\Override]
    public function resolve(TellRequest $request): LLMConfig {
        $config = match ($request->dsn) {
            '' => $this->fromPreset($request),
            default => LLMConfig::fromArray(Dsn::fromString($request->dsn)->toArray()),
        };
        $config = match (true) {
            $request->dsn !== '' => $config,
            $request->model === '' => $config,
            default => $config->withOverrides(['model' => $request->model]),
        };
        return $config;
    }

    private function fromPreset(TellRequest $request): LLMConfig {
        TellCredentialNames::forProvider($request->connection);

        return LLMConfig::fromPreset(
            preset: $request->connection,
            basePath: $this->connectionDirectory($request),
            template: new EnvTemplate($this->secrets),
        );
    }

    private function connectionDirectory(TellRequest $request): ?string {
        $project = rtrim($request->directory, '/\\') . '/config/llm/presets';

        return match (true) {
            is_file($project . '/' . $request->connection . '.yaml') => $project,
            is_file($this->paths->connections . '/' . $request->connection . '.yaml') => $this->paths->connections,
            default => null,
        };
    }

}
