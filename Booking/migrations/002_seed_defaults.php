<?php
// Default services, food coupons, time slots and settings.
// Everything here is editable from the admin dashboard. Service prices are
// PLACEHOLDERS: the dashboard shows a warning until an administrator confirms them.

use SJT\Core\Database;

return function (Database $db): void {
    $now = date('Y-m-d H:i:s');

    $f = fn (string $key, string $label, string $type = 'text', bool $required = false, array $options = []) =>
        ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required, 'options' => $options];

    $services = [
        [
            'code' => 'special-puja', 'name' => 'Special Puja', 'price' => 50100,
            'description' => 'Special puja offered to Lord Jagannatha on your behalf, with sankalpa in your family\'s name.',
            'approval' => 0, 'slot' => 1, 'participants' => 1, 'max_p' => 20, 'food' => 1,
            'fields' => [$f('sankalpa_names', 'Name(s) for sankalpa', 'text', true), $f('gotra', 'Gotra'), $f('occasion', 'Occasion (birthday, anniversary, etc.)')],
            'slots' => [['Morning (7:00 AM – 9:00 AM)', '07:00', 10], ['Evening (5:30 PM – 7:00 PM)', '17:30', 10]],
        ],
        [
            'code' => 'vehicle-puja', 'name' => 'Vehicle Puja', 'price' => 25100,
            'description' => 'Puja and blessing for a new or existing vehicle. Please bring the vehicle to the temple at the chosen time.',
            'approval' => 0, 'slot' => 1, 'participants' => 0, 'max_p' => 10, 'food' => 1,
            'fields' => [
                $f('vehicle_type', 'Vehicle type', 'select', true, ['Two-wheeler', 'Car', 'Commercial vehicle', 'Other']),
                $f('vehicle_number', 'Vehicle registration number', 'text', true),
            ],
            'slots' => [['Morning (8:00 AM – 10:00 AM)', '08:00', 15], ['Evening (4:00 PM – 6:00 PM)', '16:00', 15]],
        ],
        [
            'code' => 'shraddha-karma', 'name' => 'Shraddha Karma', 'price' => 110100,
            'description' => 'Shraddha rites for departed ancestors, performed by the temple priests. Subject to priest availability.',
            'approval' => 1, 'slot' => 1, 'participants' => 1, 'max_p' => 15, 'food' => 1,
            'fields' => [
                $f('departed_name', 'Name of the departed', 'text', true),
                $f('relationship', 'Your relationship to the departed'),
                $f('gotra', 'Gotra'),
                $f('tithi', 'Tithi / date of passing (if known)'),
            ],
            'slots' => [['Morning (7:00 AM – 10:00 AM)', '07:00', 3]],
        ],
        [
            'code' => 'brata-ghara', 'name' => 'Brata Ghara / Upanayana Ceremony', 'price' => 210100,
            'description' => 'Sacred thread ceremony (Brata Ghara / Upanayana). The temple office will contact you to confirm arrangements.',
            'approval' => 1, 'slot' => 1, 'participants' => 1, 'max_p' => 200, 'food' => 1,
            'fields' => [
                $f('batu_name', 'Name of the boy (batu)', 'text', true),
                $f('father_name', "Father's name", 'text', true),
                $f('gotra', 'Gotra'),
            ],
            'slots' => [['Morning (6:00 AM – 12:00 PM)', '06:00', 2], ['Afternoon (12:00 PM – 4:00 PM)', '12:00', 1]],
        ],
        [
            'code' => 'bhaghar-marriage', 'name' => 'Bhaghar / Marriage-related Ceremony', 'price' => 500100,
            'description' => 'Bhaghar, marriage and related ceremonies at the temple. The temple office will contact you to confirm arrangements.',
            'approval' => 1, 'slot' => 1, 'participants' => 1, 'max_p' => 300, 'food' => 1,
            'fields' => [
                $f('ceremony_type', 'Ceremony', 'select', true, ['Bhaghar', 'Marriage', 'Other marriage-related ceremony']),
                $f('bride_name', "Bride's name", 'text', true),
                $f('groom_name', "Groom's name", 'text', true),
            ],
            'slots' => [['Morning muhurta', '06:00', 1], ['Evening muhurta', '17:00', 1]],
        ],
        [
            'code' => 'other-services', 'name' => 'Other Temple Services', 'price' => 0,
            'description' => 'Any other service or offering. Describe what you need; the temple office will confirm the charges before you pay.',
            'approval' => 1, 'slot' => 1, 'participants' => 1, 'max_p' => 100, 'food' => 1,
            'fields' => [$f('service_request', 'Describe the service you need', 'textarea', true)],
            'slots' => [['Morning', '08:00', null], ['Afternoon', '12:00', null], ['Evening', '17:00', null]],
        ],
        [
            'code' => 'food-only', 'name' => 'Food-Only Booking (Prasadam Coupons)', 'price' => 0,
            'description' => 'Book food / prasadam coupons only, without any puja or ceremony.',
            'approval' => 0, 'slot' => 1, 'participants' => 0, 'max_p' => 0, 'food' => 1, 'food_only' => 1,
            'fields' => [],
            'slots' => [['Lunch prasadam (12:30 PM – 2:00 PM)', '12:30', null], ['Dinner prasadam (7:30 PM – 9:00 PM)', '19:30', null]],
        ],
    ];

    foreach ($services as $i => $s) {
        $id = $db->insert('services', [
            'code' => $s['code'], 'name' => $s['name'], 'description' => $s['description'],
            'price_paise' => $s['price'], 'requires_approval' => $s['approval'], 'requires_slot' => $s['slot'],
            'collect_participants' => $s['participants'], 'max_participants' => $s['max_p'],
            'allow_food' => $s['food'], 'is_food_only' => $s['food_only'] ?? 0,
            'is_combinable' => empty($s['food_only']) ? 1 : 0, 'daily_capacity' => null, 'lead_days' => null,
            'fields_json' => json_encode($s['fields'], JSON_UNESCAPED_UNICODE),
            'is_active' => 1, 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ($s['slots'] as $j => [$label, $start, $cap]) {
            $db->insert('time_slots', [
                'service_id' => $id, 'label' => $label, 'start_time' => $start, 'capacity' => $cap,
                'weekdays' => '1234567', 'is_active' => 1, 'sort_order' => $j,
            ]);
        }
    }

    // Prasadam menu matches bookingPreview.html (thalis + extra kheeri).
    foreach ([
        ['Niyamita Bhoga Thali', 'Anna, Daali, Mixed Veg, Khatta', 15100],
        ['Special Bhoga Thali', 'Anna, Daali, Mixed Veg, Khatta, ek Bhajji, Kheeri', 19100],
        ['Khichuri Bhoga Thali', 'Khichuri, Bhaja, Khatta', 15100],
        ['Extra Kheeri', 'Additional serving of Kheeri', 5000],
    ] as $i => [$n, $d, $p]) {
        $db->insert('booking_coupons', [
            'name' => $n, 'description' => $d, 'price_paise' => $p, 'max_per_booking' => 200,
            'is_active' => 1, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $settings = [
        'temple_name' => 'Shree Jagannatha Temple Bengaluru',
        'temple_address' => 'Bengaluru, Karnataka',
        'temple_phone' => '',
        'temple_email' => '',
        'temple_website' => 'https://sjtbengaluru.org',
        'temple_gstin' => '',
        'admin_whatsapp_numbers' => '',
        'booking_prefix' => 'SJT',
        'invoice_prefix' => 'SJTB',
        'booking_lead_days' => '1',
        'booking_max_days' => '90',
        'additional_charge_label' => '',
        'additional_charge_paise' => '0',
        'upi_vpa' => '',
        'upi_payee_name' => 'Shree Jagannatha Temple Bengaluru',
        'upi_qr_mode' => 'dynamic',
        'upi_include_tr' => '0',
        'upi_verified' => '0',
        'upi_verified_note' => '',
        'payment_instructions' => 'Scan the QR code with any UPI app (GPay, PhonePe, Paytm, BHIM, bank app). Pay the exact amount shown, then enter the 12-digit UPI reference number (UTR) below. Your booking is confirmed only after the temple verifies the payment.',
        'whatsapp_enabled' => '0',
        'whatsapp_language' => 'en',
        'wa_tpl_devotee_ack' => 'sjt_booking_received',
        'wa_tpl_devotee_update' => 'sjt_booking_update',
        'wa_tpl_admin_new' => 'sjt_admin_new_booking',
        'wa_tpl_admin_payment' => 'sjt_admin_payment_submitted',
        'terms_text' => 'Booking requests are confirmed only after payment verification and, where required, approval by the temple office. Please arrive 15 minutes before your time slot.',
        'prices_reviewed' => '0',
    ];
    foreach ($settings as $k => $v) {
        $db->insert('settings', ['skey' => $k, 'svalue' => $v, 'updated_at' => $now, 'updated_by' => 'system:seed']);
    }
};
