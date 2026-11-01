<?php
declare(strict_types=1);

/** Online booking services, slots, and blocked dates (shared DB with Booking app). */

const BOOKING_FIELD_TYPES = [
    'text' => 'Short text',
    'textarea' => 'Long text',
    'select' => 'Choice list',
    'number' => 'Number',
    'date' => 'Date',
];

function booking_catalog_can_manage(): bool
{
    return booking_settings_can_edit(current_user()['role'] ?? null);
}

function booking_fmt_date(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '—';
    }
    $t = strtotime($ymd);
    return $t ? date('D, j M Y', $t) : $ymd;
}

function booking_money_paise(int $paise): string
{
    return '₹' . number_format($paise / 100, 2);
}

/** @return list<array<string,mixed>> */
function booking_catalog_services(bool $activeOnly = false): array
{
    if (!booking_module_installed()) {
        return [];
    }
    $sql = 'SELECT * FROM services' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id';
    return array_map('booking_catalog_hydrate_service', db_all($sql));
}

function booking_catalog_service(int $id): ?array
{
    $r = db_one('SELECT * FROM services WHERE id = ?', [$id]);
    return $r ? booking_catalog_hydrate_service($r) : null;
}

/** @param array<string,mixed> $r */
function booking_catalog_hydrate_service(array $r): array
{
    $r['id'] = (int) $r['id'];
    foreach (['price_paise', 'max_participants', 'sort_order'] as $k) {
        $r[$k] = (int) $r[$k];
    }
    foreach (['requires_approval', 'requires_slot', 'collect_participants', 'allow_food', 'is_food_only', 'is_combinable', 'is_active'] as $k) {
        $r[$k] = (int) ($r[$k] ?? 0) === 1;
    }
    $r['daily_capacity'] = $r['daily_capacity'] === null ? null : (int) $r['daily_capacity'];
    $r['lead_days'] = $r['lead_days'] === null ? null : (int) $r['lead_days'];
    $r['fields'] = json_decode((string) ($r['fields_json'] ?? '[]'), true) ?: [];
    return $r;
}

/** @return list<array<string,mixed>> */
function booking_catalog_slots(?int $serviceId = null, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM time_slots WHERE 1=1';
    $p = [];
    if ($serviceId !== null) {
        $sql .= ' AND service_id = ?';
        $p[] = $serviceId;
    }
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $rows = db_all($sql . ' ORDER BY service_id, sort_order, start_time, id', $p);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['service_id'] = (int) $r['service_id'];
        $r['capacity'] = $r['capacity'] === null ? null : (int) $r['capacity'];
        $r['is_active'] = (int) $r['is_active'] === 1;
    }
    unset($r);
    return $rows;
}

function booking_catalog_slot(int $id): ?array
{
    $r = db_one('SELECT * FROM time_slots WHERE id = ?', [$id]);
    if (!$r) {
        return null;
    }
    $r['id'] = (int) $r['id'];
    $r['service_id'] = (int) $r['service_id'];
    $r['capacity'] = $r['capacity'] === null ? null : (int) $r['capacity'];
    $r['is_active'] = (int) $r['is_active'] === 1;
    return $r;
}

/** @return list<array<string,mixed>> */
function booking_catalog_blocked_dates(?string $from = null): array
{
    $sql = 'SELECT b.*, s.name AS service_name FROM blocked_dates b LEFT JOIN services s ON s.id = b.service_id';
    $p = [];
    if ($from !== null) {
        $sql .= ' WHERE b.block_date >= ?';
        $p[] = $from;
    }
    return db_all($sql . ' ORDER BY b.block_date, b.id', $p);
}

function booking_catalog_block_reason(int $serviceId, string $date): ?string
{
    $r = db_one(
        'SELECT reason FROM blocked_dates WHERE block_date = ? AND (service_id IS NULL OR service_id = ?) ORDER BY service_id IS NULL DESC LIMIT 1',
        [$date, $serviceId]
    );
    return $r ? ((string) ($r['reason'] ?: 'Not available on this date')) : null;
}

function booking_catalog_slot_used(int $slotId, string $date): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM booking_items i JOIN bookings b ON b.id = i.booking_id
         WHERE i.slot_id = ? AND b.service_date = ? AND b.booking_status NOT IN ('rejected','cancelled')",
        [$slotId, $date]
    );
}

