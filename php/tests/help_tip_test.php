<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

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

check(help_lines('First line. Second line? Third!') === ['First line.', 'Second line?', 'Third!'], 'each sentence becomes its own line');
check(help_lines('Use of ₹251.00 or less is immediate. Above that it waits.') === ['Use of ₹251.00 or less is immediate.', 'Above that it waits.'], 'a decimal amount is not split');
check(help_lines("  Spaced   out.\n\nNext line.  ") === ['Spaced out.', 'Next line.'], 'extra spaces and blank lines are removed');
check(help_lines('पहला वाक्य। दूसरा वाक्य।') === ['पहला वाक्य।', 'दूसरा वाक्य।'], 'a Hindi or Odia full stop ends a line');
check(help_lines('   ') === [], 'blank help has no lines');

$html = help_tip('Keep <b>this</b> safe. Second.');
check(str_contains($html, '&lt;b&gt;this&lt;/b&gt;') && !str_contains($html, '<b>'), 'help text is escaped');
check(substr_count($html, 'class="help-tip-line"') === 2, 'the popup has one line per sentence');
check(str_contains($html, 'type="button"') && str_contains($html, 'aria-expanded="false"') && str_contains($html, 'role="tooltip"'), 'the icon is a button tied to a tooltip');
check(preg_match('/aria-controls="(help-\d+)"/', $html, $m) === 1 && str_contains($html, 'id="' . $m[1] . '"'), 'the button points at its own popup');
check(preg_match('/id="(help-\d+)"/', help_tip('One.'), $a) === 1 && preg_match('/id="(help-\d+)"/', help_tip('Two.'), $b) === 1 && $a[1] !== $b[1], 'every popup on a page has its own id');
check(help_tip('  ') === '', 'blank help shows no icon');

echo $failed === 0 ? "passed\n" : "failed {$failed}\n";
exit($failed === 0 ? 0 : 1);
