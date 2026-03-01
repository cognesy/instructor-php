<?php declare(strict_types=1);

use Cognesy\Schema\Utils\DocblockInfo;

it('extracts docblock summary without tags', function () {
    $docblock = <<<'PHPDOC'
/**
 * Build search parameters.
 * Includes strict filtering rules.
 *
 * @param string $query Search phrase
 * @return array<string,mixed>
 */
PHPDOC;

    expect(DocblockInfo::summary($docblock))
        ->toBe("Build search parameters.\nIncludes strict filtering rules.");
});

it('extracts parameter description by parameter name', function () {
    $docblock = <<<'PHPDOC'
/**
 * @param string $query Search phrase
 * @param int $limit Max items
 */
PHPDOC;

    expect(DocblockInfo::parameterDescription($docblock, 'query'))->toBe('Search phrase')
        ->and(DocblockInfo::parameterDescription($docblock, 'limit'))->toBe('Max items')
        ->and(DocblockInfo::parameterDescription($docblock, 'missing'))->toBe('');
});

