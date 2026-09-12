<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/guard.php';

$user = requireRole($pdo, [ROLE_PARTNERSHIP_DIRECTOR]);

$pendingProposals = fetchSubmittedProposals($pdo);
$approvedProposals = fetchApprovedProposals($pdo);
$pendingCount = count($pendingProposals);
$approvedCount = count($approvedProposals);
$draftCount = count(fetchDirectorAgreementDrafts($pdo, (int) $user['id']));
$counts = fetchAgreementCounts($pdo);

renderDirectorDashboardHeader(
    $user,
    'Partnership Director Dashboard',
    $pendingProposals,
    $pendingCount
);

renderDashboardLogoutAction();
renderDirectorFlashMessages();
renderDirectorSubnav('overview', $pendingCount, $draftCount);
?>

<?php if ($pendingCount > 0): ?>
    <a href="<?= e(directorReviewPath()) ?>"
       class="director-pending-banner mb-4 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm text-decoration-none d-block"
       aria-label="Open pending proposals for review">
        <div class="flex flex-wrap items-start gap-3">
            <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                <?= (int) $pendingCount ?> pending
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-amber-950 mb-0">
                    <?= (int) $pendingCount === 1 ? '1 proposal awaits your review' : (int) $pendingCount . ' proposals await your review' ?>
                </p>
                <p class="mt-0.5 text-xs text-amber-800 mb-0">
                    Latest: <?= e($pendingProposals[0]['partner_name']) ?>
                    · <?= e($pendingProposals[0]['campus']) ?>
                    · submitted by <?= e($pendingProposals[0]['submitted_by']) ?>
                </p>
            </div>
        </div>
    </a>
<?php else: ?>
    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600" role="status">
        <i class="bi bi-inbox me-1 text-slate-400" aria-hidden="true"></i>
        No new pending proposal submissions from satellite campuses.
    </div>
<?php endif; ?>

<section class="mb-8">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <h2 class="h5 mb-0">Executive Analytics Overview</h2>
        <a href="<?= e(routePath('dashboard/registry')) ?>" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-table me-1"></i> Open Registry Dashboard
        </a>
    </div>
    <?php renderMetricCards($counts); ?>
</section>

<section class="director-panel">
    <div class="director-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2>Approved Proposals</h2>
            <p>Proposals that have been approved and are ready for negotiations.</p>
        </div>
        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
            <?= (int) $approvedCount ?> approved
        </span>
    </div>

    <?php if ($approvedProposals === []): ?>
        <div class="director-empty-state">
            <p class="text-sm text-slate-500 mb-0">No approved proposals yet.</p>
            <p class="mt-2 text-xs text-slate-400 mb-0">
                Approved proposals appear here as an immutable audit record before offline negotiation and final registry entry.
            </p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Proposal #</th>
                        <th class="px-6 py-3">Partner</th>
                        <th class="px-6 py-3">Campus</th>
                        <th class="px-6 py-3">Agreement Type</th>
                        <th class="px-6 py-3">Partnership Type</th>
                        <th class="px-6 py-3">Submitted By</th>
                        <th class="px-6 py-3">Approved</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($approvedProposals as $proposal): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-6 py-4 font-medium text-slate-900">#<?= (int) $proposal['id'] ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= e($proposal['partner_name']) ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= e($proposal['campus']) ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= e($proposal['agreement_type']) ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= e($proposal['partnership_type']) ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= e($proposal['submitted_by']) ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= e($proposal['reviewed_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php renderDirectorDashboardFooter(); ?>
