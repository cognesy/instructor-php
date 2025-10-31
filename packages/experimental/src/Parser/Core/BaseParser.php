<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser\Core;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Combinators\{MapParser, SequenceParser, ChoiceParser, ManyParser, OptionalParser};

/**
 * Base implementation providing combinator methods.
 */
abstract class BaseParser implements Parser
{
    abstract public function parse(ParserState $state): ParseResult;

    public function map(callable $fn): Parser
    {
        return new MapParser($this, $fn);
    }

    public function then(Parser $next): Parser
    {
        return new SequenceParser($this, $next);
    }

    public function or(Parser $alternative): Parser
    {
        return new ChoiceParser($this, $alternative);
    }

    public function many(): Parser
    {
        return new ManyParser($this, minCount: 0);
    }

    public function many1(): Parser
    {
        return new ManyParser($this, minCount: 1);
    }

    public function optional(): Parser
    {
        return new OptionalParser($this);
    }
}
