<?php
define('ROOT', __DIR__);

require ROOT .'/vendor/autoload.php';

function dd($data = null) {
    var_dump($data);
    exit;
}

function env($group, $index = null) {
    return \App\Utils::env($group, $index);
}

function console($data) {
    echo '[ ' . date('Y-m-d H:i:s') . ' ]' . "\n";
    echo  "\t" . var_export($data) . "\n";
}