<?php

namespace Mobility\Util;

class Autoloader
{
    public static function register()
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload($class)
    {
        // Convert namespace to file path
        $prefix = 'Mobility\\';
        
        // Check if the class uses the namespace prefix
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // Not our namespace, let other autoloaders handle it
            return;
        }

        // Get the relative class name
        $relativeClass = substr($class, $len);

        // Replace namespace separators with directory separators
        $file = __DIR__ . '/../../src/' . str_replace('\\', '/', $relativeClass) . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
}