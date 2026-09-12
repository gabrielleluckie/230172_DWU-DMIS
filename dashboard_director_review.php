<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/guard.php';

$user = requireRole($pdo, [ROLE_PARTNERSHIP_DIRECTOR]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_proposal') {
        $proposalId = (int) ($_POST['proposal_id'] ?? 0);

        if (approveProposal($pdo, $proposalId, (int) $user['id'], $user['name'])) {
            setFlash('success', 'Proposal #' . $proposalId . ' has been approved for offline negotiation.');
        } else {
            setFlash('error', 'Unable to approve the selected proposal.');
        }

        redirect(directorReviewPath());
    }

    if ($action === 'reject_proposal') {
        $proposalId = (int) ($_POST['proposal_id'] ?? 0);
        $comment = trim($_POST['rejection_comment'] ?? '');

        if ($comment === '') {
            setFlash('error', 'A rejection reason is required.');
            redirect(directorReviewPath());
        }

        if (rejectProposal($pdo, $proposalId, (int) $user['id'], $comment, $user['name'])) {
            setFlash('success', 'Proposal #' . $proposalId . ' has been rejected with feedback.');
        } else {
            setFlash('error', 'Unable to reject the selected proposal.');
        }

        redirect(directorReviewPath());
    }
}

$pendingProposals = fetchSubmittedProposals($pdo);
$pendingCount = count($pendingProposals);
$draftCount = count(fetchDirectorAgreementDrafts($pdo, (int) $user['id']));

renderDirectorDashboardHeader(
    $user,
    'Pending Proposals for Review',
    $pendingProposals,
    $pendingCount,
    [
        'pageSubtitle' => 'Campus Admin submissions with Status = Submitted.',
    ]
);

renderDashboardLogoutAction();
renderDirectorSubnav('review', $pendingCount, $draftCount);
?>

<div class="director-review-page">
    <?php renderDirectorFlashMessages(); ?>
    <section class="director-panel">
        <div class="director-panel-header d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <h1>Pending Proposals for Review</h1>
                <p>Approve to clear a proposal for offline legal negotiation, or reject with a reason for the campus admin.</p>
            </div>
            <?php if ($pendingCount === 0): ?>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Queue empty</span>
            <?php else: ?>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800"><?= (int) $pendingCount ?> in queue</span>
            <?php endif; ?>
        </div>

        <?php if ($pendingProposals === []): ?>
            <div class="director-empty-state">
                <p class="text-sm text-slate-500 mb-0">No new pending proposal submissions from satellite campuses.</p>
                <p class="mt-2 text-xs text-slate-400 mb-0">
                    When a Campus Admin submits a proposal, it will appear here for approval or rejection.
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingProposals as $proposal): ?>
                <article class="director-proposal-item">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-slate-900 h5 mb-1"><?= e($proposal['partner_name']) ?></h2>
                            <p class="mt-1 text-sm text-slate-500 mb-0">
                                <?= e($proposal['agreement_type']) ?> · <?= e($proposal['campus']) ?>
                            </p>
                            <?php if (($proposal['partnership_type'] ?? '') !== ''): ?>
                                <p class="mt-1 text-xs text-slate-500 mb-0"><?= e($proposal['partnership_type']) ?></p>
                            <?php endif; ?>
                            <?php if (($proposal['scope_description'] ?? '') !== ''): ?>
                                <p class="mt-2 text-xs text-slate-600 whitespace-pre-line mb-0"><?= e($proposal['scope_description']) ?></p>
                            <?php endif; ?>
                            <p class="mt-2 text-xs text-slate-400 mb-0">
                                Submitted <?= e($proposal['submitted_at']) ?> by <?= e($proposal['submitted_by']) ?>
                                · Proposal #<?= (int) $proposal['id'] ?>
                            </p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Submitted</span>
                    </div>

                    <p class="mt-3 text-xs text-slate-500 mb-0">
                        Registering the signed agreement in the live registry is a separate step on the
                        <a href="<?= e(directorRegisterPath()) ?>" class="text-emerald-700 font-semibold">Active Partnership Entry Form</a>.
                    </p>

                    <div class="mt-4 d-flex flex-column gap-3">
                        <form method="post" action="<?= e(directorReviewPath()) ?>">
                            <input type="hidden" name="action" value="approve_proposal">
                            <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
                            <button type="submit"
                                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                Approve Proposal
                            </button>
                        </form>

                        <form method="post" action="<?= e(directorReviewPath()) ?>" class="d-flex flex-wrap align-items-end gap-2">
                            <input type="hidden" name="action" value="reject_proposal">
                            <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
                            <div class="flex-grow-1" style="min-width: 16rem;">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Rejection reason (required)
                                </label>
                                <input type="text"
                                       name="rejection_comment"
                                       required
                                       placeholder="Explain why this proposal cannot proceed..."
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
                            </div>
                            <button type="submit"
                                    class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">
                                Reject Proposal
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<?php renderDirectorDashboardFooter(); ?>
