<?php
namespace Cognesy\Utils;

use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;

class Tokenizer
{
    public static function tokenCount(string $content) : int {
        $config = new Gpt3TokenizerConfig();
        $tokenizer = new Gpt3Tokenizer($config);
        $tokens = $tokenizer->encode($content);
        return count($tokens);
    }
}
