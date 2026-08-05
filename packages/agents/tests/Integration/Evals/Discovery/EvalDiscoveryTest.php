<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Evals\Discovery;

use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\EvalDataset;
use Cognesy\Agents\Evals\EvalDiscovery;
use Cognesy\Agents\Evals\EvalTags;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

function evalFixtureDirectory(): string {
    $path = sys_get_temp_dir() . '/cognesy-evals-' . bin2hex(random_bytes(5));
    mkdir($path . '/support', 0777, true);
    return $path;
}

function removeEvalFixtures(string $root): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($root);
}

it('discovers sorted path identities and dataset suffixes', function (): void {
    $root = evalFixtureDirectory();
    file_put_contents($root . '/z.eval.php', "<?php return [\\Cognesy\\Agents\\Evals\\AgentEval::define('z1', static function(): void {}), \\Cognesy\\Agents\\Evals\\AgentEval::define('z2', static function(): void {})];");
    file_put_contents($root . '/support/refund.eval.php', "<?php return \\Cognesy\\Agents\\Evals\\AgentEval::define('refund', static function(): void {});");

    $evals = EvalDiscovery::in($root)->discover();
    expect(array_map(static fn (AgentEval $eval): ?string => $eval->id(), $evals->all()))
        ->toBe(['support/refund', 'z/0000', 'z/0001']);
    removeEvalFixtures($root);
});

it('loads typed json and yaml dataset rows', function (): void {
    $root = evalFixtureDirectory();
    file_put_contents($root . '/rows.json', '[{"prompt":"one"},{"prompt":"two"}]');
    file_put_contents($root . '/rows.yaml', "- prompt: three\n");

    $json = EvalDataset::fromJson($root . '/rows.json');
    $yaml = EvalDataset::fromYaml($root . '/rows.yaml');
    expect($json->count())->toBe(2)
        ->and([...$json][0]->string('prompt'))->toBe('one')
        ->and($yaml->count())->toBe(1);
    removeEvalFixtures($root);
});

it('filters by glob required and excluded tags', function (): void {
    $evals = new AgentEvals(
        AgentEval::define('smoke', static function (): void {}, EvalTags::of('smoke'))->withId('support/refund'),
        AgentEval::define('slow', static function (): void {}, EvalTags::of('slow'))->withId('support/search'),
    );

    expect($evals->filtered('support/*', EvalTags::of('smoke'), EvalTags::of('slow'))->all())
        ->toHaveCount(1)
        ->and($evals->filtered(required: EvalTags::of('missing'))->count())->toBe(0);
});

it('rejects invalid exports and dataset roots', function (): void {
    $root = evalFixtureDirectory();
    file_put_contents($root . '/bad.eval.php', '<?php return "bad";');
    file_put_contents($root . '/bad.json', '{"prompt":"not-list"}');

    expect(fn () => EvalDiscovery::in($root)->discover())->toThrow(InvalidArgumentException::class)
        ->and(fn () => EvalDataset::fromJson($root . '/bad.json'))->toThrow(InvalidArgumentException::class);
    removeEvalFixtures($root);
});