function booking_catalog_service_used(int $serviceId, string $date): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM booking_items i JOIN bookings b ON b.id = i.booking_id
         WHERE i.item_type = 'service' AND i.service_id = ? AND b.service_date = ? AND b.booking_status NOT IN ('rejected','cancelled')",
        [$serviceId, $date]
    );
}

function booking_catalog_weekday(string $date): string
{
    return (string) date('N', strtotime($date));
}

/** @return list<array<string,mixed>> */
function booking_catalog_day_view(string $date): array
{
    $out = [];
    foreach (booking_catalog_services(true) as $s) {
        $slots = [];
        foreach (booking_catalog_slots($s['id']) as $sl) {
            $slots[] = $sl + [
                'used' => booking_catalog_slot_used($sl['id'], $date),
                'offered' => str_contains((string) $sl['weekdays'], booking_catalog_weekday($date)),
            ];
        }
        $out[] = [
            'service' => $s,
            'blocked' => booking_catalog_block_reason($s['id'], $date),
            'slots' => $slots,
            'used' => booking_catalog_service_used($s['id'], $date),
        ];
    }
    return $out;
}

function booking_catalog_prices_reviewed(): bool
{
    return (booking_setting('prices_reviewed', '0') ?? '0') === '1';
}

function booking_catalog_active_slot_count(int $serviceId): int
{
    return (int) db_value(
        'SELECT COUNT(*) FROM time_slots WHERE service_id = ? AND is_active = 1',
        [$serviceId]
    );
}

/** Same booking window as the public site (Settings › Online booking › Rules). */
function booking_catalog_public_min_date(?array $service = null): string
{
    $lead = null;
    if ($service !== null && $service['lead_days'] !== null) {
        $lead = max(0, (int) $service['lead_days']);
    }
    if ($lead === null) {
        $lead = max(0, (int) (booking_setting('booking_lead_days', '1') ?? 1));
    }
    return date('Y-m-d', strtotime(date('Y-m-d') . ' +' . $lead . ' days'));
}

function booking_catalog_public_max_date(): string
{
    $max = max(1, min(730, (int) (booking_setting('booking_max_days', '90') ?? 90)));
    return date('Y-m-d', strtotime(date('Y-m-d') . ' +' . $max . ' days'));
}

function booking_catalog_parse_daily_capacity(mixed $raw): ?int
{
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    $n = (int) $s;
    return $n < 1 ? null : min(50000, $n);
}

function booking_catalog_parse_lead_days(mixed $raw): ?int
{
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    return max(0, min(730, (int) $s));
}

function booking_catalog_parse_slot_capacity(mixed $raw): ?int
{
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    $n = (int) $s;
    return $n < 1 ? null : min(50000, $n);
}

