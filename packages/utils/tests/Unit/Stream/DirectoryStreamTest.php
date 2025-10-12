<?php declare(strict_types=1);

use Cognesy\Utils\Stream\Filesystem\DirectoryStream;

function tmpdir_build_tree(array $entries): string {
    $root = sys_get_temp_dir() . '/ds_' . uniqid();
    mkdir($root, 0777, true);
    foreach ($entries as $path => $content) {
        $full = $root . '/' . $path;
        $dir = dirname($full);
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        if ($content === null) { mkdir($full, 0777, true); continue; }
        file_put_contents($full, (string)$content);
    }
    return $root;
}

function tmpdir_remove_tree(string $root): void {
    if (!is_dir($root)) { return; }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    @rmdir($root);
}

test('DirectoryStream files recursive with extension filter', function () {
    $root = tmpdir_build_tree([
        'a/x.php' => '',
        'a/y.txt' => 't',
        'b/c/z.PHP' => 'u',
        'empty' => null,
    ]);
    try {
        $s = DirectoryStream::from($root)->withExtensions('php')->files(true);
        $names = array_map('basename', iterator_to_array($s->getIterator(), false));
        expect($names)->toEqualCanonicalizing(['x.php', 'z.PHP']);
    } finally {
        tmpdir_remove_tree($root);
    }
});

test('DirectoryStream dirs non-recursive', function () {
    $root = tmpdir_build_tree([
        'a' => null,
        'a/b' => null,
        'c' => null,
    ]);
    try {
        $s = DirectoryStream::from($root)->dirs(false);
        $names = array_map('basename', iterator_to_array($s->getIterator(), false));
        expect($names)->toEqualCanonicalizing(['a', 'c']);
    } finally {
        tmpdir_remove_tree($root);
    }
});

