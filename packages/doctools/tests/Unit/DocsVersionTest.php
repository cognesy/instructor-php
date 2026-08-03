<?php declare(strict_types=1);

use Composer\InstalledVersions;
use Cognesy\Doctools\Docs;

it('reports its Composer package version', function (): void {
    $package = 'cognesy/instructor-doctools';
    $version = InstalledVersions::isInstalled($package)
        ? InstalledVersions::getPrettyVersion($package)
        : InstalledVersions::getRootPackage()['pretty_version'];

    expect((new Docs())->getVersion())->toBe($version)
        ->not->toBe('1.0.0');
});