/** @return array{error:?string, id?:int} */
function booking_catalog_save_service_from_post(string $actor): array
{
    if (!booking_module_installed()) {
        return ['error' => 'Online booking is not installed on this database.'];
    }
    $id = (int) ($_POST['id'] ?? 0);
    $old = $id > 0 ? booking_catalog_service($id) : null;
    if ($id > 0 && !$old) {
        return ['error' => 'Service not found.'];
    }
    $errors = [];
    $name = trim(post_string('name', 150));
    if (mb_strlen($name) < 3) {
        $errors[] = 'Enter a service name.';
    }
    $foodOnly = $old ? $old['is_food_only'] : false;
    $priceRaw = (string) ($_POST['price'] ?? '');
    if ($priceRaw === '' && $old && !$foodOnly) {
        $price = (int) $old['price_paise'];
    } else {
        $price = booking_parse_rupees($priceRaw === '' ? '0' : $priceRaw);
        if ($price === null) {
            $errors[] = 'Enter a valid price in rupees (0 if charges are confirmed later).';
        }
    }
    $code = $old['code'] ?? trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    if (!$old && db_value('SELECT 1 FROM services WHERE code = ?', [$code])) {
        $code .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    }
    $fields = [];
    $labels = $_POST['field_label'] ?? [];
    $types = $_POST['field_type'] ?? [];
    $reqs = $_POST['field_required'] ?? [];
    $opts = $_POST['field_options'] ?? [];
    $keys = $_POST['field_key'] ?? [];
    if (!is_array($labels)) {
        $labels = [];
    }
    foreach ($labels as $i => $label) {
        $label = is_string($label) ? trim(mb_substr($label, 0, 80)) : '';
        if ($label === '') {
            continue;
        }
        $type = is_string($types[$i] ?? null) && isset(BOOKING_FIELD_TYPES[$types[$i]]) ? $types[$i] : 'text';
        $keyRaw = is_string($keys[$i] ?? null) ? $keys[$i] : '';
        $key = preg_match('/^[a-z0-9_]{1,40}$/', $keyRaw) ? $keyRaw : trim(substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), 0, 40), '_');
        $optStr = is_string($opts[$i] ?? null) ? $opts[$i] : '';
        $options = $type === 'select' ? array_values(array_filter(array_map(static fn ($o) => trim(mb_substr($o, 0, 80)), explode(',', $optStr)))) : [];
        if ($type === 'select' && count($options) < 2) {
            $errors[] = "Choice list \"$label\" needs at least two comma-separated options.";
        }
        $fields[] = [
            'key' => $key ?: 'field_' . $i,
            'label' => $label,
            'type' => $type,
            'required' => !empty($reqs[$i]),
            'options' => $options,
        ];
    }
    if (count(array_unique(array_column($fields, 'key'))) !== count($fields)) {
        $errors[] = 'Two extra fields have the same name. Please make the labels different.';
    }
    $now = date('Y-m-d H:i:s');
    $row = [
        'name' => $name,
        'description' => trim(post_string('description', 500)) ?: null,
        'price_paise' => $foodOnly ? 0 : (int) $price,
        'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
        'requires_slot' => isset($_POST['requires_slot']) ? 1 : 0,
        'collect_participants' => isset($_POST['collect_participants']) ? 1 : 0,
        'max_participants' => max(1, min(5000, (int) ($_POST['max_participants'] ?? 50))),
        'allow_food' => $foodOnly ? 1 : (isset($_POST['allow_food']) ? 1 : 0),
        'is_combinable' => $foodOnly ? 0 : (isset($_POST['is_combinable']) ? 1 : 0),
        'daily_capacity' => booking_catalog_parse_daily_capacity($_POST['daily_capacity'] ?? ''),
        'lead_days' => booking_catalog_parse_lead_days($_POST['lead_days'] ?? ''),
        'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'sort_order' => (int) ($_POST['sort_order'] ?? 100),
        'updated_at' => $now,
    ];
    if ($errors) {
        return ['error' => implode(' ', $errors)];
    }
    $serviceIdForSlots = $id > 0 ? $id : 0;
    if ($row['requires_slot'] && $row['is_active'] && $serviceIdForSlots > 0 && booking_catalog_active_slot_count($serviceIdForSlots) === 0) {
        return ['error' => 'Add at least one active time slot below, or turn off “Requires time slot” for date-only booking.'];
    }
    if ($old) {
        db_exec(
            'UPDATE services SET name = ?, description = ?, price_paise = ?, requires_approval = ?, requires_slot = ?,
             collect_participants = ?, max_participants = ?, allow_food = ?, is_combinable = ?, daily_capacity = ?, lead_days = ?,
             fields_json = ?, is_active = ?, sort_order = ?, updated_at = ? WHERE id = ?',
            [
                $row['name'], $row['description'], $row['price_paise'], $row['requires_approval'], $row['requires_slot'],
                $row['collect_participants'], $row['max_participants'], $row['allow_food'], $row['is_combinable'],
                $row['daily_capacity'], $row['lead_days'], $row['fields_json'], $row['is_active'], $row['sort_order'],
                $row['updated_at'], $id,
            ]
        );
    } else {
        db_exec(
            'INSERT INTO services (code, name, description, price_paise, requires_approval, requires_slot, collect_participants,
             max_participants, allow_food, is_food_only, is_combinable, daily_capacity, lead_days, fields_json, is_active, sort_order, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $code, $row['name'], $row['description'], $row['price_paise'], $row['requires_approval'], $row['requires_slot'],
                $row['collect_participants'], $row['max_participants'], $row['allow_food'], 0, $row['is_combinable'],
                $row['daily_capacity'], $row['lead_days'], $row['fields_json'], $row['is_active'], $row['sort_order'], $now, $now,
            ]
        );
        $id = (int) db()->lastInsertId();
    }
    return ['error' => null, 'id' => $id];
}

