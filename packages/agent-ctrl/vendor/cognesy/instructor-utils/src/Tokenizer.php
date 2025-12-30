<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;

/**
 * Class Tokenizer
 * Provides utility functions for tokenizing text using the GPT-3 tokenizer.
 */
class Tokenizer
{
    /**
     * Counts the number of tokens in a given string content.
     *
     * @param string $content The content to be tokenized.
     * @return int The number of tokens in the content.
     */
    public static function tokenCount(string $content) : int {
        $config = new Gpt3TokenizerConfig();
        $tokenizer = new Gpt3Tokenizer($config);
        $tokens = $tokenizer->encode($content);
        return count($tokens);
    }
}