<?php
declare(strict_types=1);

function donation_render(string $title, string $view, array $vars = [], string $pageScripts = ''): void
{
    $vars['title'] = $title;
    $vars['flashes'] = $vars['flashes'] ?? [];
    extract($vars, EXTR_SKIP);
    ob_start();
    include DONATION_ROOT . '/views/' . $view . '.php';
    $content = (string) ob_get_clean();
    ob_start();
    include BOOKING_ROOT . '/app/Views/layout.php';
    $html = (string) ob_get_clean();
    if ($pageScripts !== '') {
        $html = str_replace('</body>', $pageScripts . "\n</body>", $html, $count);
        if ($count === 0) {
            $html .= $pageScripts;
        }
    }
    echo $html;
}