/** @return array{error:?string} */
function booking_catalog_save_slot_from_post(): array
{
    $sid = (int) ($_POST['service_id'] ?? 0);
    $svc = booking_catalog_service($sid);
    if (!$svc) {
        return ['error' => 'Service not found.'];
    }
    $id = (int) ($_POST['id'] ?? 0);
    $label = trim(post_string('label', 100));
    $start = (string) ($_POST['start_time'] ?? '');
    $start = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) ? $start : null;
    $weekdays = $_POST['weekdays'] ?? [];
    $days = implode('', array_filter(['1', '2', '3', '4', '5', '6', '7'], static fn ($d) => is_array($weekdays) && in_array($d, array_map('strval', $weekdays), true)));
    if ($label === '' || !$start || $days === '') {
        return ['error' => 'A time slot needs a label, a start time and at least one weekday.'];
    }
    $cap = booking_catalog_parse_slot_capacity($_POST['capacity'] ?? '');
    if ($cap !== null && $cap < 1) {
        return ['error' => 'Slot capacity must be at least 1, or leave blank for no limit.'];
    }
    $row = [
        'label' => $label,
        'start_time' => $start,
        'capacity' => $cap,
        'weekdays' => $days,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ];
    if ($id > 0) {
        $old = booking_catalog_slot($id);
        if (!$old || $old['service_id'] !== $sid) {
            return ['error' => 'Time slot not found.'];
        }
        db_exec(
            'UPDATE time_slots SET label = ?, start_time = ?, capacity = ?, weekdays = ?, is_active = ?, sort_order = ? WHERE id = ?',
            [$row['label'], $row['start_time'], $row['capacity'], $row['weekdays'], $row['is_active'], $row['sort_order'], $id]
        );
    } else {
        db_exec(
            'INSERT INTO time_slots (service_id, label, start_time, capacity, weekdays, is_active, sort_order) VALUES (?,?,?,?,?,?,?)',
            [$sid, $row['label'], $row['start_time'], $row['capacity'], $row['weekdays'], $row['is_active'], $row['sort_order']]
        );
    }
    if (!empty($svc['requires_slot']) && !empty($svc['is_active']) && booking_catalog_active_slot_count($sid) === 0) {
        return ['error' => 'This service requires a time slot but none are active. Add a slot or turn off “Requires time slot”.'];
    }
    return ['error' => null];
}

/** @return array{error:?string, count?:int} */
function booking_catalog_block_from_post(string $actor): array
{
    $from = post_string('from', 12);
    $to = trim(post_string('to', 12)) ?: $from;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) {
        return ['error' => 'Choose a valid date range (up to one year).'];
    }
    if ((strtotime($to) - strtotime($from)) > 366 * 86400) {
        return ['error' => 'Choose a valid date range (up to one year).'];
    }
    $sid = (int) ($_POST['service_id'] ?? 0);
    $sid = $sid > 0 ? $sid : null;
    if ($sid && !booking_catalog_service($sid)) {
        return ['error' => 'Service not found.'];
    }
    $reason = trim(post_string('reason', 255));
    $now = date('Y-m-d H:i:s');
    $n = 0;
    for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
        db_exec(
            'INSERT INTO blocked_dates (service_id, block_date, reason, created_at, created_by) VALUES (?,?,?,?,?)',
            [$sid, $d, $reason !== '' ? $reason : null, $now, $actor]
        );
        $n++;
    }
    return ['error' => null, 'count' => $n];
}

/** @return array{error:?string} */
function booking_catalog_unblock(int $id): array
{
    $row = db_one('SELECT id FROM blocked_dates WHERE id = ?', [$id]);
    if (!$row) {
        return ['error' => 'Closed date not found.'];
    }
    db_exec('DELETE FROM blocked_dates WHERE id = ?', [$id]);
    return ['error' => null];
}

