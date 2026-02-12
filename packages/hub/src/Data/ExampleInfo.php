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
    ) {}

    public static function fromFile(string $path, string $name) : ExampleInfo {
        [$content, $data] = self::yamlFrontMatter($path);
        $title = $data['title'] ?? self::getTitle($content);
        $docName = $data['docname'] ?? Str::snake($name);
        $order = isset($data['order']) ? (int) $data['order'] : null;
        $id = $data['id'] ?? '';

        return new ExampleInfo(
            title: $title,
            docName: $docName,
            content: $content,
            order: $order,
            id: $id,
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
}
