<?php

declare(strict_types=1);

use Cognesy\Tell\Tests\Support\Architecture\TellArchitectureRules;

function tellArchitectureSource(string $namespace): string
{
    return dirname(__DIR__, 2) . '/src/' . $namespace;
}

function tellArchitectureFixture(string $name): string
{
    return dirname(__DIR__) . '/Fixtures/Architecture/' . $name . '.php.fixture';
}

it('keeps Data independent from every higher Tell namespace', function (): void {
    $files = TellArchitectureRules::phpFiles(tellArchitectureSource('Data'));

    expect(TellArchitectureRules::dependencyViolations(
        $files,
        ['Cognesy\\Tell\\Data\\'],
    ))->toBe([])
        ->and(TellArchitectureRules::frameworkViolations($files))->toBe([]);
});

it('keeps Core contracts limited to Data and Core', function (): void {
    expect(TellArchitectureRules::dependencyViolations(
        TellArchitectureRules::phpFiles(tellArchitectureSource('Core/Contract')),
        [
            'Cognesy\\Tell\\Data\\',
            'Cognesy\\Tell\\Core\\',
        ],
    ))->toBe([]);
});

it('keeps Core limited to Data and Core without framework containers', function (): void {
    $files = TellArchitectureRules::phpFiles(tellArchitectureSource('Core'));

    expect(TellArchitectureRules::dependencyViolations($files, [
        'Cognesy\\Tell\\Data\\',
        'Cognesy\\Tell\\Core\\',
    ]))->toBe([])
        ->and(TellArchitectureRules::frameworkViolations($files))->toBe([]);
});

it('keeps adapters on public Data Core and contract boundaries', function (): void {
    expect(TellArchitectureRules::dependencyViolations(
        TellArchitectureRules::phpFiles(tellArchitectureSource('Adapter')),
        [
            'Cognesy\\Tell\\Adapter\\',
            'Cognesy\\Tell\\Data\\',
            'Cognesy\\Tell\\Core\\Contract\\',
            'Cognesy\\Tell\\Core\\Configuration\\TellConfig',
            'Cognesy\\Tell\\Core\\Observation\\TellEventNormalizer',
            'Cognesy\\Tell\\Core\\Paths\\TellPaths',
            'Cognesy\\Tell\\Core\\Secrets\\TellCredentialNames',
            'Cognesy\\Tell\\Core\\Workspace\\Branch\\BranchName',
            'Cognesy\\Tell\\Core\\Workspace\\WorkspaceException',
        ],
    ))->toBe([]);
});

it('keeps capability implementations independent of sibling families and import side effects', function (): void {
    $files = TellArchitectureRules::phpFiles(tellArchitectureSource('Capability'));

    expect(TellArchitectureRules::dependencyViolations($files, [
        'Cognesy\\Tell\\Capability\\',
        'Cognesy\\Tell\\Data\\',
        'Cognesy\\Tell\\Core\\',
    ]))->toBe([])
        ->and(TellArchitectureRules::capabilitySiblingViolations($files))->toBe([])
        ->and(TellArchitectureRules::importSideEffectViolations($files))->toBe([]);
});

it('keeps shell-job host ownership and default strategy selection in Composition', function (): void {
    $files = TellArchitectureRules::phpFiles(tellArchitectureSource('Capability/ShellJob'));
    $hostFiles = array_values(array_filter(
        $files,
        static fn (string $file): bool => str_contains(basename($file), 'Host'),
    ));
    $source = implode('', array_map(
        static fn (string $file): string => (string) file_get_contents($file),
        $files,
    ));

    expect($hostFiles)->toBe([])
        ->and($source)->not->toContain('new TellShellJobPolicy()')
        ->not->toContain('TellShellJobApprovals::denyAll()')
        ->not->toContain('new NullTellShellJobObserver()');
});

it('ratchets concrete capability selection toward Composition only', function (): void {
    $files = TellArchitectureRules::phpFiles(dirname(__DIR__, 2) . '/src');

    expect(TellArchitectureRules::providerSelectionViolations($files))->toBe([])
        ->and(TellArchitectureRules::providerCompositionViolations($files))->toBe([]);
});

