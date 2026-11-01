<?php
declare(strict_types=1);

/** Sibling ERP app folder on the server (crm/ preferred, php/ legacy). */
function booking_install_erp_root(): ?string
{
    $parent = dirname(__DIR__);
    foreach (['crm', 'php'] as $name) {
        $root = $parent . DIRECTORY_SEPARATOR . $name;
        if (is_file($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php')) {
            return $root;
        }
    }
    return null;
}
