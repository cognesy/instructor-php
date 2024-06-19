<?php

namespace Cognesy\InstructorHub\Utils\Mintlify;

use Spatie\YamlFrontMatter\Document;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class MdxFile
{
    private string $fullPath = '';
    private string $basePath = '';
    private string $fileName = '';
    private string $extension = '';
    private string $title = '';
    private Document $document;

    public function __construct() {}

    public function hasTitle() : bool {
        return $this->title !== '';
    }

    public function title() : string {
        return $this->title;
    }

    public function body() : string {
        return $this->document->body();
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
        $mdxFile = new MdxFile();
        $mdxFile->fullPath = $path;
        $mdxFile->basePath = dirname($path);
        $mdxFile->fileName = basename($path);
        $mdxFile->extension = pathinfo($path, PATHINFO_EXTENSION);
        $mdxFile->document = YamlFrontMatter::parseFile($path);
        $mdxFile->title = $mdxFile->document->matter('title');
        return $mdxFile;
    }

    public static function fromString(string $content) : MdxFile {
        $mdxFile = new MdxFile();
        $mdxFile->document = YamlFrontMatter::parse($content);
        $mdxFile->title = $mdxFile->document->matter('title');
        return $mdxFile;
    }
}
