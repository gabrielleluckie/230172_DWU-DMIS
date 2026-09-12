<?php

declare(strict_types=1);

/**
 * New agreement entry flow.
 *
 * Generates a unique access_token, stores it with the agreement,
 * and emails the partner and director a secure view link.
 */

require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/agreement_notify.php';

$user = requireRole($pdo, [ROLE_PARTNERSHIP_DIRECTOR]);
$registerPath = appUrl('add_agreement.php');

handleDirectorPartnershipEntryPost($pdo, $user, $registerPath);

$editingDraftId = isset($_GET['draft']) ? (int) $_GET['draft'] : 0;
$entryForm = [];

if ($editingDraftId > 0) {
    $draftRow = fetchDirectorAgreementDraftById($pdo, (int) $user['id'], $editingDraftId);
    if ($draftRow === null) {
        setFlash('error', 'That draft was not found.');
        redirect($registerPath);
    }
    $entryForm = $draftRow['form'] ?? [];
}

$pendingProposals = fetchSubmittedProposals($pdo);
$pendingCount = count($pendingProposals);
$directorDrafts = fetchDirectorAgreementDrafts($pdo, (int) $user['id']);
$draftCount = count($directorDrafts);
$partners = fetchPartners($pdo);
$campuses = fetchCampuses($pdo);
$directorEntryFormAction = $registerPath;
$directorEmailPrefill = (string) ($entryForm['director_email'] ?? $user['email'] ?? '');
$directorDraftsAction = $registerPath;
$activeNav = $editingDraftId > 0 ? 'drafts' : 'register';

renderDirectorDashboardHeader(
    $user,
    'Active Partnership Entry Form',
    $pendingProposals,
    $pendingCount,
    [
        'pageSubtitle'     => 'Save a draft at any time, then register it later from Saved Drafts.',
        'extraStylesheets' => [
            assetUrl('css/director-partnership-entry-form.css') . '?v=' . (string) (
                is_file(__DIR__ . '/css/director-partnership-entry-form.css')
                    ? filemtime(__DIR__ . '/css/director-partnership-entry-form.css')
                    : time()
            ),
        ],
    ]
);

renderDashboardLogoutAction();
renderDirectorSubnav($activeNav, $pendingCount, $draftCount);
?>

<div class="director-register-page">
    <?php renderDirectorFlashMessages(); ?>
    <?php require __DIR__ . '/includes/views/director-agreement-drafts.php'; ?>
    <section class="director-entry-form-panel director-panel">
        <div class="director-panel-header">
            <h1><?= $editingDraftId > 0 ? 'Edit Draft Partnership' : 'Active Partnership Entry Form' ?></h1>
            <p>
                <?php if ($editingDraftId > 0): ?>
                    Finish this draft and click Register Agreement, or Save Draft again. You can still start a
                    <a href="<?= e($registerPath) ?>">new agreement</a> without losing other drafts.
                <?php else: ?>
                    Register a signed partnership agreement. The partner and director will receive a secure view link by email.
                <?php endif; ?>
            </p>
        </div>

        <?php require __DIR__ . '/includes/views/director-partnership-entry-form.php'; ?>
    </section>
</div>

<?php renderDirectorDashboardFooter(); ?>
