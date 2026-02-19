<?php
/**
 * Simple PSR-4 Autoloader for IRIS SDK
 *
 * Use this when composer isn't available.
 * In production, use composer's autoloader instead.
 */

spl_autoload_register(function (string $class) {
    // Only handle IRIS\SDK namespace
    $prefix = 'IRIS\\SDK\\';
    $baseDir = __DIR__ . '/src/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Also autoload Guzzle if installed via composer
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require $vendorAutoload;
}
