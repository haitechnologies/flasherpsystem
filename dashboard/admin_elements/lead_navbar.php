<?php
$leadNavLeadId = (int)($_REQUEST['lead_id'] ?? $_REQUEST['id'] ?? 0);
$leadNavLinks = [
    'lead.php' => 'Lead',
    'lead_notes.php' => 'Notes',
    'lead_attachments.php' => 'Attachments',
    'listing_quotations.php' => 'Quotations',
];

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<div class="d-flex flex-wrap gap-2 py-2">
    <?php foreach ($leadNavLinks as $leadNavFile => $leadNavLabel): ?>
        <?php
        $isActive = ($currentScript === $leadNavFile);
        $btnClass = $isActive ? 'btn-primary' : 'btn-light';
        $url = $leadNavFile . '?lead_id=' . $leadNavLeadId;
        ?>
        <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm <?php echo $btnClass; ?>">
            <?php echo htmlspecialchars($leadNavLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endforeach; ?>
</div>
