<?php
declare(strict_types=1);

use Cognesy\Auxiliary\AstGrep\Data\SearchResult;
use Cognesy\Auxiliary\AstGrep\Data\SearchResults;

describe('SearchResult', function () {
    it('stores search result data', function () {
        $result = new SearchResult(
            file: '/path/to/file.php',
            line: 42,
            match: 'new UserModel($data)',
            context: ['before' => '// Create user', 'after' => 'return $user;']
        );

        expect($result->file)->toBe('/path/to/file.php');
        expect($result->line)->toBe(42);
        expect($result->match)->toBe('new UserModel($data)');
        expect($result->context)->toBe(['before' => '// Create user', 'after' => 'return $user;']);
    });

    it('gets relative path', function () {
        $result = new SearchResult(
            file: '/home/user/project/src/Models/User.php',
            line: 10,
            match: 'class User'
        );

        expect($result->getRelativePath('/home/user/project/'))->toBe('src/Models/User.php');
        expect($result->getRelativePath('/home/user/'))->toBe('project/src/Models/User.php');
        expect($result->getRelativePath('/different/path/'))->toBe('/home/user/project/src/Models/User.php');
    });

    it('extracts directory and filename', function () {
        $result = new SearchResult(
            file: '/path/to/Models/User.php',
            line: 1,
            match: 'namespace App\\Models;'
        );

        expect($result->getDirectory())->toBe('/path/to/Models');
        expect($result->getFilename())->toBe('User.php');
    });

    it('generates match preview', function () {
        $longMatch = "This is a very long match string that contains\nmultiple lines\nand\ttabs and should be truncated for preview purposes to make it more readable";

        $result = new SearchResult(
            file: 'test.php',
            line: 1,
            match: $longMatch
        );

        $preview = $result->getMatchPreview(50);

        expect($preview)->toHaveLength(50);
        expect($preview)->toEndWith('...');
        expect($preview)->not->toContain("\n");
        expect($preview)->not->toContain("\t");
    });

    it('converts to array', function () {
        $result = new SearchResult(
            file: 'file.php',
            line: 10,
            match: 'test match',
            context: ['key' => 'value']
        );

        expect($result->toArray())->toBe([
            'file' => 'file.php',
            'line' => 10,
            'match' => 'test match',
            'context' => ['key' => 'value'],
        ]);
    });
});

describe('SearchResults', function () {
    beforeEach(function () {
        $this->results = [
            new SearchResult('file1.php', 10, 'match1'),
            new SearchResult('file2.php', 20, 'match2'),
            new SearchResult('file1.php', 30, 'match3'),
            new SearchResult('dir/file3.php', 5, 'match4'),
        ];
    });

    it('manages collection of results', function () {
        $collection = new SearchResults($this->results);

        expect($collection->count())->toBe(4);
        expect($collection->isEmpty())->toBeFalse();
        expect($collection->isNotEmpty())->toBeTrue();
    });

    it('adds new results', function () {
        $collection = new SearchResults();
        $newResult = new SearchResult('new.php', 1, 'new match');

        $collection->add($newResult);

        expect($collection->count())->toBe(1);
        expect($collection->first())->toBe($newResult);
    });

    it('gets first and last results', function () {
        $collection = new SearchResults($this->results);

        expect($collection->first())->toBe($this->results[0]);
        expect($collection->last())->toBe($this->results[3]);
    });

    it('filters results', function () {
        $collection = new SearchResults($this->results);

        $filtered = $collection->filter(fn($r) => $r->file === 'file1.php');

        expect($filtered->count())->toBe(2);
        expect($filtered->all()[0]->file)->toBe('file1.php');
        expect($filtered->all()[1]->file)->toBe('file1.php');
    });

    it('maps over results', function () {
        $collection = new SearchResults($this->results);

        $files = $collection->map(fn($r) => $r->file);

        expect($files)->toBe(['file1.php', 'file2.php', 'file1.php', 'dir/file3.php']);
    });

    it('groups results by file', function () {
        $collection = new SearchResults($this->results);

        $grouped = $collection->groupByFile();

        expect(array_keys($grouped))->toBe(['file1.php', 'file2.php', 'dir/file3.php']);
        expect(count($grouped['file1.php']))->toBe(2);
        expect(count($grouped['file2.php']))->toBe(1);
    });

    it('groups results by directory', function () {
        $collection = new SearchResults($this->results);

        $grouped = $collection->groupByDirectory();

        expect(array_keys($grouped))->toBe(['.', 'dir']);
        expect(count($grouped['.']))->toBe(3);
        expect(count($grouped['dir']))->toBe(1);
    });

    it('gets unique files and directories', function () {
        $collection = new SearchResults($this->results);

        expect($collection->getFiles())->toBe(['file1.php', 'file2.php', 'dir/file3.php']);
        expect($collection->getDirectories())->toBe(['.', 'dir']);
    });

    it('sorts by file', function () {
        $collection = new SearchResults($this->results);

        $sorted = $collection->sortByFile();
        $sortedFiles = $sorted->map(fn($r) => $r->file);

        expect($sortedFiles)->toBe(['dir/file3.php', 'file1.php', 'file1.php', 'file2.php']);
    });

    it('sorts by line number', function () {
        $collection = new SearchResults($this->results);

        $sorted = $collection->sortByLine();
        $sortedLines = $sorted->map(fn($r) => $r->line);

        expect($sortedLines)->toBe([5, 10, 20, 30]);
    });

    it('converts to JSON', function () {
        $collection = new SearchResults([
            new SearchResult('test.php', 1, 'match')
        ]);

        $json = $collection->toJson();
        $decoded = json_decode($json, true);

        expect($decoded[0])->toBe([
            'file' => 'test.php',
            'line' => 1,
            'match' => 'match',
            'context' => [],
        ]);
    });

    it('is iterable', function () {
        $collection = new SearchResults($this->results);

        $count = 0;
        foreach ($collection as $key => $result) {
            expect($result)->toBeInstanceOf(SearchResult::class);
            expect($key)->toBe($count);
            $count++;
        }

        expect($count)->toBe(4);
    });
});