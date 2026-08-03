<?php declare(strict_types=1);

namespace Cognesy\Agents\Discovery;

use Cognesy\Agents\Collections\NameList;

final readonly class DiscoveryResult
{
    public function __construct(
        public PackageManifests $manifests = new PackageManifests(),
        public NameList $errors = new NameList(),
        public NameList $capabilities = new NameList(),
        public NameList $tools = new NameList(),
    ) {}

    public static function empty(): self {
        return new self();
    }

    public function manifests(): PackageManifests {
        return $this->manifests;
    }

    public function errors(): NameList {
        return $this->errors;
    }

    public function capabilities(): NameList {
        return $this->capabilities;
    }

    public function tools(): NameList {
        return $this->tools;
    }

    public function withManifest(PackageManifest $manifest): self {
        return new self(
            manifests: $this->manifests->with($manifest),
            errors: $this->errors,
            capabilities: $this->capabilities,
            tools: $this->tools,
        );
    }

    public function withError(string $error): self {
        return new self(
            manifests: $this->manifests,
            errors: $this->errors->merge(new NameList($error)),
            capabilities: $this->capabilities,
            tools: $this->tools,
        );
    }

    public function withCapability(string $name): self {
        return new self(
            manifests: $this->manifests,
            errors: $this->errors,
            capabilities: $this->capabilities->merge(new NameList($name)),
            tools: $this->tools,
        );
    }

    public function withTool(string $name): self {
        return new self(
            manifests: $this->manifests,
            errors: $this->errors,
            capabilities: $this->capabilities,
            tools: $this->tools->merge(new NameList($name)),
        );
    }
}
