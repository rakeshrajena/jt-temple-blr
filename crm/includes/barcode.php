<?php
declare(strict_types=1);

/**
 * Code 128, set B. Each pattern is bar/space widths. Stop (106) is one module longer.
 *
 * @return list<string>
 */
function code128_patterns(): array
{
    return [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];
}

function code128_expand(string $widths): string
{
    $bars = '';
    $draw = true;
    $length = strlen($widths);
    for ($i = 0; $i < $length; $i++) {
        $bars .= str_repeat($draw ? '1' : '0', (int) $widths[$i]);
        $draw = !$draw;
    }
    return $bars;
}

/** A module string of bars (1) and spaces (0), with a quiet zone on each side. */
function code128_modules(string $text): string
{
    if ($text === '' || preg_match('/^[\x20-\x7E]+$/', $text) !== 1) {
        return '';
    }
    $patterns = code128_patterns();
    $sum = 104;
    $values = [];
    $weight = 1;
    $length = strlen($text);
    for ($i = 0; $i < $length; $i++) {
        $value = ord($text[$i]) - 32;
        $values[] = $value;
        $sum += $weight * $value;
        $weight++;
    }
    $modules = str_repeat('0', 10) . code128_expand($patterns[104]);
    foreach ($values as $value) {
        $modules .= code128_expand($patterns[$value]);
    }
    $modules .= code128_expand($patterns[$sum % 103]);
    $modules .= code128_expand($patterns[106]);
    return $modules . str_repeat('0', 10);
}
