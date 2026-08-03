<?php declare(strict_types=1);

use Composer\InstalledVersions;
use Cognesy\InstructorHub\Hub;

it('reports its Composer package version in both version renderers', function (): void {
    $package = 'cognesy/instructor-hub';
    $version = InstalledVersions::isInstalled($package)
        ? InstalledVersions::getPrettyVersion($package)
        : InstalledVersions::getRootPackage()['pretty_version'];
    $hub = new Hub();

    expect($hub->getVersion())->toBe($version)
        ->and($hub->getLongVersion())->toContain("version <comment>{$version}</comment>")
        ->not->toContain('version <comment>2.0.0</comment>');
});
