<?php declare(strict_types=1);

namespace Cognesy\Utils\Tokenization\Drivers;

use Cognesy\Utils\Tokenization\Contracts\CanTokenizeText;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use Override;

/**
 * BPE tokenizer backed by gioni06/gpt3-tokenizer.
 *
 * The vocabulary ships with the package, so this driver works offline and is
 * the default. Building the underlying tokenizer costs ~30 ms and ~25 MB, so
 * it is created once, on first use, and reused for the lifetime of the driver.
 */
final class Gpt3TokenizerDriver implements CanTokenizeText
{
    /**
     * gioni06/gpt3-tokenizer implements the GPT-2 byte-pair encoding, which is
     * the same vocabulary OpenAI publishes as `r50k_base`.
     */
    private const string ENCODING = 'r50k_base';

    private ?Gpt3Tokenizer $tokenizer = null;

    public function __construct(
        private readonly ?Gpt3TokenizerConfig $config = null,
    ) {}

    #[Override]
    public function encoding(): string {
        return self::ENCODING;
    }

    /** @return list<int> */
    #[Override]
    public function encode(string $text): array {
        return array_values($this->tokenizer()->encode($text));
    }

    #[Override]
    public function tokenCount(string $text): int {
        return count($this->tokenizer()->encode($text));
    }

    private function tokenizer(): Gpt3Tokenizer {
        return $this->tokenizer ??= new Gpt3Tokenizer($this->config ?? new Gpt3TokenizerConfig());
    }
}
