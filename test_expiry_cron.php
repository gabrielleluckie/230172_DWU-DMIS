<?php

declare(strict_types=1);

/**
 * Manual browser trigger for the 30-day expiry email job.
 * Open: http://localhost/IS406_PartnershipRegistry/test_expiry_cron.php
 */

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$isLocalHost = str_contains($host, 'localhost')
    || str_contains($host, '127.0.0.1')
    || in_array($remote, ['127.0.0.1', '::1'], true);

if (!$isLocalHost) {
    http_response_code(403);
    exit('This expiry test page can only be run on localhost.');
}

if (!defined('EXPIRY_NOTIFY_PRINT_RESULTS')) {
    define('EXPIRY_NOTIFY_PRINT_RESULTS', false);
}

require_once __DIR__ . '/includes/check_expiring_agreements.php';

/** @var array<string, mixed> $expiryNotificationResult */
$result = $expiryNotificationResult ?? [
    'ok'               => false,
    'message'          => 'Expiry checker did not return a result.',
    'target_date'      => '',
    'found'            => 0,
    'emails_attempted' => 0,
    'emails_sent'      => 0,
    'via'              => '',
    'agreements'       => [],
    'errors'           => ['Missing $expiryNotificationResult.'],
];

$ok = (bool) ($result['ok'] ?? false);
$agreements = is_array($result['agreements'] ?? null) ? $result['agreements'] : [];
$errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
$banner = $ok ? '#064e3b' : '#9f1239';
$statusWord = $ok ? 'Success' : 'Failure';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiry Cron Test — DWU Partnership Registry</title>
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f1f5f9; color: #0f172a; }
        .banner { background: <?= h($banner) ?>; color: #fff; padding: 1.5rem 1.25rem; }
        .banner p { margin: 0.35rem 0 0; opacity: 0.9; }
        .wrap { max-width: 52rem; margin: 1.5rem auto; padding: 0 1rem 2rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.6rem 0.5rem; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        th { font-size: 0.72rem; letter-spacing: 0.06em; text-transform: uppercase; color: #064e3b; }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 0.75rem; }
        .meta div { background: #ecfdf5; border-radius: 0.5rem; padding: 0.75rem; }
        .meta strong { display: block; font-size: 0.72rem; letter-spacing: 0.06em; text-transform: uppercase; color: #064e3b; margin-bottom: 0.25rem; }
        .errors { color: #9f1239; }
        code { background: #f1f5f9; padding: 0.1rem 0.35rem; border-radius: 0.25rem; }
    </style>
</head>
<body>
    <header class="banner">
        <h1 style="margin:0;font-size:1.4rem;">Expiry notification test</h1>
        <p><?= h($statusWord) ?> — <?= h((string) $result['message']) ?></p>
    </header>
    <main class="wrap">
        <section class="card">
            <div class="meta">
                <div>
                    <strong>Target expiry date</strong>
                    <?= h((string) $result['target_date']) ?>
                </div>
                <div>
                    <strong>Agreements found</strong>
                    <?= (int) $result['found'] ?>
                </div>
                <div>
                    <strong>Emails sent</strong>
                    <?= (int) $result['emails_sent'] ?> / <?= (int) $result['emails_attempted'] ?>
                </div>
                <div>
                    <strong>Delivery</strong>
                    <?= h((string) ($result['via'] !== '' ? $result['via'] : 'none')) ?>
                </div>
            </div>
            <p style="margin:1rem 0 0;font-size:0.875rem;color:#475569;">
                Recipients are taken from each agreement’s
                <code>partner_email</code> and <code>director_email</code>.
                Failures are written to PHP <code>error_log()</code>.
                For a live SMTP transcript use
                <a href="test_email.php"><code>test_email.php</code></a>.
            </p>
        </section>

        <section class="card">
            <h2 style="margin:0 0 0.75rem;font-size:1.05rem;color:#064e3b;">Matching active agreements</h2>
            <?php if ($agreements === []): ?>
                <p style="margin:0;color:#475569;">
                    None. Set an Active agreement’s <code>Expiry_Date</code> to
                    <code><?= h((string) $result['target_date']) ?></code> and refresh this page.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Partner organisation</th>
                            <th>Partner email</th>
                            <th>Director email</th>
                            <th>Expiration date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agreements as $agreement): ?>
                            <tr>
                                <td><?= h((string) ($agreement['id'] ?? '')) ?></td>
                                <td><?= h((string) ($agreement['title'] ?? '')) ?></td>
                                <td><?= h((string) ($agreement['partner_name'] ?? '')) ?></td>
                                <td><?= h((string) ($agreement['partner_email'] ?? '')) ?></td>
                                <td><?= h((string) ($agreement['director_email'] ?? '')) ?></td>
                                <td><?= h((string) ($agreement['expiry_date'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($errors !== []): ?>
            <section class="card errors">
                <h2 style="margin:0 0 0.5rem;font-size:1.05rem;">Errors</h2>
                <ul style="margin:0;padding-left:1.2rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h((string) $error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
