<?php
declare(strict_types=1);

/**
 * Splits help text into one line per sentence.
 *
 * @return list<string>
 */
function help_lines(string $text): array
{
    $flat = trim((string) preg_replace('/\s+/u', ' ', $text));
    if ($flat === '') {
        return [];
    }
    $parts = preg_split('/(?<=[.!?।])\s+/u', $flat) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn (string $line): bool => $line !== ''));
}

/** Renders a "?" button that shows the help text line by line on hover, focus, or tap. */
function help_tip(string $text): string
{
    static $next = 0;
    $lines = help_lines($text);
    if ($lines === []) {
        return '';
    }
    $id = 'help-' . ++$next;
    $body = '';
    foreach ($lines as $line) {
        $body .= '<span class="help-tip-line">' . e($line) . '</span>';
    }
    return '<span class="help-tip">'
        . '<button type="button" class="help-tip-btn" aria-label="' . e(t('ui.help')) . '" aria-expanded="false" aria-controls="' . $id . '">?</button>'
        . '<span class="help-tip-body" id="' . $id . '" role="tooltip" hidden>' . $body . '</span>'
        . '</span>';
}
