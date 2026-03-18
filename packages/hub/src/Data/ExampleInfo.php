<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\Utils\Markdown\FrontMatter;
use Cognesy\Utils\Str;

class ExampleInfo
{
    public function __construct(
        public string $title,
        public string $docName,
        public string $content,
        public ?int $order = null,
        public string $id = '',
        public bool $skip = false,
        /** @var string[] */
        public array $tags = [],
    ) {}

    public static function fromFile(string $path, string $name) : ExampleInfo {
        [$content, $data] = self::yamlFrontMatter($path);
        $title = $data['title'] ?? self::getTitle($content);
        $docName = $data['docname'] ?? Str::snake($name);
        $order = isset($data['order']) ? (int) $data['order'] : null;
        $id = $data['id'] ?? '';
        $skip = (bool) ($data['skip'] ?? false);
        $tags = self::parseTags($data['tags'] ?? []);

        return new ExampleInfo(
            title: $title,
            docName: $docName,
            content: $content,
            order: $order,
            id: $id,
            skip: $skip,
            tags: $tags,
        );
    }

    public function hasTitle() : bool {
        return $this->title !== '';
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    private static function yamlFrontMatter(string $path) : array {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }
        $document = FrontMatter::parse($content);
        $content = $document->document();
        $data = $document->data();
        return [$content, $data];
    }

    private static function getTitle(string $content) : string {
        $header = self::findMdH1Line($content);
        return self::cleanStr($header, 60);
    }

    /**
     * @return string[]
     */
    private static function parseTags(mixed $value) : array {
        $rawTags = match (true) {
            is_string($value) => preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            is_array($value) => $value,
            default => [],
        };

        $tags = [];
        $seen = [];
        foreach ($rawTags as $tag) {
            $normalized = self::stringTag($tag);
            if ($normalized === '') {
                continue;
            }
            $key = strtolower($normalized);
            if (isset($seen[$key])) {
                continue;
            }
            $tags[] = $normalized;
            $seen[$key] = true;
        }

        return $tags;
    }

    private static function cleanStr(string $input, int $limit) : string {
        // remove any \n, \r, PHP tags, md hashes
        $output = str_replace(["\n", "\r", '<?php', '?>', '#'], [' ', '', '', '', ''], $input);
        // remove leading and trailing spaces
        $output = trim($output);
        // remove double spaces
        $output = preg_replace('/\s+/', ' ', $output) ?? $output;
        // remove any ANSI codes
        $output = preg_replace('/\e\[[\d;]*m/', '', $output) ?? $output;
        return substr(trim($output), 0, $limit);
    }

    private static function findMdH1Line(string $input) : string {
        $lines = explode("\n", $input);
        foreach ($lines as $line) {
            if (substr($line, 0, 2) === '# ') {
                return $line;
            }
        }
        return '';
    }

    private static function stringTag(mixed $value) : string {
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return trim((string) $value);
    }
}
