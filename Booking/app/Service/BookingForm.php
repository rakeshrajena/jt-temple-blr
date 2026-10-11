<?php
declare(strict_types=1);

namespace SJT\Service;

use SJT\Core\ValidationException;
use SJT\Repo\Catalog;
use SJT\Repo\Settings;

/**
 * Validates raw booking-form input on the server and prices it.
 * This is the single place where booking totals are calculated; the browser
 * only shows an estimate, and the server recalculates at review and at submit.
 *
 * Input keys:
 *   services[]            selected service ids
 *   name, mobile, email, service_date, instructions
 *   slot[sid], participants[sid], f[sid][fieldKey]
 *   add_food=1, food_cat[cid]=1, food[cid]=qty
 */
final class BookingForm
{
    public function __construct(private Catalog $catalog, private Availability $availability, private Settings $settings)
    {
    }

    /** @return array draft: devotee, date, services[], food[], quote */
    public function validate(array $in): array
    {
        // Normalise shapes first: anything not of the expected type is treated as empty.
        foreach (['name', 'mobile', 'email', 'service_date', 'instructions', 'add_food'] as $k) {
            $in[$k] = is_string($in[$k] ?? null) ? $in[$k] : '';
        }
        foreach (['services', 'slot', 'participants', 'f', 'food_cat', 'food'] as $k) {
            $in[$k] = is_array($in[$k] ?? null) ? $in[$k] : [];
        }
        $err = [];
        $all = [];
        foreach ($this->catalog->services() as $s) {
            $all[$s['id']] = $s;
        }

        // ---- Services
        $ids = array_values(array_unique(array_map('intval', array_filter((array) ($in['services'] ?? []), 'is_scalar'))));
        $selected = [];
        foreach ($ids as $id) {
            if (!isset($all[$id])) {
                $err['services'] = 'One of the selected services is no longer available. Please review your selection.';
                continue;
            }
            $selected[$id] = $all[$id];
        }
        if (!$selected && !isset($err['services'])) {
            $err['services'] = 'Please choose at least one service.';
        }
        $foodOnly = (bool) array_filter($selected, fn ($s) => $s['is_food_only']);
        if (count($selected) > 1) {
            if ($foodOnly) {
                $err['services'] = 'Food-Only Booking cannot be combined with other services. To add food to a puja or ceremony, untick Food-Only and use "Add Food / Prasadam Coupons" instead.';
            } elseif ($single = array_filter($selected, fn ($s) => !$s['is_combinable'])) {
                $err['services'] = reset($single)['name'] . ' must be booked on its own. Please make a separate booking for other services.';
            }
        }

        // ---- Devotee
        $name = Validator::name((string) ($in['name'] ?? ''));
        if (!$name) {
            $err['name'] = 'Please enter your full name (letters only, 2–100 characters).';
        }
        $mobile = Validator::normaliseMobile((string) ($in['mobile'] ?? ''));
        if (!$mobile) {
            $err['mobile'] = 'Please enter a valid 10-digit mobile number (or +country code for numbers outside India).';
        }
        $emailRaw = trim((string) ($in['email'] ?? ''));
        $email = null;
        if ($emailRaw !== '') {
            $email = Validator::email($emailRaw);
            if (!$email) {
                $err['email'] = 'Please enter a valid email address, or leave it empty.';
            }
        }
        $instructions = Validator::text((string) ($in['instructions'] ?? ''), 1000, true);

        // ---- Date
        $date = Validator::date((string) ($in['service_date'] ?? ''));
        if (!$date) {
            $err['service_date'] = 'Please choose a date.';
        }

        // ---- Per-service details
        $lines = [];
        if ($date && !isset($err['services'])) {
            foreach ($selected as $sid => $svc) {
                if ($problem = $this->availability->serviceProblem($svc, $date)) {
                    $err['service_date'] = $problem;
                    continue;
                }
                $line = ['service' => $svc, 'slot' => null, 'participants' => null, 'details' => []];
                if ($svc['requires_slot']) {
                    $slotRaw = $in['slot'][$sid] ?? 0;
                    $slotId = is_scalar($slotRaw) ? (int) $slotRaw : 0;
                    $slot = $slotId ? $this->catalog->slot($slotId) : null;
                    if (!$slot) {
                        $err["slot.$sid"] = 'Please choose a time for ' . $svc['name'] . '.';
                    } elseif ($p = $this->availability->slotProblem($svc, $slot, $date)) {
                        $err["slot.$sid"] = $p;
                    } else {
                        $line['slot'] = $slot;
                    }
                }
                if ($svc['collect_participants']) {
                    $raw = is_scalar($in['participants'][$sid] ?? null) ? trim((string) $in['participants'][$sid]) : '';
                    if (!preg_match('/^\d{1,4}$/', $raw) || (int) $raw < 1 || (int) $raw > $svc['max_participants']) {
                        $err["participants.$sid"] = 'Number of participants for ' . $svc['name'] . ' must be between 1 and ' . $svc['max_participants'] . '.';
                    } else {
                        $line['participants'] = (int) $raw;
                    }
                }
                $fieldsIn = is_array($in['f'][$sid] ?? null) ? $in['f'][$sid] : [];
                foreach ($svc['fields'] as $fd) {
                    $key = $fd['key'];
                    $raw = $fieldsIn[$key] ?? '';
                    $raw = is_string($raw) ? $raw : '';
                    $val = Validator::text($raw, $fd['type'] === 'textarea' ? 1000 : 150, $fd['type'] === 'textarea');
                    $ek = "f.$sid.$key";
                    if ($val === '') {
                        if (!empty($fd['required'])) {
                            $err[$ek] = $fd['label'] . ' is required for ' . $svc['name'] . '.';
                        }
                        continue;
                    }
                    if ($fd['type'] === 'select' && !in_array($val, $fd['options'] ?? [], true)) {
                        $err[$ek] = 'Please choose a valid option for ' . $fd['label'] . '.';
                        continue;
                    }
                    if ($fd['type'] === 'number' && !preg_match('/^\d{1,6}$/', $val)) {
                        $err[$ek] = $fd['label'] . ' must be a number.';
                        continue;
                    }
                    if ($fd['type'] === 'date' && !Validator::date($val)) {
                        $err[$ek] = $fd['label'] . ' must be a valid date.';
                        continue;
                    }
                    $line['details'][] = ['key' => $key, 'label' => $fd['label'], 'value' => $val];
                }
                $lines[] = $line;
            }
        }

        // ---- Food coupons
        $food = [];
        $foodAllowed = (bool) array_filter($selected, fn ($s) => $s['allow_food']);
        $wantsFood = $foodOnly || !empty($in['add_food']);
        if ($wantsFood && $selected && !$foodAllowed) {
            $err['food'] = 'Food coupons are not available with the selected service.';
        } elseif ($wantsFood) {
            $cats = (array) ($in['food_cat'] ?? []);
            $qtys = (array) ($in['food'] ?? []);
            foreach ($this->catalog->coupons() as $c) {
                $cid = $c['id'];
                $raw = is_scalar($qtys[$cid] ?? null) ? trim((string) $qtys[$cid]) : '';
                $ticked = !empty($cats[$cid]) || ($raw !== '' && $raw !== '0');
                if (!$ticked) {
                    continue;
                }
                if (!preg_match('/^\d{1,4}$/', $raw) || (int) $raw < 1) {
                    $err["food.$cid"] = 'Please enter the number of persons for ' . $c['name'] . '.';
                } elseif ((int) $raw > $c['max_per_booking']) {
                    $err["food.$cid"] = 'Maximum ' . $c['max_per_booking'] . ' persons for ' . $c['name'] . ' in one booking. Please contact the temple office for larger groups.';
                } else {
                    $food[] = ['coupon' => $c, 'qty' => (int) $raw];
                }
            }
            if (!$food && !array_filter(array_keys($err), fn ($k) => str_starts_with($k, 'food.'))) {
                $err['food'] = $foodOnly
                    ? 'Please choose at least one food coupon category and enter the number of persons.'
                    : 'Please choose a food coupon category and enter the number of persons, or untick "Add Food / Prasadam Coupons".';
            }
        }

        if ($err) {
            throw new ValidationException($err);
        }

        $draft = [
            'booking_type' => $foodOnly ? 'food_only' : 'service',
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email,
            'service_date' => $date,
            'instructions' => $instructions,
            'services' => $lines,
            'food' => $food,
        ];
        $draft['quote'] = $this->quote($draft);
        return $draft;
    }

