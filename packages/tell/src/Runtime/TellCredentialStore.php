<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Cognesy\Config\Secrets\DotenvFileSecretSource;
use Dotenv\Dotenv;
use RuntimeException;
use SensitiveParameter;
use Throwable;

final readonly class TellCredentialStore
{
    public function __construct(private TellPaths $paths) {}

    public function source(): DotenvFileSecretSource
    {
        $this->assertPrivateFile();

        return DotenvFileSecretSource::optional($this->paths->credentials, 'tell-credentials');
    }

    /** @return list<string> */
    public function variables(): array
    {
        $variables = array_keys($this->read());
        sort($variables);

        return $variables;
    }

    public function set(string $variable, #[SensitiveParameter] string $value): bool
    {
        TellCredentialNames::assertVariable($variable);
        if ($value === '' || str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new RuntimeException('Credential input must be a non-empty single line.');
        }
        $credentials = $this->read();
        $changed = ($credentials[$variable] ?? null) !== $value;
        $credentials[$variable] = $value;
        $this->write($credentials);

        return $changed;
    }

    public function remove(string $variable): bool
    {
        TellCredentialNames::assertVariable($variable);
        $credentials = $this->read();
        if (! array_key_exists($variable, $credentials)) {
            return false;
        }
        unset($credentials[$variable]);
        $this->write($credentials);

        return true;
    }

    /** @return array<string, string> */
    private function read(): array
    {
        if (! file_exists($this->paths->credentials)) {
            return [];
        }
        $this->assertPrivateFile();
        $content = file_get_contents($this->paths->credentials);
        if (! is_string($content)) {
            throw new RuntimeException('Unable to read Tell credentials file.');
        }
        try {
            $parsed = Dotenv::parse($content);
        } catch (Throwable) {
            throw new RuntimeException('Unable to parse Tell credentials file.');
        }
        $credentials = [];
        foreach ($parsed as $name => $value) {
            if (! is_string($value)) {
                continue;
            }
            TellCredentialNames::assertVariable($name);
            $credentials[$name] = $value;
        }

        return $credentials;
    }

    /** @param array<string, string> $credentials */
    private function write(#[SensitiveParameter] array $credentials): void
    {
        $directory = (new TellStorage($this->paths))->ensureConfig();
        if (is_link($this->paths->credentials)) {
            throw new RuntimeException('Refusing to replace a symbolic-link Tell credentials file.');
        }
        ksort($credentials);
        $content = implode('', array_map(
            fn (string $name, string $value): string => $name.'='.$this->encode($value)."\n",
            array_keys($credentials),
            array_values($credentials),
        ));
        $temporary = tempnam($directory, '.credentials-');
        if (! is_string($temporary)) {
            throw new RuntimeException('Unable to create a temporary Tell credentials file.');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write Tell credentials file.');
            }
            @chmod($temporary, 0600);
            if (! @rename($temporary, $this->paths->credentials)) {
                throw new RuntimeException('Unable to replace Tell credentials file atomically.');
            }
            @chmod($this->paths->credentials, 0600);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function assertPrivateFile(): void
    {
        if (! file_exists($this->paths->credentials)) {
            return;
        }
        if (is_link($this->paths->credentials) || ! is_file($this->paths->credentials)) {
            throw new RuntimeException('Tell credentials path must be a regular file.');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }
        $permissions = fileperms($this->paths->credentials);
        if (is_int($permissions) && ($permissions & 0077) !== 0) {
            throw new RuntimeException('Tell credentials file permissions are too broad; run chmod 600 ~/.tell/config/credentials.env.');
        }
    }

    private function encode(#[SensitiveParameter] string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', '$'],
            ['\\\\', '\\"', '\\$'],
            $value,
        );

        return '"'.$escaped.'"';
    }
}
