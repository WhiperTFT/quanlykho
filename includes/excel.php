<?php
spl_autoload_register(function ($class) {

    $map = [
        'PhpOffice\\PhpSpreadsheet\\' => __DIR__ . '/../libs/phpspreadsheet/src/PhpSpreadsheet/',
        'Psr\\SimpleCache\\'          => __DIR__ . '/../libs/psr/simple-cache/src/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (strpos($class, $prefix) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
});
