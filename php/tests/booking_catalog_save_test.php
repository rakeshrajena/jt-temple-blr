<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$services = booking_catalog_services(false);
if ($services === []) {
    fwrite(STDERR, "No services\n");
    exit(1);
}
$id = (int) $services[0]['id'];
$before = booking_catalog_service($id);
$_POST = [
    'id' => (string) $id,
    'name' => $before['name'],
    'price' => '888.00',
    'description' => (string) ($before['description'] ?? ''),
    'requires_slot' => $before['requires_slot'] ? '1' : '',
    'is_active' => '1',
    'max_participants' => (string) $before['max_participants'],
    'sort_order' => (string) $before['sort_order'],
    'field_label' => [],
];
$r = booking_catalog_save_service_from_post('test');
if ($r['error'] !== null) {
    fwrite(STDERR, 'Save failed: ' . $r['error'] . "\n");
    exit(1);
}
$after = booking_catalog_service($id);
if ($after['price_paise'] !== 88800) {
    fwrite(STDERR, 'Expected 88800 got ' . $after['price_paise'] . "\n");
    exit(1);
}
// restore
$_POST['price'] = number_format($before['price_paise'] / 100, 2, '.', '');
booking_catalog_save_service_from_post('test');
echo "ok\n";
