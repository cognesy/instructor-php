<?php declare(strict_types=1);

use Cognesy\Messages\Utils\File;

// fromFile() reads a real file from disk, so this belongs in the Integration
// lane rather than tests/Unit/Utils/FileTest.php (fast unit tests must not
// perform filesystem I/O).
describe('File::fromFile() (regression: instructor-r50t.9)', function () {
    beforeEach(function () {
        $this->path = sys_get_temp_dir() . '/instructor-file-test-' . uniqid() . '.txt';
        file_put_contents($this->path, 'hello world');
    });

    afterEach(function () {
        @unlink($this->path);
    });

    it('derives fileName from the file path', function () {
        $file = File::fromFile($this->path);

        expect($file->toContentPart()->toArray()['file']['file_name'])->toBe(basename($this->path));
    });
});
