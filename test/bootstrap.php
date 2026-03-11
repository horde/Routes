<?php
/**
 * Bootstrap for PHPUnit tests
 *
 * Load composer autoloader
 */

// Find composer autoloader
$candidates = [
    dirname(__FILE__, 2) . '/vendor/autoload.php',  // When running in package root
    dirname(__FILE__, 4) . '/autoload.php',          // When installed as dependency
];

foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        return;
    }
}

throw new RuntimeException('Could not find composer autoloader');
