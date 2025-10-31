<?php declare(strict_types=1);

namespace Cognesy\Experimental\Parser;

use Cognesy\Experimental\Parser\Contracts\Parser;
use Cognesy\Experimental\Parser\Parsers\{SatisfyParser, TokenParser, LiteralParser};
use Cognesy\Experimental\Parser\Combinators\{SepByParser, BetweenParser, ChainlParser, ChoiceParser};

/**
 * Factory for creating parser instances.
 * Provides convenient static methods for common parsers.
 */
class ParserFactory
{
    /**
     * Create a parser that satisfies a predicate.
     */
    public static function satisfy(\Closure $predicate, string $description = 'token'): Parser
    {
        return new SatisfyParser($predicate, $description);
    }

    /**
     * Create a parser for a specific token type.
     */
    public static function token(string $tokenType): Parser
    {
        return new TokenParser($tokenType);
    }

    /**
     * Create a parser for a specific literal value.
     */
    public static function literal(string $tokenType, string $value): Parser
    {
        return new LiteralParser($tokenType, $value);
    }

    /**
     * Create a parser for items separated by a delimiter.
     */
    public static function sepBy(Parser $itemParser, Parser $separatorParser, int $minCount = 0): Parser
    {
        return new SepByParser($itemParser, $separatorParser, $minCount);
    }

    /**
     * Create a parser for content between delimiters.
     */
    public static function between(Parser $open, Parser $close, Parser $content): Parser
    {
        return new BetweenParser($open, $close, $content);
    }

    /**
     * Create a left-associative operator parser.
     */
    public static function chainl(Parser $termParser, Parser $opParser): Parser
    {
        return new ChainlParser($termParser, $opParser);
    }

    /**
     * Create a choice parser (try multiple alternatives).
     */
    public static function choice(Parser ...$parsers): Parser
    {
        if (empty($parsers)) {
            throw new \InvalidArgumentException('choice() requires at least one parser');
        }

        return array_reduce(
            array_slice($parsers, 1),
            fn(Parser $acc, Parser $p) => new ChoiceParser($acc, $p),
            $parsers[0]
        );
    }
}
