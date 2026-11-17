<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  PARTIAL: ANNOUNCEMENTS WIDGET — Reusable announcement card
 *  Usage: <?php include __DIR__ . '/../partials/announcements_widget.php'; ?>
 *
 *  Expected variables:
 *   $widget_announcements — Array from get_announcements()
 *   $widget_style         — 'card' (employee) or 'banner' (resident)
 * ─────────────────────────────────────────────────────────────
 */
$widget_announcements = $widget_announcements ?? [];
$widget_style         = $widget_style         ?? 'card';

if ($widget_style === 'banner' && !empty($widget_announcements)):
?>
<div class="alert alert-info alert-dismissible fade show mt-2" style="font-size:13px;">
    <strong>
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        Announcement:
    </strong>
    <?= htmlspecialchars($widget_announcements[0]['title']) ?> &mdash;
    <?= htmlspecialchars(mb_strimwidth($widget_announcements[0]['body'], 0, 150, '...')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size:10px;"></button>
</div>
<?php elseif ($widget_style === 'card'): ?>
<div class="card shadow-sm flex-grow-1">
    <div class="card-header fw-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        Announcements
    </div>
    <div class="card-body text-muted" style="min-height:120px;max-height:320px;overflow-y:auto;">
        <?php if (empty($widget_announcements)): ?>
            <small><em>No announcements at this time.</em></small>
        <?php else: ?>
            <?php foreach ($widget_announcements as $ann): ?>
            <div style="border-bottom:1px solid #eee;padding:8px 0;">
                <?php if (!empty($ann['image'])): ?>
                    <img src="../<?= htmlspecialchars($ann['image']) ?>" alt="" style="width:100%;max-height:140px;object-fit:cover;border-radius:8px;margin-bottom:8px;">
                <?php endif; ?>
                <strong style="color:#1e293b;"><?= htmlspecialchars($ann['title']) ?></strong>
                <br><small><?= nl2br(htmlspecialchars(mb_strimwidth($ann['body'], 0, 150, '...'))) ?></small>
                <br><small class="text-muted">City of Santa Rosa &mdash;<?= date('M j, Y', strtotime($ann['created_at'])) ?></small>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
