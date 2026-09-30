<?php

// For projects without Composer (and the WooCommerce plugin, which bundles this library).
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'InstantPay\\', 11) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 11)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
