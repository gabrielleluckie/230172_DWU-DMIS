<?php

/**
 * Partnership Registry — full agreement detail.
 *
 * Expected variables:
 * - $viewAgreement (array)
 * - $agreementHistory (list<array>)
 * - $filterStatus, $filterCampus
 */

$viewAgreement = $viewAgreement ?? [];
$agreementHistory = $agreementHistory ?? [];
$detailId = (int) ($viewAgreement['id'] ?? 0);
$hasDocument = agreementHasDocument($viewAgreement);
$fileLabel = $hasDocument
    ? agreementDownloadFilename((string) ($viewAgreement['document_path'] ?? ''))
    : '';
$backUrl = registryDashboardUrl([
    'status'    => $filterStatus ?? 'ALL',
    'campus_id' => $filterCampus ?? 0,
]);

$formatDate = static function (?string $value): string {
    if ($value === null || trim((string) $value) === '') {
        return '';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('M j, Y', $timestamp) : '';
};

$clean = static function (?string $value): string {
    $value = trim((string) $value);

    return ($value === '' || $value === '—' || strcasecmp($value, 'N/A') === 0) ? '' : $value;
};

$daysRemaining = $viewAgreement['days_remaining'] ?? null;
$remainingLabel = '';
if ($daysRemaining !== null && $daysRemaining !== '') {
    if ((int) $daysRemaining < 0) {
        $remainingLabel = abs((int) $daysRemaining) . ' days overdue';
    } elseif ((int) $daysRemaining === 0) {
        $remainingLabel = 'Expires today';
    } else {
        $remainingLabel = (int) $daysRemaining . ' days remaining';
    }
}

$website = $clean($viewAgreement['partner_website'] ?? '');
$websiteHref = $website;
if ($websiteHref !== '' && !preg_match('#^https?://#i', $websiteHref)) {
    $websiteHref = 'https://' . $websiteHref;
}

$contactEmail = $clean($viewAgreement['contact_email'] ?? '');
$contactName = $clean($viewAgreement['contact'] ?? '');
$scope = $clean($viewAgreement['scope'] ?? '');
$directorComments = $clean($viewAgreement['director_comments'] ?? '');

$facts = [
    ['Country', $clean($viewAgreement['partner_country'] ?? '')],
    ['Address', preg_replace('/\s+/', ' ', $clean($viewAgreement['partner_address'] ?? '')) ?: ''],
    ['Website', $website],
    ['Province', $clean($viewAgreement['campus_province'] ?? '')],
    ['Contact', $contactName],
    ['Designation', $clean($viewAgreement['contact_designation'] ?? '')],
    ['Email', $contactEmail],
    ['Phone', $clean($viewAgreement['contact_phone'] ?? '')],
    ['Fax', $clean($viewAgreement['contact_fax'] ?? '')],
    ['Signed', $formatDate($viewAgreement['signed_date'] ?? null)],
    ['Expires', $formatDate($viewAgreement['expiry'] ?? null)],
    ['Duration', $clean($viewAgreement['duration'] ?? '')],
    ['Time remaining', $remainingLabel],
    ['Registered', $formatDate($viewAgreement['registered_at'] ?? null)],
    ['Registered by', $clean($viewAgreement['registered_by'] ?? '')],
];
$facts = array_values(array_filter(
    $facts,
    static fn(array $row): bool => $row[1] !== ''
));

$renderFactValue = static function (string $label, string $value) use ($websiteHref): string {
    if ($label === 'Website') {
        return '<a href="' . e($websiteHref) . '" target="_blank" rel="noopener">' . e($value) . '</a>';
    }

    if ($label === 'Email') {
        return '<a href="mailto:' . e($value) . '">' . e($value) . '</a>';
    }

    return e($value);
};
?>
<section class="app-card registry-detail overflow-hidden">
    <div class="registry-detail-toolbar">
        <a href="<?= e($backUrl) ?>" class="registry-detail-back">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to registry
        </a>
        <div class="registry-detail-toolbar-actions">
            <span class="registry-status-text <?= statusBadgeClasses((string) ($viewAgreement['status'] ?? '')) ?>">
                <?= e((string) ($viewAgreement['status'] ?? '')) ?>
            </span>
            <?php if ($hasDocument): ?>
                <a class="btn btn-outline-dark btn-sm"
                   href="<?= e(agreementDownloadUrl($detailId)) ?>"
                   target="_blank"
                   rel="noopener">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>
                    View PDF
                </a>
                <a class="btn btn-success btn-sm"
                   href="<?= e(agreementDownloadUrl($detailId, true)) ?>">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>
                    Download
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="registry-detail-body">
        <header class="registry-detail-title">
            <p class="registry-detail-kicker mb-0">
                Agreement #<?= $detailId > 0 ? str_pad((string) $detailId, 4, '0', STR_PAD_LEFT) : '—' ?>
                <?php if ($fileLabel !== ''): ?>
                    <span aria-hidden="true"> · </span>
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    <?= e($fileLabel) ?>
                <?php endif; ?>
            </p>
            <h2 class="h4 mb-0"><?= e((string) ($viewAgreement['partner'] ?? 'Partnership')) ?></h2>
            <p class="registry-detail-subtitle mb-0">
                <?= e($clean($viewAgreement['type'] ?? '') ?: 'Agreement') ?>
                <?php if ($clean($viewAgreement['Partnership_type'] ?? '') !== ''): ?>
                    <span aria-hidden="true"> · </span><?= e($clean($viewAgreement['Partnership_type'] ?? '')) ?>
                <?php endif; ?>
                <?php if ($clean($viewAgreement['campus'] ?? '') !== ''): ?>
                    <span aria-hidden="true"> · </span><?= e($clean($viewAgreement['campus'] ?? '')) ?>
                <?php endif; ?>
            </p>
        </header>

        <?php if ($facts !== []): ?>
            <table class="registry-detail-table">
                <tbody>
                    <?php foreach (array_chunk($facts, 2) as $pair): ?>
                        <tr>
                            <?php foreach ($pair as [$label, $value]): ?>
                                <th scope="row"><?= e($label) ?></th>
                                <td><?= $renderFactValue($label, $value) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($scope !== ''): ?>
            <div class="registry-detail-note">
                <h3>Scope &amp; notes</h3>
                <p><?= nl2br(e($scope)) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($directorComments !== ''): ?>
            <div class="registry-detail-note">
                <h3>Director comments</h3>
                <p><?= nl2br(e($directorComments)) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$hasDocument): ?>
            <p class="registry-detail-empty mb-0">No scanned agreement file was attached when this partnership was registered.</p>
        <?php endif; ?>

        <?php if ($agreementHistory !== []): ?>
            <div class="registry-detail-note">
                <h3>History</h3>
                <ul class="registry-detail-history">
                    <?php foreach ($agreementHistory as $event): ?>
                        <li>
                            <strong><?= e((string) ($event['Event_Type'] ?? 'Event')) ?></strong>
                            <?php if ($formatDate($event['Event_Date'] ?? null) !== ''): ?>
                                <span><?= e($formatDate($event['Event_Date'] ?? null)) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($event['Comments'])): ?>
                                — <?= e((string) $event['Comments']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>
