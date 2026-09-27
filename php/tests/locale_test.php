<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/includes/locale.php';

$failed = 0;

function check(bool $ok, string $name): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

$catalogPath = APP_ROOT . '/locales/languages.json';
$backup = (string) file_get_contents($catalogPath);
$extra = APP_ROOT . '/locales/zz.json';

try {
    $_SESSION = [];
    check(current_locale() === 'en', 'default language is English');
    check(t('nav.dashboard') === 'Dashboard', 'English dashboard label');
    check(set_current_locale('zz') === false, 'unknown language is refused');
    check(current_locale() === 'en', 'refused language leaves English in place');
    check(set_current_locale('hi') === true, 'Hindi can be selected');
    $hindi = t('nav.dashboard');
    check($hindi !== 'Dashboard' && $hindi !== 'nav.dashboard', 'Hindi dashboard label is translated');
    check(t('missing.key') === 'missing.key', 'a missing phrase shows its key');
    check(set_current_locale('or') === true, 'Odia can be selected');
    check(t('nav.dashboard') !== 'Dashboard', 'Odia dashboard label is translated');

    $added = add_language('zz', 'Test', 'Test');
    check($added === null && language_exists('zz') && is_file($extra), 'a new language file is created');
    check(add_language('zz', 'Test', 'Test') !== null, 'the same language cannot be added twice');
    check(add_language('en', 'English', 'English') !== null, 'English cannot be added again');
    check(add_language('../en', 'Bad', 'Bad') !== null, 'a language code cannot leave the locales folder');
    check(delete_language('en') !== null && language_exists('en'), 'English cannot be removed');

    $saved = save_locale_phrases('zz', ['nav.dashboard' => 'Hello', 'not.a.key' => 'nope', 'nav.overview' => '<b>']);
    check($saved !== null, 'a phrase cannot contain < or >');
    $saved = save_locale_phrases('zz', ['nav.dashboard' => 'Hello']);
    check($saved === null, 'a known phrase can be saved');
    $stored = locale_phrases('zz', true);
    check(($stored['nav.dashboard'] ?? '') === 'Hello' && !isset($stored['not.a.key']), 'unknown keys are not stored');
    $saved = save_locale_phrases('zz', []);
    check($saved === null && (locale_phrases('zz', true)['nav.dashboard'] ?? '') === 'Hello', 'a missing field keeps the saved phrase');
    set_current_locale('zz');
    check(t('nav.overview') === 'Overview', 'a blank new language falls back to English');
    check(delete_language('zz') === null && !is_file($extra) && !language_exists('zz'), 'a language file can be removed');
    check(($_SESSION['locale'] ?? '') === 'en', 'removing the current language returns to English');
} finally {
    if (is_file($extra)) {
        unlink($extra);
    }
    file_put_contents($catalogPath, $backup);
    locale_phrases('zz', true);
}

exit($failed === 0 ? 0 : 1);
