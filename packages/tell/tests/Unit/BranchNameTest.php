<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Workspace\Branch\BranchName;
use PHPUnit\Framework\Assert;

beforeEach(function (): void {
    global $tellTemporaryRoots;
    $tellTemporaryRoots = [];
});

it('accepts the closed portable branch grammar', function (): void {
    foreach (['a', 'review-1', 'feature42', str_repeat('a', 64)] as $value) {
        Assert::assertSame($value, BranchName::from($value)->toString());
    }
});

it('rejects invalid, reserved, traversal-like, Unicode, and ambiguous-case branch names', function (string $value): void {
    expect(static fn (): BranchName => BranchName::from($value))->toThrow(InvalidArgumentException::class);
})->with([
    [''],
    ['-review'],
    ['review/'],
    ['../review'],
    ['review..next'],
    ['Review'],
    ['review-Żółć'],
    [str_repeat('a', 65)],
    ['main'],
    ['internal-state'],
    ['session-replay'],
    ['agent-child'],
]);
