<?php
declare(strict_types=1);

/** Full address, phone, email, website, GSTIN — edited with Identity; mirrored to Booking `settings`. */
const BRAND_ADDRESS_KEY = 'brand_address';
const BRAND_PHONE_KEY = 'brand_phone';
const BRAND_EMAIL_KEY = 'brand_email';
const BRAND_WEBSITE_KEY = 'brand_website';
const BRAND_GSTIN_KEY = 'brand_gstin';

function app_brand_contact(string $key): string
{
    $allowed = [BRAND_ADDRESS_KEY, BRAND_PHONE_KEY, BRAND_EMAIL_KEY, BRAND_WEBSITE_KEY, BRAND_GSTIN_KEY];
    if (!in_array($key, $allowed, true)) {
        return '';
    }
    $v = trim(brand_setting($key));
    if ($v !== '') {
        return $v;
    }
    if (!function_exists('booking_module_installed') || !booking_module_installed()) {
        return '';
    }
    $map = [
        BRAND_ADDRESS_KEY => 'temple_address',
        BRAND_PHONE_KEY => 'temple_phone',
        BRAND_EMAIL_KEY => 'temple_email',
        BRAND_WEBSITE_KEY => 'temple_website',
        BRAND_GSTIN_KEY => 'temple_gstin',
    ];

    return trim((string) booking_setting($map[$key], ''));
}

function app_temple_address(): string
{
    return app_brand_contact(BRAND_ADDRESS_KEY);
}

function app_temple_phone(): string
{
    return app_brand_contact(BRAND_PHONE_KEY);
}

function app_temple_email(): string
{
    return app_brand_contact(BRAND_EMAIL_KEY);
}

function app_temple_website(): string
{
    return app_brand_contact(BRAND_WEBSITE_KEY);
}

function app_temple_gstin(): string
{
    return app_brand_contact(BRAND_GSTIN_KEY);
}

/** @return array{address:string,phone:string,email:string,website:string,gstin:string} */
function temple_identity_contact_from_post(): array
{
    return [
        'address' => mb_substr(trim((string) ($_POST['app_address'] ?? '')), 0, 1000),
        'phone' => mb_substr(trim((string) ($_POST['app_phone'] ?? '')), 0, 200),
        'email' => mb_substr(trim((string) ($_POST['app_email'] ?? '')), 0, 200),
        'website' => mb_substr(trim((string) ($_POST['app_website'] ?? '')), 0, 200),
        'gstin' => mb_substr(trim((string) ($_POST['app_gstin'] ?? '')), 0, 200),
    ];
}

function temple_identity_contact_error(array $contact): ?string
{
    $email = $contact['email'];
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'Temple email is not valid.';
    }

    return null;
}

function temple_identity_persist_contact(array $contact): void
{
    brand_upsert(BRAND_ADDRESS_KEY, $contact['address']);
    brand_upsert(BRAND_PHONE_KEY, $contact['phone']);
    brand_upsert(BRAND_EMAIL_KEY, $contact['email']);
    brand_upsert(BRAND_WEBSITE_KEY, $contact['website']);
    brand_upsert(BRAND_GSTIN_KEY, $contact['gstin']);
}

/** Push Identity (name, place, contact) into Booking `settings` for invoices and devotee pages. */
function temple_identity_sync_to_booking_settings(string $actor): void
{
    if (!function_exists('booking_module_installed') || !booking_module_installed()) {
        return;
    }
    $pairs = [
        'temple_name' => app_display_name(),
        'temple_address' => app_temple_address(),
        'temple_phone' => app_temple_phone(),
        'temple_email' => app_temple_email(),
        'temple_website' => app_temple_website(),
        'temple_gstin' => app_temple_gstin(),
    ];
    foreach ($pairs as $key => $value) {
        booking_setting_set($key, $value, $actor);
    }
}

/** After Booking contact save — mirror into `app_settings` and keep tables aligned. */
function temple_identity_sync_brand_from_booking_contact(string $actor): void
{
    if (!function_exists('booking_module_installed') || !booking_module_installed()) {
        return;
    }
    $name = trim((string) booking_setting('temple_name', ''));
    if ($name !== '') {
        brand_upsert(BRAND_NAME_KEY, mb_substr($name, 0, 80));
    }
    brand_upsert(BRAND_ADDRESS_KEY, trim((string) booking_setting('temple_address', '')));
    brand_upsert(BRAND_PHONE_KEY, trim((string) booking_setting('temple_phone', '')));
    brand_upsert(BRAND_EMAIL_KEY, trim((string) booking_setting('temple_email', '')));
    brand_upsert(BRAND_WEBSITE_KEY, trim((string) booking_setting('temple_website', '')));
    brand_upsert(BRAND_GSTIN_KEY, trim((string) booking_setting('temple_gstin', '')));
    temple_identity_sync_to_booking_settings($actor);
}