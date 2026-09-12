<?php

/**
 * Partnership Director sub-navigation.
 *
 * Expected: $activeNav (overview|review|register|drafts), $pendingCount (int), $draftCount (int)
 */

$activeNav = $activeNav ?? 'overview';
$pendingCount = (int) ($pendingCount ?? 0);
$draftCount = (int) ($draftCount ?? 0);
$draftsUrl = directorRegisterPath() . '?drafts=1#director-saved-drafts';
?>
<div class="app-subnav app-subnav-bleed">
    <div class="container-fluid px-3 px-lg-4">
        <ul class="nav nav-tabs campus-admin-tabs mb-0" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="<?= e(routePath('dashboard/director')) ?>"
                   class="nav-link fw-semibold <?= $activeNav === 'overview' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2 me-1"></i> Overview
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?= e(directorReviewPath()) ?>"
                   class="nav-link fw-semibold <?= $activeNav === 'review' ? 'active' : '' ?>">
                    <i class="bi bi-clipboard-check me-1"></i> Pending Proposals
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge rounded-pill bg-warning text-dark ms-1"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?= e(directorRegisterPath()) ?>"
                   class="nav-link fw-semibold <?= $activeNav === 'register' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-plus me-1"></i> Register Partnership
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?= e($draftsUrl) ?>"
                   class="nav-link fw-semibold <?= $activeNav === 'drafts' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text me-1"></i> Saved Drafts
                    <?php if ($draftCount > 0): ?>
                        <span class="badge rounded-pill bg-secondary ms-1"><?= $draftCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>
</div>
