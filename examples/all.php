<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$displayErrors = true;

echo "\033[1;33mExecuting all examples...\n";
// get current directory of this script
$dir = dirname(__DIR__).'/examples';
//echo "   (script directory: $dir)\n";

// get all files in the directory
$files = scandir($dir);
// remove . and .. from the list
$files = array_diff($files, array('.', '..'));
$errors = '';
$correct = 0;
$incorrect = 0;
$total = 0;
// loop through the files and select only directories
foreach ($files as $file) {
    if (is_dir($dir . '/' . $file)) {
        echo " \033[1;30m[.]\033[0m $file";
        // check if run.php exists in the directory
        if (file_exists($dir . '/' . $file . '/run.php')) {
            // execute run.php and print the output to CLI
            echo "\033[1;30m > running ...";
            $output = shell_exec('php ' . $dir . '/' . $file . '/run.php');
            // if $output contains "Fatal Error", print the error
            if (strpos($output, 'Fatal error') !== false) {
                $errors .= "[$file]\n".$output."\n\n";
                echo " > \033[1;31mERROR\033[0m\n";
                $incorrect++;
            } else {
                echo " > \033[1;32mOK\033[0m\n";
                $correct++;
            }
            $total++;
        }
    }
}
// print out errors, if any encountered
$correctPercent = round(($correct / $total) * 100, 0);
$incorrectPercent = round(($incorrect / $total) * 100, 0);
echo "\n\n";
echo "\033[1;33mRESULTS:\033[0m\n";
echo " [+] Correct runs ..... $correct ($correctPercent%)\n";
echo " [-] Incorrect runs ... $incorrect ($incorrectPercent%)\n";
echo " \033[1mTotal ................ $total (100%)\033[0m\n\n";
if ($displayErrors && !empty($errors)) {
    echo "\n\nERRORS:\n$errors\n";
}
