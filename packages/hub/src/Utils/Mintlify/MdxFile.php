<?php

namespace Cognesy\InstructorHub\Utils\Mintlify;

use JetBrains\PhpStorm\Deprecated;
use Webuni\FrontMatter\FrontMatter;

#[Deprecated]
class MdxFile
{
    public function __construct(
        private string $title = '',
        private string $content = '',
        private string $fullPath = '',
        private string $basePath = '',
        private string $fileName = '',
        private string $extension = '',
    ) {}

    public function hasTitle() : bool {
        return $this->title !== '';
    }

    public function title() : string {
        return $this->title;
    }

    public function content() : string {
        return $this->content;
    }

    public function path() : string {
        return $this->fullPath;
    }

    public function basePath() : string {
        return $this->basePath;
    }

    public function fileName() : string {
        return $this->fileName;
    }

    public function extension() : string {
        return $this->extension;
    }

    public static function fromFile(string $path) : MdxFile {
        if (!file_exists($path)) {
            throw new \Exception("Failed to Mintlify index file");
        }
        [$content, $data] = self::yamlFrontMatterFromFile($path);
        return new MdxFile(
            title: $data['title'] ?? '',
            content: $content,
            fullPath: $path,
            basePath: dirname($path),
            fileName: basename($path),
            extension: pathinfo($path, PATHINFO_EXTENSION),
        );
    }

    public static function fromString(string $content) : MdxFile {
        [$content, $data] = self::yamlFrontMatterFromString($content);
        return new MdxFile(
            title: $data['title'] ?? '',
            content: $content,
            fullPath: '',
            basePath: '',
            fileName: '',
            extension: '',
        );
    }

    private static function yamlFrontMatterFromFile(string $path) : array {
        $content = file_get_contents($path);
        return self::yamlFrontMatterFromString($content);
    }

    private static function yamlFrontMatterFromString(string $fileContent) : array {
        $document = FrontMatter::createYaml()->parse($fileContent);
        $content = $document->getContent();
        $data = $document->getData();
        return [$content, $data];
    }
}
