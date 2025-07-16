<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\Utils\Str;
use Webuni\FrontMatter\FrontMatter;

class ExampleInfo
{
    public function __construct(
        public string $title,
        public string $docName,
        public string $content,
    ) {}

    public static function fromFile(string $path, string $name) : ExampleInfo {
        [$content, $data] = self::yamlFrontMatter($path);
        $title = $data['title'] ?? self::getTitle($content);
        $docName = $data['docname'] ?? Str::snake($name);

        return new ExampleInfo(
            title: $title,
            docName: $docName,
            content: $content,
        );
    }

    public function hasTitle() : bool {
        return $this->title !== '';
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    private static function yamlFrontMatter(string $path) : array {
        $content = file_get_contents($path);
        $document = FrontMatter::createYaml()->parse($content);
        $content = $document->getContent();
        $data = $document->getData();
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
        $output = preg_replace('/\s+/', ' ', $output);
        // remove any ANSI codes
        $output = preg_replace('/\e\[[\d;]*m/', '', $output);
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
