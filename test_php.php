<?php
echo "PHP is working fine.\n";
file_put_contents('test_file.txt', 'test content');
if (file_exists('test_file.txt')) {
    echo "File creation is working fine.\n";
    unlink('test_file.txt');
} else {
    echo "File creation FAILED.\n";
}
