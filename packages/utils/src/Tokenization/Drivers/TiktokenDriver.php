<?php declare(strict_types=1);

namespace Cognesy\Utils\Tokenization\Drivers;

use Closure;
use Cognesy\Utils\Tokenization\Contracts\CanTokenizeText;
use Override;
use RuntimeException;
use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * BPE tokenizer backed by yethee/tiktoken, an optional dependency.
 *
 * Faster and leaner than {@see Gpt3TokenizerDriver} (roughly 25x on longer
 * texts, a third of the memory), and it covers the modern OpenAI encodings -
 * but the vocabulary is downloaded from OpenAI's public storage on first use
 * and cached on disk, so the first call needs network access. Point it at a
 * pre-populated cache directory to keep it offline.
 */
final class TiktokenDriver implements CanTokenizeText
{
    private ?Encoder $encoder;

    /** @var (Closure(): Encoder)|null */
    private ?Closure $factory;

    /** @param (Closure(): Encoder)|null $factory */
    private function __construct(?Encoder $encoder, ?Closure $factory) {
        $this->encoder = $encoder;
        $this->factory = $factory;
    }

    /**
     * Wraps an encoder you built yourself - use this to control the vocabulary
     * source, e.g. a Vocab loaded from a file you ship with your application.
     */
    public static function using(Encoder $encoder): self {
        return new self($encoder, null);
    }

    /**
     * @param non-empty-string $encoding One of EncoderProvider::ENCODINGS, e.g. `cl100k_base`.
     * @param string|null $cacheDir Where downloaded vocabularies are cached; defaults to the library's own location.
     */
    public static function forEncoding(string $encoding = 'cl100k_base', ?string $cacheDir = null): self {
        return new self(null, static function () use ($encoding, $cacheDir): Encoder {
            return self::provider($cacheDir)->get($encoding);
        });
    }

    /**
     * @param non-empty-string $model Model name, e.g. `gpt-4o` - the matching encoding is resolved by the library.
     * @param string|null $cacheDir Where downloaded vocabularies are cached; defaults to the library's own location.
     */
    public static function forModel(string $model, ?string $cacheDir = null): self {
        return new self(null, static function () use ($model, $cacheDir): Encoder {
            return self::provider($cacheDir)->getForModel($model);
        });
    }

    public static function isAvailable(): bool {
        return class_exists(EncoderProvider::class);
    }

    #[Override]
    public function encoding(): string {
        return $this->encoder()->getEncoding();
    }

    /** @return list<int> */
    #[Override]
    public function encode(string $text): array {
        return $this->encoder()->encode($text);
    }

    #[Override]
    public function tokenCount(string $text): int {
        return count($this->encoder()->encode($text));
    }

    private function encoder(): Encoder {
        if ($this->encoder === null) {
            assert($this->factory !== null);
            $this->encoder = ($this->factory)();
            $this->factory = null;
        }

        return $this->encoder;
    }

    private static function provider(?string $cacheDir): EncoderProvider {
        if (!self::isAvailable()) {
            throw new RuntimeException('yethee/tiktoken is not installed - run `composer require yethee/tiktoken` to use ' . self::class . '.');
        }

        $provider = new EncoderProvider();
        if ($cacheDir !== null && $cacheDir !== '') {
            $provider->setVocabCache($cacheDir);
        }

        return $provider;
    }
}
