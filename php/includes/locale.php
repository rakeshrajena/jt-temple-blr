<?php
declare(strict_types=1);

function locale_dir(): string
{
    return APP_ROOT . '/locales';
}

function locale_path(string $code): string
{
    return locale_dir() . '/' . $code . '.json';
}

/** @return list<array{code: string, name: string, native: string}> */
function language_catalog(): array
{
    $path = locale_dir() . '/languages.json';
    if (!is_file($path)) {
        return [['code' => 'en', 'name' => 'English', 'native' => 'English']];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [['code' => 'en', 'name' => 'English', 'native' => 'English']];
    }
    $languages = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $code = strtolower(trim((string) ($row['code'] ?? '')));
        if (preg_match('/^[a-z]{2,8}$/', $code) !== 1) {
            continue;
        }
        $languages[] = [
            'code' => $code,
            'name' => trim((string) ($row['name'] ?? $code)),
            'native' => trim((string) ($row['native'] ?? $code)),
        ];
    }
    $hasEnglish = false;
    foreach ($languages as $language) {
        if ($language['code'] === 'en') {
            $hasEnglish = true;
        }
    }
    if (!$hasEnglish) {
        array_unshift($languages, ['code' => 'en', 'name' => 'English', 'native' => 'English']);
    }
    return $languages;
}

function language_exists(string $code): bool
{
    foreach (language_catalog() as $language) {
        if ($language['code'] === $code) {
            return true;
        }
    }
    return false;
}

/** @return array<string, string> */
function locale_phrases(string $code, bool $reload = false): array
{
    static $cache = [];
    if ($reload) {
        unset($cache[$code]);
    }
    if (isset($cache[$code])) {
        return $cache[$code];
    }
    if (preg_match('/^[a-z]{2,8}$/', $code) !== 1 || !is_file(locale_path($code))) {
        $cache[$code] = [];
        return $cache[$code];
    }
    $decoded = json_decode((string) file_get_contents(locale_path($code)), true);
    $phrases = [];
    if (is_array($decoded)) {
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value) && trim($value) !== '') {
                $phrases[$key] = $value;
            }
        }
    }
    $cache[$code] = $phrases;
    return $phrases;
}

function current_locale(): string
{
    $code = $_SESSION['locale'] ?? 'en';
    if (!is_string($code) || !language_exists($code)) {
        return 'en';
    }
    return $code;
}

function set_current_locale(string $code): bool
{
    $code = strtolower(trim($code));
    if (!language_exists($code)) {
        return false;
    }
    $_SESSION['locale'] = $code;
    return true;
}

function t(string $key, array $vars = []): string
{
    $locale = current_locale();
    $text = locale_phrases($locale)[$key] ?? '';
    if ($text === '' && $locale !== 'en') {
        $text = locale_phrases('en')[$key] ?? '';
    }
    if ($text === '') {
        $text = $key;
    }
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function t_fixed(string $group, string $value): string
{
    $slug = strtolower(str_replace([' ', '-'], '_', $value));
    $key = $group . '.' . $slug;
    $text = t($key);
    return $text === $key ? $value : $text;
}

/** @return array<string, string> */
function english_phrases(): array
{
    return locale_phrases('en');
}

/** @param array<string, string> $posted */
function save_locale_phrases(string $code, array $posted): ?string
{
    $code = strtolower(trim($code));
    if (!language_exists($code)) {
        return 'That language is not on the list.';
    }
    $english = english_phrases();
    $saved = [];
    foreach ($english as $key => $englishText) {
        if (!array_key_exists($key, $posted)) {
            $saved[$key] = $code === 'en' ? $englishText : (locale_phrases($code)[$key] ?? '');
            continue;
        }
        $value = trim($posted[$key]);
        if (mb_strlen($value) > 500) {
            return 'A phrase is too long.';
        }
        if (preg_match('/[<>]/', $value) === 1) {
            return 'A phrase cannot contain < or >.';
        }
        if ($code === 'en' && $value === '') {
            return 'English phrases cannot be left blank.';
        }
        if ($value !== '') {
            $saved[$key] = $value;
        }
    }
    if ($code === 'en') {
        foreach ($english as $key => $unused) {
            if (!isset($saved[$key]) || $saved[$key] === '') {
                return 'English phrases cannot be left blank.';
            }
        }
    }
    return write_locale_file($code, $saved);
}

function add_language(string $code, string $name, string $native): ?string
{
    $code = strtolower(trim($code));
    $name = trim($name);
    $native = trim($native);
    if (preg_match('/^[a-z]{2,8}$/', $code) !== 1) {
        return 'Use 2 to 8 letters for the language code.';
    }
    if ($code === 'en' || language_exists($code)) {
        return 'That language is already on the list.';
    }
    if ($name === '' || mb_strlen($name) > 40 || $native === '' || mb_strlen($native) > 40) {
        return 'Enter the language name and how it is written in that language.';
    }
    if (preg_match('/[<>]/', $name . $native) === 1) {
        return 'A language name cannot contain < or >.';
    }
    $languages = language_catalog();
    $languages[] = ['code' => $code, 'name' => $name, 'native' => $native];
    $error = write_language_catalog($languages);
    if ($error !== null) {
        return $error;
    }
    return write_locale_file($code, []);
}

function delete_language(string $code): ?string
{
    $code = strtolower(trim($code));
    if ($code === 'en') {
        return 'English stays on the list. It is the default language.';
    }
    if (!language_exists($code)) {
        return 'That language is not on the list.';
    }
    $languages = [];
    foreach (language_catalog() as $language) {
        if ($language['code'] !== $code) {
            $languages[] = $language;
        }
    }
    $error = write_language_catalog($languages);
    if ($error !== null) {
        return $error;
    }
    $path = locale_path($code);
    if (is_file($path) && !unlink($path)) {
        return 'The language file could not be removed.';
    }
    locale_phrases($code, true);
    if (($_SESSION['locale'] ?? '') === $code) {
        $_SESSION['locale'] = 'en';
    }
    return null;
}

/** @param list<array{code: string, name: string, native: string}> $languages */
function write_language_catalog(array $languages): ?string
{
    $json = json_encode(array_values($languages), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return 'The language list could not be saved.';
    }
    if (!is_dir(locale_dir()) && !mkdir(locale_dir(), 0775, true) && !is_dir(locale_dir())) {
        return 'The language list could not be saved.';
    }
    if (file_put_contents(locale_dir() . '/languages.json', $json . "\n", LOCK_EX) === false) {
        return 'The language list could not be saved.';
    }
    return null;
}

/** @param array<string, string> $phrases */
function write_locale_file(string $code, array $phrases): ?string
{
    if (preg_match('/^[a-z]{2,8}$/', $code) !== 1) {
        return 'That language code is not valid.';
    }
    ksort($phrases);
    $json = json_encode($phrases, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return 'The language file could not be saved.';
    }
    if (!is_dir(locale_dir()) && !mkdir(locale_dir(), 0775, true) && !is_dir(locale_dir())) {
        return 'The language file could not be saved.';
    }
    if (file_put_contents(locale_path($code), $json . "\n", LOCK_EX) === false) {
        return 'The language file could not be saved.';
    }
    locale_phrases($code, true);
    return null;
}
