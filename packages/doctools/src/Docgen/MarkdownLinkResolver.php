<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen;

final readonly class MarkdownLinkResolver
{
    public function __construct(private string $sourceDir, private string $linkPrefix) {}

    public function rewrite(string $content, string $relativePath): string
    {
        $rewritten = preg_replace_callback(
            '#(?<code>```.*?```|~~~.*?~~~|`[^`\n]*`)|\]\((?<url>[^\s)]+)#s',
            function (array $match) use ($relativePath): string {
                if (!isset($match['url'])) {
                    return $match[0];
                }

                return '](' . $this->resolve($match['url'], $relativePath);
            },
            $content,
        );

        return $rewritten ?? throw new \RuntimeException('Failed to resolve Markdown links: ' . preg_last_error_msg());
    }

    private function resolve(string $url, string $relativePath): string
    {
        if (preg_match('/^(?:[a-z][a-z\d+.-]*:|\/\/|#)/i', $url)) {
            return $url;
        }

        $root = realpath($this->sourceDir);
        $pathLength = strcspn($url, '?#');
        $path = rawurldecode(substr($url, 0, $pathLength));
        $base = match (str_starts_with($path, '/')) {
            true => $this->sourceDir . '/' . ltrim($path, '/'),
            false => $this->sourceDir . '/' . dirname($relativePath) . '/' . $path,
        };

        foreach ([$base, $base . '.md', rtrim($base, '/') . '/index.md'] as $candidate) {
            $target = realpath($candidate);
            if ($root === false || $target === false || !is_file($target) || !str_starts_with($target, $root . '/')) {
                continue;
            }

            $relative = substr($target, strlen($root) + 1);
            $resolved = match (trim($this->linkPrefix)) {
                '' => $relative,
                default => rtrim($this->linkPrefix, '/') . '/' . $relative,
            };

            return $resolved . substr($url, $pathLength);
        }

        return $url;
    }
}
