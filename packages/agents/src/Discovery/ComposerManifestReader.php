<?php declare(strict_types=1);

namespace Cognesy\Agents\Discovery;

use Composer\InstalledVersions;
use JsonException;
use ReflectionClass;
use Throwable;

final readonly class ComposerManifestReader
{
    private const EXTRA_KEY = 'cognesy-agents';

    public function __construct(
        private string $vendorDir,
        private ?string $rootComposerPath = null,
    ) {}

    public static function installed(
        ?string $vendorDir = null,
        ?string $rootComposerPath = null,
    ): self {
        $resolvedVendorDir = $vendorDir ?? self::runtimeVendorDir();
        $resolvedRootComposerPath = match (true) {
            $rootComposerPath !== null => $rootComposerPath,
            $vendorDir === null => self::runtimeRootComposerPath(),
            default => null,
        };
        return new self($resolvedVendorDir, $resolvedRootComposerPath);
    }

    public function read(): DiscoveryResult {
        $result = DiscoveryResult::empty();
        [$packages, $result] = $this->readInstalledPackages($result);
        foreach ($packages as $index => $package) {
            $result = $this->readPackage($package, "installed package #{$index}", false, $result);
        }
        return $this->readRootPackage($result);
    }

    /** @return array{list<mixed>, DiscoveryResult} */
    private function readInstalledPackages(DiscoveryResult $result): array {
        $path = $this->vendorDir . '/composer/installed.json';
        [$data, $error] = $this->readJson($path);
        if ($error !== null) {
            return [[], $result->withError($error)];
        }
        if (!is_array($data)) {
            return [[], $result->withError("Composer metadata '{$path}' must contain an object or list.")];
        }

        $packages = array_is_list($data) ? $data : ($data['packages'] ?? null);
        if (!is_array($packages) || !array_is_list($packages)) {
            return [[], $result->withError("Composer metadata '{$path}' has no valid packages list.")];
        }
        return [$packages, $result];
    }

    private function readRootPackage(DiscoveryResult $result): DiscoveryResult {
        if ($this->rootComposerPath === null) {
            return $result;
        }
        [$package, $error] = $this->readJson($this->rootComposerPath);
        if ($error !== null) {
            return $result->withError($error);
        }
        return $this->readPackage($package, 'root package', true, $result);
    }

    private function readPackage(
        mixed $package,
        string $source,
        bool $root,
        DiscoveryResult $result,
    ): DiscoveryResult {
        if (!is_array($package)) {
            return $result->withError("Composer {$source} must be an object.");
        }

        $packageName = $package['name'] ?? null;
        if (!is_string($packageName) || trim($packageName) === '') {
            return $result->withError("Composer {$source} has no valid package name.");
        }

        $extra = $package['extra'][self::EXTRA_KEY] ?? null;
        if ($extra === null) {
            return $result;
        }
        if (!is_array($extra)) {
            return $result->withError("Package '{$packageName}' extra." . self::EXTRA_KEY . ' must be an object.');
        }

        [$capabilities, $result] = $this->readMap($extra, 'capabilities', $packageName, $result);
        [$tools, $result] = $this->readMap($extra, 'tools', $packageName, $result);
        if ($capabilities === [] && $tools === []) {
            return $result;
        }
        return $result->withManifest(new PackageManifest($packageName, $capabilities, $tools, $root));
    }

    /** @return array{array<string, class-string>, DiscoveryResult} */
    private function readMap(
        array $extra,
        string $type,
        string $packageName,
        DiscoveryResult $result,
    ): array {
        $map = $extra[$type] ?? [];
        if (!is_array($map) || array_is_list($map) && $map !== []) {
            return [[], $result->withError("Package '{$packageName}' {$type} must be a name-to-class object.")];
        }

        $valid = [];
        foreach ($map as $name => $class) {
            if (!is_string($name) || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name) !== 1) {
                $result = $result->withError("Package '{$packageName}' has invalid {$type} name '{$name}'.");
                continue;
            }
            if (!is_string($class) || !$this->isClassName($class)) {
                $result = $result->withError("Package '{$packageName}' {$type} '{$name}' has invalid class name.");
                continue;
            }
            /** @var class-string $normalized */
            $normalized = ltrim($class, '\\');
            $valid[$name] = $normalized;
        }
        return [$valid, $result];
    }

    private function isClassName(string $class): bool {
        return preg_match(
            '/^\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/',
            $class,
        ) === 1;
    }

    /** @return array{mixed, ?string} */
    private function readJson(string $path): array {
        if (!is_file($path) || !is_readable($path)) {
            return [null, "Composer metadata file is not readable: {$path}"];
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return [null, "Composer metadata file could not be read: {$path}"];
        }
        try {
            return [json_decode($json, true, 512, JSON_THROW_ON_ERROR), null];
        } catch (JsonException $exception) {
            return [null, "Composer metadata '{$path}' is invalid JSON: {$exception->getMessage()}"];
        }
    }

    private static function runtimeVendorDir(): string {
        $file = (new ReflectionClass(InstalledVersions::class))->getFileName();
        if (!is_string($file)) {
            return '';
        }
        return dirname($file, 2);
    }

    private static function runtimeRootComposerPath(): ?string {
        try {
            $root = InstalledVersions::getRootPackage();
            $installPath = $root['install_path'] ?? null;
            return is_string($installPath) ? $installPath . '/composer.json' : null;
        } catch (Throwable) {
            return null;
        }
    }
}
