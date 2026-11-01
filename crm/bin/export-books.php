<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

try {
    $count = write_books_snapshot(db());
    echo "Saved {$count} rows for the server install.\n";
} catch (Throwable $e) {
    error_log('[jt_blr] export books: ' . $e->getMessage());
    fwrite(STDERR, "Could not save the books copy.\n");
    exit(1);
}