function booking_catalog_confirm_prices(string $actor): void
{
    booking_setting_set('prices_reviewed', '1', $actor);
}

function booking_catalog_settings_service_url(int $id = 0): string
{
    return url('settings/booking-service', $id > 0 ? ['id' => $id] : []);
}

/** @return never */
function settings_process_booking_post(string $bookingActor): void
{
    $form = is_string($_POST['form'] ?? null) ? trim($_POST['form']) : '';
    if ($form === '' || !str_starts_with($form, 'booking_')) {
        http_response_code(400);
        echo 'Missing booking form.';
        exit;
    }
    if (!booking_module_installed()) {
        flash('error', 'Online booking is not installed on this database.');
        redirect(url('settings'));
    }
    if (!booking_settings_can_edit(current_user()['role'])) {
        flash('error', t('shell.admin_only'));
        redirect(url('settings') . '#online-booking');
    }
    if ($form === 'booking_prices_confirm') {
        booking_catalog_confirm_prices($bookingActor);
        flash('success', t('settings.ob_prices_confirmed'));
        redirect(url('settings') . '#ob-services');
    }
    if ($form === 'booking_unblock_date') {
        $result = booking_catalog_unblock((int) ($_POST['block_id'] ?? 0));
        flash($result['error'] !== null ? 'error' : 'success', $result['error'] ?? t('settings.ob_reopened'));
        redirect(url('settings') . '#ob-availability');
    }
    try {
        $result = match ($form) {
            'booking_contact' => booking_settings_save_contact($_POST, $bookingActor),
            'booking_rules' => booking_settings_save_rules($_POST, $bookingActor),
            'booking_whatsapp' => booking_settings_save_whatsapp($_POST, $bookingActor),
            'booking_prasadam' => food_coupon_catalog_can_edit((string) (current_user()['role'] ?? ''))
                ? food_coupon_catalog_save_from_post()
                : ['error' => t('shell.admin_only')],
            'booking_service_save' => booking_catalog_save_service_from_post($bookingActor),
            'booking_slot_save' => booking_catalog_save_slot_from_post(),
            'booking_block_dates' => booking_catalog_block_from_post($bookingActor),
            default => ['error' => 'Unknown booking settings form.'],
        };
    } catch (Throwable $e) {
        error_log('[jt_blr] booking settings: ' . $e->getMessage());
        flash('error', 'Could not save. Please try again.');
        redirect(url('settings') . '#online-booking');
    }
    if ($form === 'booking_block_dates' && ($result['error'] ?? null) === null) {
        $n = (int) ($result['count'] ?? 0);
        flash('success', t('settings.ob_blocked_ok', ['n' => (string) $n]));
        redirect(url('settings') . '#ob-availability');
    }
    $serviceId = (int) ($_POST['id'] ?? $_POST['service_id'] ?? 0);
    if ($form === 'booking_service_save') {
        if (($result['error'] ?? null) === null) {
            flash('success', t('settings.ob_service_saved'));
            redirect(booking_catalog_settings_service_url((int) ($result['id'] ?? $serviceId)));
        }
        flash('error', $result['error']);
        redirect(booking_catalog_settings_service_url($serviceId > 0 ? $serviceId : 0));
    }
    if ($form === 'booking_slot_save') {
        $sid = (int) ($_POST['service_id'] ?? 0);
        if (($result['error'] ?? null) === null) {
            flash('success', t('settings.ob_slot_saved'));
            redirect(booking_catalog_settings_service_url($sid) . '#slots');
        }
        flash('error', $result['error']);
        redirect(booking_catalog_settings_service_url($sid) . '#slots');
    }
    flash($result['error'] !== null ? 'error' : 'success', $result['error'] ?? (
        $form === 'booking_prasadam' ? t('settings.ob_prasadam_saved') : t('settings.ob_saved')
    ));
    $anchor = match ($form) {
        'booking_contact' => '#ob-contact',
        'booking_rules' => '#ob-rules',
        'booking_whatsapp' => '#ob-whatsapp',
        'booking_prasadam' => '#ob-prasadam',
        'booking_block_dates' => '#ob-availability',
        default => '#online-booking',
    };
    redirect(url('settings') . $anchor);
}
