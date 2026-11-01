<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$legacy = legacy_selection_puja_purposes();
$names = online_booking_existing_offer_name_keys();
$missing = [];
foreach ($legacy as $row) {
    $key = mb_strtolower($row['name']);
    if (!isset($names[$key])) {
        $missing[] = $row['name'];
    }
}

echo 'legacy_rows=' . count($legacy) . "\n";
echo 'migration_flag=' . brand_setting(ONLINE_BOOKING_PUJA_MIGRATION_FLAG) . "\n";
echo 'services_table=' . (online_booking_services_table_exists() ? 'yes' : 'no') . "\n";
echo 'service_count=' . (online_booking_services_table_exists() ? count(booking_catalog_services(false)) : 0) . "\n";
echo 'missing_from_db=' . count($missing) . "\n";
if ($missing !== []) {
    echo implode("\n", array_slice($missing, 0, 10)) . "\n";
}

exit($missing === [] ? 0 : 1);
