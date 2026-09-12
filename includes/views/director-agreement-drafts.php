<?php

/**
 * Partnership Director — saved agreement drafts.
 *
 * Expected: $directorDrafts, $editingDraftId, $directorDraftsAction
 */

$directorDrafts = $directorDrafts ?? [];
$editingDraftId = (int) ($editingDraftId ?? 0);
$directorDraftsAction = $directorDraftsAction ?? directorRegisterPath();
$registerPath = $directorDraftsAction;
?>
<section id="director-saved-drafts" class="director-drafts-panel director-panel mb-4">
    <div class="director-panel-header">
        <h2>Saved Drafts</h2>
        <p>Open a draft to finish it, then click Register Agreement. Saving a draft does not stop you from entering another partnership.</p>
    </div>
    <div class="director-drafts-body">
        <?php if ($directorDrafts === []): ?>
            <p class="director-drafts-empty">No saved drafts yet. Use Save Draft on the form below.</p>
        <?php else: ?>
            <div class="director-drafts-list">
                <?php foreach ($directorDrafts as $savedDraft): ?>
                    <?php
                    $draftId = (int) ($savedDraft['id'] ?? 0);
                    $isEditing = $editingDraftId > 0 && $draftId === $editingDraftId;
                    $label = trim((string) ($savedDraft['title'] ?? 'Untitled draft'));
                    $metaParts = array_filter([
                        (string) ($savedDraft['partner_name'] ?? ''),
                        (string) ($savedDraft['agreement_type'] ?? ''),
                        (string) ($savedDraft['campus'] ?? ''),
                    ], static fn (string $part): bool => $part !== '');
                    $editUrl = $registerPath . (str_contains($registerPath, '?') ? '&' : '?') . 'draft=' . $draftId;
                    ?>
                    <article class="director-draft-item<?= $isEditing ? ' is-editing' : '' ?>">
                        <div>
                            <h3>
                                <a href="<?= e($editUrl) ?>"><?= e($label) ?></a>
                            </h3>
                            <?php if ($metaParts !== []): ?>
                                <p class="director-draft-meta"><?= e(implode(' · ', $metaParts)) ?></p>
                            <?php endif; ?>
                            <p class="director-draft-date">Saved <?= e((string) ($savedDraft['saved_at'] ?? '')) ?></p>
                        </div>
                        <div class="director-draft-actions">
                            <span class="director-draft-badge"><?= $isEditing ? 'Editing' : 'Draft' ?></span>
                            <a href="<?= e($editUrl) ?>" class="director-draft-btn">Edit &amp; register</a>
                            <form method="post" action="<?= e($registerPath) ?>" class="director-draft-delete">
                                <input type="hidden" name="action" value="delete_draft">
                                <input type="hidden" name="draft_id" value="<?= $draftId ?>">
                                <button type="submit"
                                        class="director-draft-btn director-draft-btn-danger"
                                        onclick="return confirm('Delete this draft? This cannot be undone.');">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
