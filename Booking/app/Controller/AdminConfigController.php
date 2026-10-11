<?php
declare(strict_types=1);

namespace SJT\Controller;

use SJT\Core\App;
use SJT\Core\Container;
use SJT\Core\HttpException;
use SJT\Core\Request;
use SJT\Core\Response;
use SJT\Service\Auth;
use SJT\Service\Validator;

/** Services, prices, coupons, slots, blocked dates, settings and users. */
final class AdminConfigController extends Controller
{
    private const FIELD_TYPES = ['text' => 'Short text', 'textarea' => 'Long text', 'select' => 'Choice list', 'number' => 'Number', 'date' => 'Date'];

    // ---------------------------------------------------------------- availability (blocked dates)

    public function availability(Request $req): Response
    {
        $date = Validator::date($req->str('date')) ?? today();
        return $this->adminView('availability', [
            'title' => 'Availability',
            'blocked' => Container::catalog()->blockedDates(today()),
            'services' => Container::catalog()->services(false),
            'date' => $date,
            'day' => $this->dayView($date),
        ]);
    }

    /** Slot usage for one date across all services. */
    private function dayView(string $date): array
    {
        $out = [];
        $av = Container::availability();
        foreach (Container::catalog()->services() as $s) {
            $slots = [];
            foreach (Container::catalog()->slots($s['id']) as $sl) {
                $slots[] = $sl + ['used' => $av->slotUsed($sl['id'], $date), 'offered' => str_contains($sl['weekdays'], $av->weekday($date))];
            }
            $out[] = ['service' => $s, 'blocked' => Container::catalog()->blockReason($s['id'], $date), 'slots' => $slots, 'used' => $av->serviceUsed($s['id'], $date)];
        }
        return $out;
    }

