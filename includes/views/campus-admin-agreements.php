<?php

/**
 * Campus Admin — registered agreements list and detail.
 *
 * Expected variables:
 * - $agreements (list<array>)
 * - $filterStatus (string)
 * - $viewAgreement (?array)
 */

use App\Models\Agreement;

$filterStatus = $filterStatus ?? 'ALL';
$viewAgreement = $viewAgreement ?? null;
$baseUrl = routePath('dashboard/campus-admin') . '?tab=agreements';
$statusOptions = [
    Agreement::STATUS_ACTIVE => 'Active',
    Agreement::STATUS_EXPIRING_SOON => 'Expiring Soon',
    Agreement::STATUS_EXPIRED => 'Expired',
    'ALL' => 'All registered',
];

$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('M j, Y', $timestamp) : '—';
};
?>

<div class="app-page-heading mb-3">
    <h1 class="h5 mb-1">Registered Agreements</h1>
    <p class="text-secondary small mb-0">View active partnerships for your campus and download the attached agreement file.</p>
</div>

<?php if (is_array($viewAgreement)): ?>
    <?php
    $detailId = (int) ($viewAgreement['id'] ?? 0);
    $hasDocument = agreementHasDocument($viewAgreement);
    $fileLabel = $hasDocument
        ? agreementDownloadFilename((string) $viewAgreement['document_path'])
        : '';
    ?>
    <section class="ca-agreement-detail mb-4">
        <div class="ca-agreement-detail-header">
            <a href="<?= e($baseUrl . '&status=' . rawurlencode((string) $filterStatus)) ?>" class="ca-agreement-back">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Back to list
            </a>
            <span class="registry-status-text <?= statusBadgeClasses((string) ($viewAgreement['status'] ?? '')) ?>">
                <?= e((string) ($viewAgreement['status'] ?? '')) ?>
            </span>
        </div>

        <h2 class="h4 mb-1"><?= e((string) ($viewAgreement['partner'] ?? 'Partnership')) ?></h2>
        <p class="text-secondary small mb-4">
            <?= e((string) ($viewAgreement['type'] ?? '')) ?>
            <?php if (!empty($viewAgreement['campus'])): ?>
                <span aria-hidden="true"> · </span><?= e((string) $viewAgreement['campus']) ?>
            <?php endif; ?>
        </p>

        <dl class="ca-agreement-meta">
            <div>
                <dt>Partnership type</dt>
                <dd><?= e((string) ($viewAgreement['Partnership_type'] ?? '—')) ?></dd>
            </div>
            <div>
                <dt>Signed</dt>
                <dd><?= e($formatDate($viewAgreement['signed_date'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Expires</dt>
                <dd><?= e($formatDate($viewAgreement['expiry'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Duration</dt>
                <dd><?= e((string) ($viewAgreement['duration'] ?? '—')) ?></dd>
            </div>
            <?php if (!empty($viewAgreement['contact']) && $viewAgreement['contact'] !== 'N/A'): ?>
                <div>
                    <dt>Contact</dt>
                    <dd>
                        <?= e((string) $viewAgreement['contact']) ?>
                        <?php if (!empty($viewAgreement['contact_email'])): ?>
                            <span class="d-block small text-secondary"><?= e((string) $viewAgreement['contact_email']) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>

        <?php if (!empty($viewAgreement['scope'])): ?>
            <div class="ca-agreement-scope">
                <h3 class="h6 mb-2">Scope</h3>
                <p class="mb-0"><?= nl2br(e((string) $viewAgreement['scope'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="ca-agreement-document">
            <h3 class="h6 mb-2">Agreement document</h3>
            <?php if ($hasDocument): ?>
                <p class="small text-secondary mb-3">
                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>
                    <?= e($fileLabel) ?>
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-dark btn-sm"
                       href="<?= e(agreementDownloadUrl($detailId)) ?>"
                       target="_blank"
                       rel="noopener">
                        <i class="bi bi-eye me-1" aria-hidden="true"></i>
                        View document
                    </a>
                    <a class="btn btn-outline-dark btn-sm"
                       href="<?= e(agreementDownloadUrl($detailId, true)) ?>">
                        <i class="bi bi-download me-1" aria-hidden="true"></i>
                        Download
                    </a>
                </div>
            <?php else: ?>
                <p class="mb-0 text-secondary">No scanned agreement file was attached when this partnership was registered.</p>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <form method="get" action="<?= e(routePath('dashboard/campus-admin')) ?>" class="ca-agreements-filters mb-3">
        <input type="hidden" name="tab" value="agreements">
        <label for="ca-agreement-status" class="form-label small fw-semibold mb-1">Status</label>
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <select id="ca-agreement-status" name="status" class="form-select form-select-sm ca-agreements-status-select">
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filterStatus === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-dark btn-sm">Apply</button>
        </div>
    </form>

    <section class="review-panel ca-agreements-panel">
        <div class="review-panel-header">
            <h2>Registered agreements</h2>
            <p><?= count($agreements) ?> record(s) for your campus.</p>
        </div>
        <div class="table-responsive">
            <table class="table ca-agreements-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Partner</th>
                        <th>Type</th>
                        <th>Lifespan</th>
                        <th>Status</th>
                        <th>Document</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($agreements === []): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                No registered agreements found for this filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agreements as $agreement): ?>
                            <?php
                            $rowId = (int) ($agreement['id'] ?? 0);
                            $hasDocument = agreementHasDocument($agreement);
                            $signedLabel = $formatDate($agreement['signed_date'] ?? null);
                            $expiryLabel = $formatDate($agreement['expiry'] ?? null);
                            $viewUrl = $baseUrl . '&id=' . $rowId . '&status=' . rawurlencode((string) $filterStatus);
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) ($agreement['partner'] ?? '')) ?></td>
                                <td><?= e((string) ($agreement['type'] ?? '')) ?></td>
                                <td class="small">
                                    <?= e($signedLabel) ?>
                                    <span class="text-secondary" aria-hidden="true"> → </span>
                                    <?= e($expiryLabel) ?>
                                </td>
                                <td>
                                    <span class="registry-status-text <?= statusBadgeClasses((string) ($agreement['status'] ?? '')) ?>">
                                        <?= e((string) ($agreement['status'] ?? '')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($hasDocument): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a class="btn btn-sm btn-outline-dark"
                                               href="<?= e(agreementDownloadUrl($rowId)) ?>"
                                               target="_blank"
                                               rel="noopener">View</a>
                                            <a class="btn btn-sm btn-dark"
                                               href="<?= e(agreementDownloadUrl($rowId, true)) ?>">Download</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e($viewUrl) ?>">Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
