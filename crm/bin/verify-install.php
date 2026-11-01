<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

try {
    $differences = verify_fresh_install(db(), books_snapshot_path());
} catch (Throwable $e) {
    error_log('[jt_blr] verify install: ' . $e->getMessage());
    fwrite(STDERR, "Could not check the install: {$e->getMessage()}\n");
    exit(1);
}

if ($differences === []) {
    echo "A fresh install from schema.sql and the books copy matches the live books.\n";
    exit(0);
}
foreach ($differences as $line) {
    fwrite(STDERR, $line . "\n");
}
fwrite(STDERR, "Run bin/export-books.php to refresh the books copy, then check again.\n");
exit(1);
