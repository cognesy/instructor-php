<?php

use Cognesy\Doctor\Doctest\Internal\DoctestLexer;
use Cognesy\Doctor\Doctest\Internal\DoctestTokenType as T;

it('tokenizes doctest annotations and regions in PHP', function () {
    $code = <<<'CODE'
// @doctest id="abc123"
echo "x";
// @doctest-region-start name=part
echo "a";
// @doctest-region-end
CODE;

    $lexer = new DoctestLexer('php');
    $types = [];
    foreach ($lexer->tokenize($code) as $tok) {
        $types[] = $tok->type;
    }

    expect($types)->toContain(T::DoctestId);
    expect($types)->toContain(T::DoctestRegionStart);
    expect($types)->toContain(T::DoctestRegionEnd);
});

