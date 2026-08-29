<?php

declare(strict_types=1);

namespace Cognesy\Tell\Discovery;

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Discovery\CapabilityDiscovery;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Tell\Contracts\CanCatalogueTellExtensions;
use Cognesy\Tell\Data\TellDiagnostic;
use Cognesy\Tell\Data\TellExtensionCatalogue;
use Cognesy\Tell\Data\TellExtensionDescriptor;
use Cognesy\Tell\Data\TellExtensionDescriptors;
use Cognesy\Tell\Data\TellExtensionKind;

/** Descriptive Composer discovery; it never mounts Tell host modules. */
final readonly class ComposerTellExtensionCatalogue implements CanCatalogueTellExtensions
{
    public function __construct(
        private ?string $vendorDirectory = null,
        private ?string $rootComposerPath = null,
    ) {}

    public function catalogue(string $directory): TellExtensionCatalogue {
        $result = CapabilityDiscovery::discover(
            new AgentCapabilityRegistry(),
            new ToolRegistry(),
            $this->vendorDirectory,
            $this->rootComposerPath ?? rtrim($directory, '/\\') . '/composer.json',
        );
        $descriptors = [];
        foreach ($result->capabilities()->all() as $name) {
            $descriptors[] = new TellExtensionDescriptor(TellExtensionKind::Capability, $name, 'composer');
        }
        foreach ($result->tools()->all() as $name) {
            $descriptors[] = new TellExtensionDescriptor(TellExtensionKind::Tool, $name, 'composer');
        }
        usort($descriptors, static fn (TellExtensionDescriptor $a, TellExtensionDescriptor $b): int => $a->key() <=> $b->key());
        $diagnostics = array_map(
            static fn (string $message): TellDiagnostic => new TellDiagnostic(
                'extension_discovery_error',
                'composer',
                'warning',
                $message,
            ),
            $result->errors()->all(),
        );

        return new TellExtensionCatalogue(new TellExtensionDescriptors(...$descriptors), array_values($diagnostics));
    }
}
