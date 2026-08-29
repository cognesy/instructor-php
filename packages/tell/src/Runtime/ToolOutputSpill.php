<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

/**
 * Moves an oversized tool result out of the conversation and onto disk.
 *
 * A long shell result costs the same tokens on every subsequent step, and the
 * older answer to that - a head/tail window - throws the rest away, so a model
 * that needed line 900 of a test run had no way back to it. Spilling keeps the
 * whole result, in a content-addressed blob under the project, and gives the
 * step a stub instead: what the result was, how big, where it went, and enough
 * of its head that the common case is answered without a read at all.
 *
 * The blob lives inside the project directory because that is the only place
 * the coding tools are allowed to read from; a path outside it would name a
 * file the model is sandboxed away from.
 */
final readonly class ToolOutputSpill
{
    /** Project-relative, and written with forward slashes so the stub reads the same everywhere. */
    private const string DIRECTORY = '.tell/blobs';

    private const int PREVIEW_COLUMNS = 200;

    /** The read window the stub suggests; the read tool's own default. */
    private const int CONTINUE_LINES = 200;

    private const int HASH_LENGTH = 16;

    /** How much of the head is sampled to decide whether a result is text. */
    private const int SNIFF_BYTES = 8_192;

    public function __construct(
        private string $directory,
        private int $threshold,
        private int $ceiling,
        private int $stubBudget = TellExecutionPolicy::DEFAULT_MAX_STUB_BYTES,
    ) {}

    public static function fromPolicy(string $directory, TellExecutionPolicy $policy): self
    {
        return new self(
            $directory,
            $policy->maxToolOutputChars,
            $policy->maxSpillBytes,
            $policy->maxStubBytes,
        );
    }

    /**
     * The replacement for one tool result, or null to leave it alone - because
     * it is small enough, because spilling is off, or because the blob could
     * not be written and the established truncation should stay in charge.
     */
    public function replace(mixed $value): mixed
    {
        if ($this->ceiling <= 0) {
            return null;
        }
        if (is_string($value)) {
            return $this->stub($value);
        }
        // Tell's coding tools answer in a structured envelope whose payload is
        // a single text field. Replacing that field keeps the envelope - and
        // its success flag and error slot - intact for whoever reads it next.
        if (is_array($value) && is_array($value['data'] ?? null) && is_string($value['data']['text'] ?? null)) {
            $stub = $this->stub($value['data']['text']);
            if ($stub === null) {
                return null;
            }
            $value['data']['text'] = $stub;
            $value['truncated'] = true;

            return $value;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return is_string($encoded) ? $this->stub($encoded) : null;
    }

    private function stub(string $text): ?string
    {
        if (strlen($text) <= $this->threshold) {
            return null;
        }
        $binary = self::isBinary($text);
        $stored = $this->clamp($text, $binary);
        $path = $this->write($stored, $binary);
        if ($path === null) {
            return null;
        }
        $discarded = strlen($stored) < strlen($text)
            ? '[the result was '.self::size(strlen($text)).'; everything past the '
                .number_format($this->ceiling).'-byte spill ceiling was discarded]'
            : null;

        // Binary gets no preview and no read hint. Its bytes would be noise in
        // the conversation, and the read tool refuses a file `file` calls
        // binary - a stub that suggested one would promise what Tell cannot do.
        if ($binary) {
            return implode("\n", array_filter([
                '[tool output: '.self::size(strlen($stored)).' of binary data — stored at '.$path.']',
                $discarded,
                'Not text: it has no preview, and the read tool will not open it. Inspect it with a shell command if you need its contents.',
            ]))."\n";
        }

        $lines = self::lines($stored);
        $head = array_values(array_filter([
            '[tool output: '.number_format(count($lines)).' line'.(count($lines) === 1 ? '' : 's')
                .', '.self::size(strlen($stored)).' — stored at '.$path.']',
            $discarded,
        ]));

        return $this->assemble($head, $lines, $path);
    }

    /**
     * Whether a result is something the model can be shown and the read tool
     * can open. The read tool asks `file` for a MIME type; this is stricter,
     * so a stub never offers a read that would come back as an error.
     */
    private static function isBinary(string $text): bool
    {
        $sample = substr($text, 0, self::SNIFF_BYTES);

        return str_contains($sample, "\0") || preg_match('//u', $sample) !== 1;
    }

    /**
     * The stub carries as much of the head as `maxStubBytes` allows.
     *
     * Its size is governed by that budget alone, not by `maxToolOutputChars`:
     * the retained-bytes limit exists to keep a tool result from crowding the
     * conversation, and the stub is the answer to it, not another instance of
     * it. The header and the way back to the blob are never dropped - a stub
     * cut short would name a file and then lose the instruction for reading
     * it - so a budget too small for either simply buys no preview.
     *
     * @param  list<string>  $head
     * @param  list<string>  $lines
     */
    private function assemble(array $head, array $lines, string $path): string
    {
        $preview = [];
        foreach ($lines as $line) {
            $candidate = [...$preview, '  '.self::clip($line)];
            if (strlen(self::join($head, $candidate, $path)) > $this->stubBudget) {
                break;
            }
            $preview = $candidate;
        }

        return self::join($head, $preview, $path);
    }

    /**
     * @param  list<string>  $head
     * @param  list<string>  $preview
     */
    private static function join(array $head, array $preview, string $path): string
    {
        $continue = 'Continue: read("'.$path.'", offset='.count($preview).', limit='.self::CONTINUE_LINES.')';

        return implode("\n", [...$head, ...$preview, $continue])."\n";
    }

    /**
     * Clamping text mid-character would store bytes no reader can decode, and
     * a UTF-8 character is at most four bytes long, so backing off is bounded.
     * Binary has no characters to land between and is cut where it is cut.
     */
    private function clamp(string $text, bool $binary): string
    {
        if (strlen($text) <= $this->ceiling) {
            return $text;
        }
        $bytes = substr($text, 0, $this->ceiling);
        if ($binary) {
            return $bytes;
        }
        for ($dropped = 0; $dropped < 3 && $bytes !== '' && preg_match('//u', $bytes) !== 1; $dropped++) {
            $bytes = substr($bytes, 0, -1);
        }

        return $bytes;
    }

    /**
     * Content-addressed, so a command run twice writes one blob and the second
     * stub points at the first one's bytes. Returns the project-relative path
     * the model should read, or null when the project is not writable.
     */
    private function write(string $content, bool $binary): ?string
    {
        $extension = $binary ? '.bin' : '.txt';
        $hash = substr(hash('sha256', $content), 0, self::HASH_LENGTH);
        $relative = self::DIRECTORY.'/'.$hash.$extension;
        $directory = rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, self::DIRECTORY);
        $path = $directory.DIRECTORY_SEPARATOR.$hash.$extension;
        if (is_file($path)) {
            return $relative;
        }
        if (! is_dir($directory) && ! @mkdir($directory, 0o700, true) && ! is_dir($directory)) {
            return null;
        }
        // Blobs are a byproduct of one turn, not project content. They live in
        // the project only because that is the one place the read tool is
        // allowed to look, so the store excludes itself from the repository.
        if (! is_file($directory.DIRECTORY_SEPARATOR.'.gitignore')) {
            @file_put_contents($directory.DIRECTORY_SEPARATOR.'.gitignore', "*\n");
        }
        // Written aside and renamed: a reader that arrives mid-write would
        // otherwise see a blob whose name promises bytes it does not yet hold.
        $temporary = $path.'.'.getmypid().'.tmp';
        if (@file_put_contents($temporary, $content) === false) {
            return null;
        }
        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            return null;
        }

        return $relative;
    }

    /** @return list<string> */
    private static function lines(string $text): array
    {
        return explode("\n", rtrim($text, "\n"));
    }

    private static function clip(string $line): string
    {
        $line = rtrim($line, "\r");
        if (mb_strlen($line) <= self::PREVIEW_COLUMNS) {
            return $line;
        }

        return mb_substr($line, 0, self::PREVIEW_COLUMNS - 1).'…';
    }

    private static function size(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes.' B',
            $bytes < 1024 * 1024 => number_format($bytes / 1024, 1).' KB',
            default => number_format($bytes / (1024 * 1024), 1).' MB',
        };
    }
}