    public function block(Request $req): Response
    {
        $from = Validator::date($req->str('from'));
        $to = Validator::date($req->str('to')) ?? $from;
        $sid = $req->int('service_id') ?: null;
        $reason = Validator::text($req->str('reason'), 255);
        if (!$from || !$to || $to < $from || (strtotime($to) - strtotime($from)) > 366 * 86400) {
            flash('error', 'Choose a valid date range (up to one year).');
            return Response::redirect('/admin/availability');
        }
        if ($sid && !Container::catalog()->service($sid)) {
            throw new HttpException(400);
        }
        $n = 0;
        for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            App::db()->insert('blocked_dates', ['service_id' => $sid, 'block_date' => $d, 'reason' => $reason ?: null, 'created_at' => now(), 'created_by' => Container::auth()->actor()['name']]);
            $n++;
        }
        $existing = (int) App::db()->value(
            "SELECT COUNT(DISTINCT b.id) FROM bookings b JOIN booking_items i ON i.booking_id = b.id WHERE b.service_date BETWEEN ? AND ? AND b.booking_status IN ('pending','confirmed')" . ($sid ? ' AND i.service_id = ' . (int) $sid : ''),
            [$from, $to]
        );
        Container::audit()->log(Container::auth()->actor(), 'availability', null, null, 'availability.blocked', ['from' => $from, 'to' => $to, 'service_id' => $sid, 'reason' => $reason]);
        flash('success', "$n date(s) blocked for new bookings." . ($existing ? " Note: $existing existing booking(s) fall on these dates — reschedule or cancel them from the Bookings page if needed." : ''));
        return Response::redirect('/admin/availability');
    }

    public function unblock(Request $req): Response
    {
        $id = $req->int('id');
        $row = App::db()->one('SELECT * FROM blocked_dates WHERE id = ?', [$id]);
        if ($row) {
            App::db()->run('DELETE FROM blocked_dates WHERE id = ?', [$id]);
            Container::audit()->log(Container::auth()->actor(), 'availability', $id, null, 'availability.unblocked', $row);
            flash('success', 'Date reopened for bookings.');
        }
        return Response::redirect('/admin/availability');
    }

    // ---------------------------------------------------------------- services & slots

    public function services(Request $req): Response
    {
        $slots = [];
        foreach (Container::catalog()->slots(null, false) as $s) {
            $slots[$s['service_id']][] = $s;
        }
        return $this->adminView('services', [
            'title' => 'Services & prices',
            'services' => Container::catalog()->services(false),
            'slots' => $slots,
            'pricesReviewed' => App::settings()->bool('prices_reviewed'),
        ]);
    }

    public function serviceForm(Request $req): Response
    {
        $id = (int) $req->param('id');
        $s = $id ? Container::catalog()->service($id) : null;
        if ($id && !$s) {
            throw new HttpException(404);
        }
        return $this->adminView('service_form', [
            'title' => $s ? 'Edit ' . $s['name'] : 'New service',
            's' => $s ?? [
                'id' => 0, 'code' => '', 'name' => '', 'description' => '', 'price_paise' => 0, 'requires_approval' => false,
                'requires_slot' => true, 'collect_participants' => false, 'max_participants' => 50, 'allow_food' => true,
                'is_food_only' => false, 'is_combinable' => true, 'daily_capacity' => null, 'lead_days' => null,
                'fields' => [], 'is_active' => true, 'sort_order' => 100,
            ],
            'slots' => $s ? Container::catalog()->slots($s['id'], false) : [],
            'types' => self::FIELD_TYPES,
        ]);
    }

    public function serviceSave(Request $req): Response
    {
        $id = $req->int('id');
        $old = $id ? Container::catalog()->service($id) : null;
        if ($id && !$old) {
            throw new HttpException(404);
        }
        $errors = [];
        $name = Validator::text($req->str('name'), 150);
        if (mb_strlen($name) < 3) {
            $errors[] = 'Enter a service name.';
        }
        $price = parse_rupees($req->str('price') === '' ? '0' : $req->str('price'));
        if ($price === null) {
            $errors[] = 'Enter a valid price in rupees (0 if charges are confirmed later).';
        }
        $code = $old['code'] ?? trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if (!$old && App::db()->value('SELECT 1 FROM services WHERE code = ?', [$code])) {
            $code .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        $fields = [];
        $labels = $req->arr('field_label');
        $types = $req->arr('field_type');
        $reqs = $req->arr('field_required');
        $opts = $req->arr('field_options');
        $keys = $req->arr('field_key');
        foreach ($labels as $i => $label) {
            $label = is_string($label) ? Validator::text($label, 80) : '';
            if ($label === '') {
                continue;
            }
            $type = is_string($types[$i] ?? null) && isset(self::FIELD_TYPES[$types[$i]]) ? $types[$i] : 'text';
            $key = is_string($keys[$i] ?? null) && preg_match('/^[a-z0-9_]{1,40}$/', $keys[$i]) ? $keys[$i] : trim(substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), 0, 40), '_');
            $options = $type === 'select' ? array_values(array_filter(array_map(fn ($o) => Validator::text($o, 80), explode(',', is_string($opts[$i] ?? null) ? $opts[$i] : '')))) : [];
            if ($type === 'select' && count($options) < 2) {
                $errors[] = "Choice list \"$label\" needs at least two comma-separated options.";
            }
            $fields[] = ['key' => $key ?: 'field_' . $i, 'label' => $label, 'type' => $type, 'required' => !empty($reqs[$i]), 'options' => $options];
        }
        if (count(array_unique(array_column($fields, 'key'))) !== count($fields)) {
            $errors[] = 'Two extra fields have the same name. Please make the labels different.';
        }
        $foodOnly = $old ? $old['is_food_only'] : false;
        $row = [
            'name' => $name,
            'description' => Validator::text($req->str('description'), 500, true) ?: null,
            'price_paise' => $foodOnly ? 0 : (int) $price,
            'requires_approval' => $req->str('requires_approval') === '1' ? 1 : 0,
            'requires_slot' => $req->str('requires_slot') === '1' ? 1 : 0,
            'collect_participants' => $req->str('collect_participants') === '1' ? 1 : 0,
            'max_participants' => max(1, min(5000, $req->int('max_participants', 50))),
            'allow_food' => $foodOnly ? 1 : ($req->str('allow_food') === '1' ? 1 : 0),
            'is_combinable' => $foodOnly ? 0 : ($req->str('is_combinable') === '1' ? 1 : 0),
            'daily_capacity' => $req->str('daily_capacity') === '' ? null : max(0, $req->int('daily_capacity')),
            'lead_days' => $req->str('lead_days') === '' ? null : max(0, min(365, $req->int('lead_days'))),
            'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'is_active' => $req->str('is_active') === '1' ? 1 : 0,
            'sort_order' => $req->int('sort_order', 100),
            'updated_at' => now(),
        ];
        if ($errors) {
            flash('error', implode(' ', $errors));
            return Response::redirect($id ? '/admin/services/' . $id : '/admin/services/new');
        }
        if ($old) {
            App::db()->update('services', $row, 'id = :id', ['id' => $id]);
            $changes = [];
            foreach ($row as $k => $v) {
                $before = $k === 'fields_json' ? $old['fields_json'] : (is_bool($old[$k] ?? null) ? (int) $old[$k] : ($old[$k] ?? null));
                if ($k !== 'updated_at' && (string) $before !== (string) $v) {
                    $changes[$k] = ['from' => $before, 'to' => $v];
                }
            }
            Container::audit()->log(Container::auth()->actor(), 'service', $id, null, 'service.updated', ['name' => $name, 'changes' => $changes]);
        } else {
            $id = App::db()->insert('services', $row + ['code' => $code, 'is_food_only' => 0, 'created_at' => now()]);
            Container::audit()->log(Container::auth()->actor(), 'service', $id, null, 'service.created', ['name' => $name, 'price' => $row['price_paise']]);
        }
        flash('success', 'Service saved. Price changes apply to new bookings only; existing bookings keep the price they were booked at.');
        return Response::redirect('/admin/services/' . $id);
    }

    public function slotSave(Request $req): Response
    {
        $sid = $req->int('service_id');
        $svc = Container::catalog()->service($sid);
        if (!$svc) {
            throw new HttpException(404);
        }
        $id = $req->int('id');
        $label = Validator::text($req->str('label'), 100);
        $start = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $req->str('start_time')) ? $req->str('start_time') : null;
        $days = implode('', array_filter(['1', '2', '3', '4', '5', '6', '7'], fn ($d) => in_array($d, array_map('strval', $req->arr('weekdays')), true)));
        if ($label === '' || !$start || $days === '') {
            flash('error', 'A time slot needs a label, a start time and at least one weekday.');
            return Response::redirect('/admin/services/' . $sid . '#slots');
        }
        $row = [
            'label' => $label, 'start_time' => $start,
            'capacity' => $req->str('capacity') === '' ? null : max(0, $req->int('capacity')),
            'weekdays' => $days, 'is_active' => $req->str('is_active') === '1' ? 1 : 0,
            'sort_order' => $req->int('sort_order'),
        ];
        if ($id) {
            $old = Container::catalog()->slot($id);
            if (!$old || $old['service_id'] !== $sid) {
                throw new HttpException(404);
            }
            App::db()->update('time_slots', $row, 'id = :id', ['id' => $id]);
            Container::audit()->log(Container::auth()->actor(), 'slot', $id, null, 'slot.updated', ['service' => $svc['name'], 'from' => $old, 'to' => $row]);
        } else {
            $id = App::db()->insert('time_slots', $row + ['service_id' => $sid]);
            Container::audit()->log(Container::auth()->actor(), 'slot', $id, null, 'slot.created', ['service' => $svc['name']] + $row);
        }
        flash('success', 'Time slot saved.');
        return Response::redirect('/admin/services/' . $sid . '#slots');
    }

    public function confirmPrices(Request $req): Response
    {
        App::settings()->set('prices_reviewed', '1', Container::auth()->actor()['name']);
        Container::audit()->log(Container::auth()->actor(), 'settings', 'prices_reviewed', null, 'prices.confirmed', []);
        flash('success', 'Thank you. Prices are marked as reviewed.');
        return Response::redirect('/admin/services');
    }

    // ---------------------------------------------------------------- food coupons

    public function coupons(Request $req): Response
    {
        return $this->adminView('coupons', ['title' => 'Food coupons', 'coupons' => Container::catalog()->coupons(false)]);
    }

    public function couponSave(Request $req): Response
    {
        $id = $req->int('id');
        $name = Validator::text($req->str('name'), 100);
        $price = parse_rupees($req->str('price'));
        if (mb_strlen($name) < 3 || $price === null || $price <= 0) {
            flash('error', 'A coupon needs a name and a price greater than ₹0.');
            return Response::redirect('/admin/coupons');
        }
        $row = [
            'name' => $name, 'description' => Validator::text($req->str('description'), 255) ?: null, 'price_paise' => $price,
            'max_per_booking' => max(1, min(5000, $req->int('max_per_booking', 200))),
            'is_active' => $req->str('is_active') === '1' ? 1 : 0, 'sort_order' => $req->int('sort_order'), 'updated_at' => now(),
        ];
        if ($id) {
            $old = Container::catalog()->coupon($id);
            if (!$old) {
                throw new HttpException(404);
            }
            App::db()->update('booking_coupons', $row, 'id = :id', ['id' => $id]);
            Container::audit()->log(Container::auth()->actor(), 'coupon', $id, null, 'coupon.updated', ['from' => array_intersect_key($old, $row), 'to' => $row]);
        } else {
            $id = App::db()->insert('booking_coupons', $row + ['created_at' => now()]);
            Container::audit()->log(Container::auth()->actor(), 'coupon', $id, null, 'coupon.created', $row);
        }
        flash('success', 'Food coupon saved. New prices apply to new bookings only.');
        return Response::redirect('/admin/coupons');
    }

    // ---------------------------------------------------------------- settings

    private const SETTING_KEYS = [
        'temple' => ['temple_name', 'temple_address', 'temple_phone', 'temple_email', 'temple_website', 'temple_gstin', 'terms_text'],
        'booking' => ['booking_lead_days', 'booking_max_days', 'additional_charge_label', 'additional_charge_paise', 'booking_prefix', 'invoice_prefix'],
        'payment' => ['upi_vpa', 'upi_payee_name', 'upi_qr_mode', 'upi_include_tr', 'upi_verified', 'upi_verified_note', 'payment_instructions'],
        'whatsapp' => ['whatsapp_enabled', 'admin_whatsapp_numbers', 'whatsapp_language', 'wa_tpl_devotee_ack', 'wa_tpl_devotee_update', 'wa_tpl_admin_new', 'wa_tpl_admin_payment'],
    ];

    public function settings(Request $req): Response
    {
        return $this->adminView('settings', [
            'title' => 'Settings',
            's' => App::settings()->all(),
            'waConfigured' => Container::whatsapp()->configured(),
            'staticQr' => is_file(\SJT\Service\UpiPayment::STATIC_QR_FILE),
            'webhookUrl' => absolute_url('/webhooks/whatsapp'),
        ]);
    }

    public function settingsSave(Request $req): Response
    {
        $section = $req->str('section');
        if (!isset(self::SETTING_KEYS[$section])) {
            throw new HttpException(400);
        }
        $settings = App::settings();
        $actor = Container::auth()->actor();
        $errors = [];
        $new = [];
        foreach (self::SETTING_KEYS[$section] as $k) {
            $v = $req->str($k);
            switch ($k) {
                case 'upi_include_tr':
                case 'upi_verified':
                case 'whatsapp_enabled':
                    $v = $v === '1' ? '1' : '0';
                    break;
                case 'booking_lead_days':
                case 'booking_max_days':
                    $v = (string) max($k === 'booking_max_days' ? 1 : 0, min(730, (int) $v));
                    break;
                case 'additional_charge_paise':
                    $p = parse_rupees($req->str('additional_charge') === '' ? '0' : $req->str('additional_charge'));
                    if ($p === null) {
                        $errors[] = 'Additional charge must be a rupee amount.';
                        continue 2;
                    }
                    $v = (string) $p;
                    break;
                case 'booking_prefix':
                case 'invoice_prefix':
                    $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v));
                    if ($v === '' || strlen($v) > 8) {
                        $errors[] = 'Booking and invoice prefixes must be 1–8 letters/digits.';
                        continue 2;
                    }
                    break;
                case 'upi_vpa':
                    $v = strtolower($v);
                    if ($v !== '' && !Container::upi()->validVpa($v)) {
                        $errors[] = 'The UPI ID looks invalid (expected something like templename@bank).';
                        continue 2;
                    }
                    break;
                case 'upi_qr_mode':
                    $v = $v === 'static' ? 'static' : 'dynamic';
                    break;
                case 'temple_email':
                    if ($v !== '' && !Validator::email($v)) {
                        $errors[] = 'Temple email is not valid.';
                        continue 2;
                    }
                    break;
                case 'admin_whatsapp_numbers':
                    $nums = [];
                    foreach (preg_split('/[,\n]+/', $v) as $n) {
                        if (trim($n) === '') {
                            continue;
                        }
                        $d = Validator::normaliseMobile($n);
                        if (!$d) {
                            $errors[] = "\"" . trim($n) . "\" is not a valid mobile number.";
                            continue 3;
                        }
                        $nums[] = '+' . $d;
                    }
                    $v = implode(', ', array_unique($nums));
                    break;
                case 'whatsapp_language':
                    $v = preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $v) ? $v : 'en';
                    break;
                case 'wa_tpl_devotee_ack':
                case 'wa_tpl_devotee_update':
                case 'wa_tpl_admin_new':
                case 'wa_tpl_admin_payment':
                    $v = preg_replace('/[^a-z0-9_]/', '', strtolower($v));
                    break;
                default:
                    $v = Validator::text($v, in_array($k, ['terms_text', 'payment_instructions', 'temple_address'], true) ? 1000 : 200, true);
            }
            $new[$k] = $v;
        }
        // Changing the UPI ID or QR mode requires re-verification.
        if ($section === 'payment' && (($new['upi_vpa'] ?? '') !== (string) $settings->get('upi_vpa') || ($new['upi_qr_mode'] ?? '') !== $settings->get('upi_qr_mode', 'dynamic'))
            && ($new['upi_verified'] ?? '0') === '1' && $req->str('reverify') !== '1') {
            $new['upi_verified'] = '0';
            $errors[] = 'Payment details changed: please make a ₹1 test payment to the new UPI ID/QR, confirm it reached the temple account, then tick "verified" again.';
        }
        if ($errors && $section !== 'payment') {
            flash('error', implode(' ', $errors));
            return Response::redirect('/admin/settings#' . $section);
        }
        $changes = [];
        foreach ($new as $k => $v) {
            if ((string) $settings->get($k, '') !== $v) {
                $changes[$k] = ['from' => $settings->get($k, ''), 'to' => $v];
                $settings->set($k, $v, $actor['name']);
            }
        }
        if ($changes) {
            Container::audit()->log($actor, 'settings', $section, null, 'settings.updated', $changes);
        }
        flash($errors ? 'error' : 'success', $errors ? implode(' ', $errors) : 'Settings saved.');
        return Response::redirect('/admin/settings#' . $section);
    }

    public function qrUpload(Request $req): Response
    {
        $err = Container::upi()->storeStaticQr($req->files['qr'] ?? []);
        if ($err) {
            flash('error', $err);
        } else {
            App::settings()->set('upi_verified', '0', Container::auth()->actor()['name']);
            Container::audit()->log(Container::auth()->actor(), 'settings', 'upi_static_qr', null, 'settings.qr_uploaded', []);
            flash('success', 'QR image uploaded. Test it with a ₹1 payment, confirm the money reached the temple account, then tick "verified".');
        }
        return Response::redirect('/admin/settings#payment');
    }

    public function qrPreview(Request $req): Response
    {
        $upi = Container::upi();
        if ($upi->mode() === 'static') {
            if (!is_file(\SJT\Service\UpiPayment::STATIC_QR_FILE)) {
                throw new HttpException(404);
            }
            return Response::file((string) file_get_contents(\SJT\Service\UpiPayment::STATIC_QR_FILE), 'image/png', 'qr.png', true);
        }
        $uri = $upi->uri(['booking_no' => 'TEST-' . date('His'), 'total_paise' => 100]);
        if (!$uri) {
            throw new HttpException(404, 'Set a UPI ID first.');
        }
        return Response::file($upi->qrPng($uri), 'image/png', 'test-qr.png', true);
    }

    public function whatsappTest(Request $req): Response
    {
        $to = Validator::normaliseMobile($req->str('to'));
        if (!$to) {
            flash('error', 'Enter a valid mobile number for the test message.');
            return Response::redirect('/admin/settings#whatsapp');
        }
        $tpl = (string) App::settings()->get('wa_tpl_devotee_update');
        $res = Container::whatsapp()->sendTemplate($to, $tpl, App::settings()->get('whatsapp_language', 'en'), [
            'Test', 'TEST-0000', 'This is a test message from the temple booking system.', 'Test', 'Test', absolute_url('/'), (string) App::settings()->get('temple_name'),
        ]);
        Container::audit()->log(Container::auth()->actor(), 'settings', 'whatsapp', null, 'whatsapp.test', ['to' => $to, 'ok' => $res['ok'], 'error' => $res['error']]);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'WhatsApp accepted the test message (id ' . $res['id'] . '). Check the phone to confirm delivery.' : 'Test failed: ' . $res['error']);
        return Response::redirect('/admin/settings#whatsapp');
    }

    // ---------------------------------------------------------------- users

    public function users(Request $req): Response
    {
        return $this->adminView('users', ['title' => 'Administrators', 'users' => App::db()->all('SELECT id, username, name, role, is_active, last_login_at, locked_until FROM admins ORDER BY username')]);
    }

    public function userSave(Request $req): Response
    {
        $me = Container::auth()->user();
        $id = $req->int('id');
        $role = $req->str('role') === 'super' ? 'super' : 'staff';
        $active = $req->str('is_active') === '1' ? 1 : 0;
        $name = Validator::text($req->str('name'), 100);
        $pw = (string) ($req->post['password'] ?? '');
        if ($id) {
            $u = App::db()->one('SELECT * FROM admins WHERE id = ?', [$id]);
            if (!$u) {
                throw new HttpException(404);
            }
            if ($id === $me['id'] && ($role !== 'super' || !$active)) {
                flash('error', 'You cannot remove your own senior role or deactivate yourself.');
                return Response::redirect('/admin/users');
            }
            $row = ['name' => $name ?: $u['name'], 'role' => $role, 'is_active' => $active, 'updated_at' => now()];
            if ($pw !== '') {
                if ($p = Auth::passwordProblem($pw)) {
                    flash('error', $p);
                    return Response::redirect('/admin/users');
                }
                $row += ['password_hash' => Auth::hash($pw), 'failed_logins' => 0, 'locked_until' => null];
            }
            App::db()->update('admins', $row, 'id = :id', ['id' => $id]);
            Container::audit()->log(Container::auth()->actor(), 'admin', $u['username'], null, 'admin.updated', ['role' => $role, 'active' => $active, 'password_changed' => $pw !== '']);
        } else {
            $username = strtolower($req->str('username'));
            if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username) || App::db()->value('SELECT 1 FROM admins WHERE username = ?', [$username])) {
                flash('error', 'Choose a unique username of 3–50 letters, digits, dots, dashes or underscores.');
                return Response::redirect('/admin/users');
            }
            if ($p = Auth::passwordProblem($pw)) {
                flash('error', $p);
                return Response::redirect('/admin/users');
            }
            App::db()->insert('admins', ['username' => $username, 'name' => $name ?: $username, 'password_hash' => Auth::hash($pw), 'role' => $role, 'is_active' => 1, 'failed_logins' => 0, 'created_at' => now(), 'updated_at' => now()]);
            Container::audit()->log(Container::auth()->actor(), 'admin', $username, null, 'admin.created', ['role' => $role]);
        }
        flash('success', 'Administrator saved.');
        return Response::redirect('/admin/users');
    }
}