it('rejects an invalid fixture for every mandatory dependency direction', function (): void {
    expect(TellArchitectureRules::dependencyViolations(
        [tellArchitectureFixture('invalid-data-import')],
        ['Cognesy\\Tell\\Data\\'],
    ))->toBe(['invalid-data-import.php.fixture: Cognesy\\Tell\\Adapter\\Console\\Symfony\\TellOptions'])
        ->and(TellArchitectureRules::dependencyViolations(
            [tellArchitectureFixture('invalid-contract-import')],
            ['Cognesy\\Tell\\Data\\', 'Cognesy\\Tell\\Core\\'],
        ))->toBe(['invalid-contract-import.php.fixture: Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceRepository'])
        ->and(TellArchitectureRules::dependencyViolations(
            [tellArchitectureFixture('invalid-core-import')],
            ['Cognesy\\Tell\\Data\\', 'Cognesy\\Tell\\Core\\'],
        ))->toBe(['invalid-core-import.php.fixture: Cognesy\\Tell\\Capability\\Observation\\Psr\\PsrTellObserver'])
        ->and(TellArchitectureRules::frameworkViolations(
            [tellArchitectureFixture('invalid-core-framework-import')],
        ))->toBe(['invalid-core-framework-import.php.fixture: Psr\\Container\\ContainerInterface'])
        ->and(TellArchitectureRules::dependencyViolations(
            [tellArchitectureFixture('invalid-adapter-import')],
            ['Cognesy\\Tell\\Data\\', 'Cognesy\\Tell\\Core\\'],
        ))->toBe(['invalid-adapter-import.php.fixture: Cognesy\\Tell\\Capability\\Observation\\Psr\\PsrTellObserver'])
        ->and(TellArchitectureRules::capabilitySiblingViolations(
            [tellArchitectureFixture('invalid-capability-sibling-import')],
        ))->toBe(['invalid-capability-sibling-import.php.fixture: Cognesy\\Tell\\Capability\\Workspace\\Filesystem\\FilesystemTellWorkspaceProvider'])
        ->and(TellArchitectureRules::capabilitySiblingViolations(
            [tellArchitectureFixture('invalid-capability-strategy-import')],
        ))->toBe(['invalid-capability-strategy-import.php.fixture: Cognesy\\Tell\\Capability\\Observation\\FilesystemTrace\\ExecutionTraceWriter'])
        ->and(TellArchitectureRules::providerSelectionViolations(
            [tellArchitectureFixture('invalid-provider-selection')],
        ))->toBe(['invalid-provider-selection.php.fixture: new Cognesy\\Tell\\Capability\\Observation\\Psr\\PsrTellObserver'])
        ->and(TellArchitectureRules::providerSelectionViolations(
            [tellArchitectureFixture('invalid-aliased-provider-selection')],
        ))->toBe(['invalid-aliased-provider-selection.php.fixture: new Cognesy\\Tell\\Capability\\Observation\\Psr\\PsrTellObserver'])
        ->and(TellArchitectureRules::providerSelectionViolations(
            [tellArchitectureFixture('invalid-fully-qualified-provider-selection')],
        ))->toBe(['invalid-fully-qualified-provider-selection.php.fixture: new Cognesy\\Tell\\Capability\\Observation\\Psr\\PsrTellObserver'])
        ->and(TellArchitectureRules::providerSelectionViolations(
            [tellArchitectureFixture('invalid-static-provider-selection')],
        ))->toBe(['invalid-static-provider-selection.php.fixture: Cognesy\\Tell\\Capability\\Observation\\Psr\\PsrTellObserver::create'])
        ->and(TellArchitectureRules::dependencyViolations(
            [tellArchitectureFixture('invalid-grouped-import')],
            [
                'Cognesy\\Tell\\Data\\',
                'Cognesy\\Tell\\Core\\Contract\\',
            ],
        ))->toBe([
            'invalid-grouped-import.php.fixture: Cognesy\\Tell\\Core\\Workspace\\Branch\\BranchResolver',
            'invalid-grouped-import.php.fixture: Cognesy\\Tell\\Core\\Workspace\\Branch\\Storage\\BranchStore',
            'invalid-grouped-import.php.fixture: Cognesy\\Tell\\Core\\Workspace\\Compaction\\CompactionRunner',
            'invalid-grouped-import.php.fixture: Cognesy\\Tell\\Core\\Workspace\\Conversation\\ConversationReader',
        ])
        ->and(TellArchitectureRules::providerCompositionViolations(
            [tellArchitectureFixture('invalid-provider-owned-composition')],
        ))->toBe([
            'invalid-provider-owned-composition.php.fixture: Cognesy\\Tell\\Capability\\ShellJob\\Process\\TellShellJobApprovals::denyAll',
            'invalid-provider-owned-composition.php.fixture: defines Cognesy\\Tell\\Capability\\ShellJob\\Process\\InvalidTellShellJobHost',
            'invalid-provider-owned-composition.php.fixture: new Cognesy\\Tell\\Capability\\ShellJob\\Process\\NullTellShellJobObserver',
            'invalid-provider-owned-composition.php.fixture: new Cognesy\\Tell\\Capability\\ShellJob\\Process\\TellShellJobPolicy',
        ])
        ->and(TellArchitectureRules::providerSelectionViolations(
            [tellArchitectureFixture('valid-provider-private-helper')],
        ))->toBe([])
        ->and(TellArchitectureRules::providerCompositionViolations(
            [tellArchitectureFixture('valid-provider-private-helper')],
        ))->toBe([])
        ->and(TellArchitectureRules::importSideEffectViolations(
            [tellArchitectureFixture('invalid-provider-import-side-effect')],
        ))->toBe(['invalid-provider-import-side-effect.php.fixture:5 has top-level execution']);
});