    /** Price a validated draft. Returns item lines and totals in paise. */
    public function quote(array $draft): array
    {
        $items = [];
        foreach ($draft['services'] as $l) {
            $svc = $l['service'];
            if ($svc['is_food_only']) {
                continue; // food-only is priced by its coupons
            }
            $items[] = [
                'item_type' => 'service', 'service_id' => $svc['id'], 'coupon_id' => null,
                'slot_id' => $l['slot']['id'] ?? null, 'slot_label' => $l['slot']['label'] ?? null,
                'name' => $svc['name'], 'unit_price_paise' => $svc['price_paise'], 'quantity' => 1,
                'amount_paise' => $svc['price_paise'], 'participants' => $l['participants'], 'details' => $l['details'],
            ];
        }
        // Food-only bookings still record the chosen slot (collection time) on a zero-value line.
        foreach ($draft['services'] as $l) {
            if ($l['service']['is_food_only']) {
                $items[] = [
                    'item_type' => 'service', 'service_id' => $l['service']['id'], 'coupon_id' => null,
                    'slot_id' => $l['slot']['id'] ?? null, 'slot_label' => $l['slot']['label'] ?? null,
                    'name' => $l['service']['name'], 'unit_price_paise' => 0, 'quantity' => 1,
                    'amount_paise' => 0, 'participants' => null, 'details' => [],
                ];
            }
        }
        foreach ($draft['food'] as $f) {
            $c = $f['coupon'];
            $items[] = [
                'item_type' => 'food', 'service_id' => null, 'coupon_id' => $c['id'],
                'slot_id' => null, 'slot_label' => null,
                'name' => $c['name'], 'unit_price_paise' => $c['price_paise'], 'quantity' => $f['qty'],
                'amount_paise' => $c['price_paise'] * $f['qty'], 'participants' => null, 'details' => [],
            ];
        }
        $services = array_sum(array_map(fn ($i) => $i['item_type'] === 'service' ? $i['amount_paise'] : 0, $items));
        $foodTotal = array_sum(array_map(fn ($i) => $i['item_type'] === 'food' ? $i['amount_paise'] : 0, $items));
        $subtotal = $services + $foodTotal;
        $extra = max(0, $this->settings->int('additional_charge_paise', 0));
        $extraLabel = $extra > 0 ? ($this->settings->get('additional_charge_label') ?: 'Additional charges') : null;
        return [
            'items' => $items,
            'services_paise' => $services,
            'food_paise' => $foodTotal,
            'subtotal_paise' => $subtotal,
            'additional_label' => $extraLabel,
            'additional_paise' => $extra,
            'total_paise' => $subtotal + $extra,
        ];
    }
}
