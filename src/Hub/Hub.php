<?php

namespace Cognesy\Instructor\Hub;

class Hub {
    public string $baseDir = '';

    public function __construct() {
        $this->baseDir = $this->getBaseDir();
    }

    public function getBaseDir() : string {
        // get current directory of this script
        return dirname(__DIR__).'/../examples';
    }

    public function getExampleDirectories() : array {
        // get all files in the directory
        $files = scandir($this->baseDir);
        // remove . and .. from the list
        $files = array_diff($files, array('.', '..'));
        foreach ($files as $key => $file) {
            // check if the file is a directory
            if (!is_dir($this->baseDir . '/' . $file)) {
                unset($files[$key]);
            }
        }
        return $files;
    }

    public function exampleExists(string $file) : bool {
        return file_exists($this->baseDir . '/' . $file . '/run.php');
    }
}
