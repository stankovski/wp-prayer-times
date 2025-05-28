<?php
/**
 * Islamic Network PSR-4 Autoloader
 * 
 * This autoloader handles the loading of classes in the IslamicNetwork namespace.
 * It follows the PSR-4 specification for autoloading.
 */

spl_autoload_register(function ($class) {
    // Base directory for the namespace prefix
    $baseDir = __DIR__ . '/';
    
    // Namespace prefixes to directory bases
    $prefixes = [
        'IslamicNetwork\\Calendar\\' => $baseDir . 'Calendar/',
        'IslamicNetwork\\PrayerTimes\\' => $baseDir . 'PrayerTimes/',
        'IslamicNetwork\\MoonSighting\\' => $baseDir . 'MoonSighting/',
    ];
    
    // Go through each namespace prefix
    foreach ($prefixes as $prefix => $dir) {
        // Check if the class uses the namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // No match, move to the next prefix
            continue;
        }
        
        // Get the relative class name
        $relativeClass = substr($class, $len);
        
        // Replace namespace separators with directory separators
        // and append .php
        $file = $dir . str_replace('\\', '/', $relativeClass) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }
    
    return false;
});