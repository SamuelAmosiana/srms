<?php
/**
 * Simplified DOMPDF Autoloader for LSC SRMS
 */

// Define the DOMPDF namespace mapping
spl_autoload_register(function ($class) {
    // Map DOMPDF classes to their file locations
    $prefix = 'Dompdf\\';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) !== 0) {
        // Not a Dompdf class, return to try other autoloaders
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});