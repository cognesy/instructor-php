<?php declare(strict_types=1);

namespace Cognesy\Utils\Tokenization;

use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenization\Drivers\Gpt3TokenizerDriver;
use Cognesy\Utils\Tokenization\Drivers\TiktokenDriver;
use InvalidArgumentException;
use Throwable;

/**
 * Decides which tokenizer {@see \Cognesy\Utils\Tokenizer} uses when the host
 * application has not chosen one.
 *
 * The default is tiktoken: it is roughly an order of magnitude faster than the
 * bundled GPT-3 tokenizer, uses a fraction of the memory, and speaks the
 * encodings current models actually use. Its vocabulary is downloaded from
 * OpenAI's public storage on first use and cached on disk, so `auto` falls back
 * to the bundled tokenizer when that fails - an install without outbound network
 * access keeps working, just slower.
 *
 * Set `INSTRUCTOR_TOKENIZER` to override without touching code:
 *
 * - `auto` (default) - tiktoken if its vocabulary can be obtained, else bundled
 * - `gpt3` - always the bundled tokenizer, never any network access
 * - `tiktoken` - tiktoken with the default encoding, failing loudly
 * - `tiktoken:cl100k_base` - tiktoken with a specific encoding, failing loudly
 *
 * The explicit forms do not fall back. Asking for a specific tokenizer and
 * silently getting a different one would mean silently getting different token
 * counts, which is worse than an error.
 */
final class TokenizerResolver
{
    public const string ENV_VAR = 'INSTRUCTOR_TOKENIZER';

    /**
     * Encoding used by current OpenAI models, so context budgets computed
     * locally track what the provider will actually charge.
     */
    public const string DEFAULT_ENCODING = 'o200k_base';

    public const string AUTO = 'auto';
    public const string GPT3 = 'gpt3';
    public const string TIKTOKEN = 'tiktoken';

    /**
     * @param string|null $preference One of the values documented above; read from the environment when null.
     * @throws InvalidArgumentException When the preference is not recognized.
     */
    public static function resolve(?string $preference = null): CanCountTokens {
        $preference = trim($preference ?? self::fromEnvironment());

        return match (true) {
            $preference === '' || $preference === self::AUTO => self::auto(),
            $preference === self::GPT3 => new Gpt3TokenizerDriver(),
            $preference === self::TIKTOKEN => self::tiktoken(self::DEFAULT_ENCODING),
            str_starts_with($preference, self::TIKTOKEN . ':') => self::tiktoken(substr($preference, strlen(self::TIKTOKEN) + 1)),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown tokenizer "%s". Expected "auto", "gpt3", "tiktoken", or "tiktoken:<encoding>".',
                $preference,
            )),
        };
    }

    private static function auto(): CanCountTokens {
        if (!TiktokenDriver::isAvailable()) {
            return new Gpt3TokenizerDriver();
        }

        try {
            $driver = TiktokenDriver::forEncoding(self::DEFAULT_ENCODING);
            // Resolves the vocabulary, downloading it if it is not cached yet. Doing
            // it here rather than on the first count keeps the failure recoverable:
            // afterwards the driver is committed and can only throw.
            $driver->encoding();
            return $driver;
        } catch (Throwable) {
            return new Gpt3TokenizerDriver();
        }
    }

    private static function tiktoken(string $encoding): TiktokenDriver {
        if ($encoding === '') {
            throw new InvalidArgumentException('Tokenizer encoding must not be empty, e.g. "tiktoken:' . self::DEFAULT_ENCODING . '".');
        }

        return TiktokenDriver::forEncoding($encoding);
    }

    private static function fromEnvironment(): string {
        $value = $_ENV[self::ENV_VAR] ?? getenv(self::ENV_VAR);

        return is_string($value) ? $value : '';
    }
}
