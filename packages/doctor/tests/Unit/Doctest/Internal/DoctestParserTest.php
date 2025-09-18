<?php

use Cognesy\Doctor\Doctest\Internal\DoctestLexer;
use Cognesy\Doctor\Doctest\Internal\DoctestParser;
use Cognesy\Doctor\Doctest\Nodes\DoctestIdNode;
use Cognesy\Doctor\Doctest\Nodes\DoctestRegionNode;

it('parses doctest id and region nodes', function () {
    $code = <<<'CODE'
// @doctest id="demo"
// @doctest-region-start name=alpha
echo "A";
// @doctest-region-end
CODE;

    $lexer = new DoctestLexer('php');
    $parser = new DoctestParser();
    $nodes = iterator_to_array($parser->parse($lexer->tokenize($code)));

    $ids = array_values(array_filter($nodes, fn($n) => $n instanceof DoctestIdNode));
    $regions = array_values(array_filter($nodes, fn($n) => $n instanceof DoctestRegionNode));

    expect($ids)->toHaveCount(1);
    expect($ids[0]->id)->toBe('demo');
    expect($regions)->toHaveCount(1);
    expect($regions[0]->name)->toBe('alpha');
    expect($regions[0]->content)->toContain('echo "A";');
});

