<?php

declare(strict_types=1);

/**
 * Public, unauthenticated partner view.
 * Access is granted only by a matching agreement access_token.
 */

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/agreement_notify.php';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$token = trim((string) ($_GET['token'] ?? ''));
$agreement = null;

try {
    $agreement = fetchAgreementByAccessToken($pdo, $token);
} catch (Throwable $exception) {
    error_log('Partner view lookup failed: ' . $exception->getMessage());
    $agreement = null;
}

$isValid = is_array($agreement);
$pageTitle = $isValid
    ? ((string) ($agreement['title'] ?? 'Partnership Agreement')) . ' — DWU Partnership Registry'
    : 'Invalid or Expired Link — DWU Partnership Registry';

$logoPath = 'assets/images/dwu_logo.jpg';
$logoUrl = is_file(__DIR__ . '/' . $logoPath) ? $logoPath : '';

$status = trim((string) ($agreement['status'] ?? ''));
$statusClass = 'partner-view-status';

if (strcasecmp($status, 'Expired') === 0) {
    $statusClass .= ' is-expired';
} elseif (strcasecmp($status, 'Expiring Soon') === 0) {
    $statusClass .= ' is-soon';
}

$scope = trim((string) ($agreement['scope'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="css/site-footer.css">
    <link rel="stylesheet" href="css/view-agreement.css">
</head>
<body class="partner-view-body">
    <header class="partner-view-header">
        <div class="partner-view-header-inner">
            <?php if ($logoUrl !== ''): ?>
                <img class="partner-view-logo" src="<?= e($logoUrl) ?>" alt="Divine Word University">
            <?php endif; ?>
            <div>
                <p class="partner-view-kicker">Divine Word University</p>
                <h1>Partnership Registry</h1>
            </div>
        </div>
    </header>

    <main class="partner-view-main">
        <article class="partner-view-card">
            <?php if (!$isValid): ?>
                <div class="partner-view-error">
                    <h2>Invalid or Expired Link</h2>
                    <p>
                        This secure partnership link is not valid. The token may be missing, incorrect, or no longer active.
                        Please contact the DWU Partnership Division if you need a new access link.
                    </p>
                </div>
            <?php else: ?>
                <div class="partner-view-card-head">
                    <h2>Partnership Agreement Overview</h2>
                    <p>Read-only summary for the external partner. No login is required for this secure link.</p>
                </div>
                <div class="partner-view-grid">
                    <div class="partner-view-label">Title</div>
                    <div class="partner-view-value"><?= e((string) ($agreement['title'] ?? '—')) ?></div>

                    <div class="partner-view-label">Scope</div>
                    <div class="partner-view-value"><?= $scope !== '' ? e($scope) : '—' ?></div>

                    <div class="partner-view-label">Partner</div>
                    <div class="partner-view-value"><?= e((string) ($agreement['partner_name'] ?? '—')) ?></div>

                    <div class="partner-view-label">Signed date</div>
                    <div class="partner-view-value"><?= e(formatAgreementNotifyDate((string) ($agreement['signed_date'] ?? ''))) ?></div>

                    <div class="partner-view-label">Expiry date</div>
                    <div class="partner-view-value"><?= e(formatAgreementNotifyDate((string) ($agreement['expiry_date'] ?? ''))) ?></div>

                    <div class="partner-view-label">Status</div>
                    <div class="partner-view-value">
                        <span class="<?= e($statusClass) ?>"><?= e($status !== '' ? $status : '—') ?></span>
                    </div>
                </div>
                <p class="partner-view-note">
                    This page is provided for reference only. To discuss renewal or amendments, contact the DWU Partnership Division.
                </p>
            <?php endif; ?>
        </article>
    </main>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
