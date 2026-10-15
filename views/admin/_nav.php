<?php
/** Kopf und Reiter „AVV“ (wie die Netzwerk-Übersicht). @var string $tab  @var array $flash  @var array $counts */
$items = ['index' => ['/admin/avv', __('Verträge'), 'files'], 'template' => ['/admin/avv/vorlage', __('Vertragstext'), 'pencil-simple'], 'settings' => ['/admin/avv/einstellungen', __('Einstellungen'), 'gear-six']];
$current = ['show' => 'index'][$tab] ?? $tab;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(\Klxm\Avv\Avv::multi() ? __('Netzwerk-Administration') : __('Erweiterung')) ?></p><h1><?= e(__('Auftragsverarbeitung (AVV)')) ?></h1>
    <p class="adm-muted"><?= e(__('Kunden füllen die Vereinbarung nach Art. 28 DS-GVO Schritt für Schritt aus und unterzeichnen elektronisch – beide erhalten das PDF.')) ?></p></div>
  <div class="adm-head__actions"><a class="adm-btn adm-btn--primary adm-btn--small" href="<?= e(url('/admin/avv#einladen')) ?>"><?= icon('paper-plane-tilt') ?> <?= e(__('Kunden einladen')) ?></a></div>
</header>
<nav class="net-tabs avv-tabs" aria-label="<?= e(__('Bereiche der AVV')) ?>">
  <?php foreach ($items as $k => [$href, $label, $ico]): ?><a href="<?= e(url($href)) ?>"<?= $k === $current ? ' aria-current="page"' : '' ?>><?= icon($ico) ?><span><?= e($label) ?></span><?php if ($k === 'index' && ($counts['open'] ?? 0)): ?><span class="net-tabs__n" title="<?= e(__('offen')) ?>"><?= (int) $counts['open'] ?></span><?php endif; ?></a><?php endforeach; ?>
</nav>
<?php foreach ($flash as [$type, $msg]): ?>
<p class="adm-flash adm-flash--<?= e($type) ?>" role="status"><?= e($msg) ?></p>
<?php endforeach; ?>
