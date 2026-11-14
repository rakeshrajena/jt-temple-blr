<?php
declare(strict_types=1);

/**
 * Redirect mistaken root install.php to CRM (production uses public_html/crm/install.php only).
 */
header('Location: /crm/install.php', true, 302);
exit;