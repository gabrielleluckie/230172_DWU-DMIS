<?php

declare(strict_types=1);

/**
 * Browser trigger for the expiry email checker.
 * Open: http://localhost/IS406_PartnershipRegistry/test_email.php
 */

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$isLocalHost = str_contains($host, 'localhost')
    || str_contains($host, '127.0.0.1')
    || in_array($remote, ['127.0.0.1', '::1'], true);

if (!$isLocalHost) {
    http_response_code(403);
    exit('This email test page can only be run on localhost.');
}

if (!defined('EXPIRY_NOTIFY_PRINT_RESULTS')) {
    define('EXPIRY_NOTIFY_PRINT_RESULTS', true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test — DWU Partnership Registry</title>
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f1f5f9; color: #0f172a; }
        .banner { background: #064e3b; color: #fff; padding: 1.5rem 1.25rem; }
        .banner h1 { margin: 0; font-size: 1.4rem; }
        .banner p { margin: 0.4rem 0 0; opacity: 0.9; }
        .wrap { max-width: 46rem; margin: 1.5rem auto; padding: 0 1rem 2rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem 1.4rem; }
        .expiry-msg { margin: 0 0 0.75rem; padding: 0.85rem 1rem; border-radius: 0.5rem; font-size: 0.95rem; line-height: 1.45; }
        .expiry-msg:last-child { margin-bottom: 0; }
        .expiry-msg-info { background: #f1f5f9; border: 1px solid #cbd5e1; }
        .expiry-msg-ok { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .expiry-msg-warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
        .expiry-msg-err { background: #fff1f2; border: 1px solid #fda4af; color: #9f1239; }
        code { background: #f1f5f9; padding: 0.1rem 0.35rem; border-radius: 0.25rem; }
    </style>
</head>
<body>
    <header class="banner">
        <h1>Expiry email test</h1>
        <p>Runs <code>includes/check_expiring_agreements.php</code> and prints SMTP results immediately.</p>
    </header>
    <main class="wrap">
        <section class="card">
            <?php require __DIR__ . '/includes/check_expiring_agreements.php'; ?>
        </section>
    </main>
</body>
</html>
