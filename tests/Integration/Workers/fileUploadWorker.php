<?php

declare(strict_types=1);

echo "POST data:\n";
foreach ($_POST as $key => $value) {
    echo "KEY: {$key}\n";
    echo "VALUE: {$value}\n\n";
}

echo "Uploaded files:\n";
foreach ($_FILES as $key => $data) {
    echo "KEY: {$key}\n";
    echo 'FILENAME: ' . $data['name'] . "\n";
    echo 'SIZE: ' . $data['size'] . "\n";

    $targetPath = sys_get_temp_dir() . '/' . $data['name'];
    if (move_uploaded_file($data['tmp_name'], $targetPath)) {
        echo "Moved to {$targetPath}\n\n";
    }
}
