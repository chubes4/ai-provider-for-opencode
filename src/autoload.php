<?php

declare(strict_types=1);

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'Chubes4\\OpenCodeAiProvider\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));
        $path           = __DIR__ . '/' . str_replace('\\', '/', $relative_class) . '.php';

        if (is_readable($path)) {
            require_once $path;
        }
    }
);
