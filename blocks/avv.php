<?php
/**
 * Block „AVV abschließen“ (Erweiterung avv): Assistent auf einer beliebigen Seite – Kunden füllen die Vereinbarung selbst aus
 * und unterzeichnen. Nur auf der Website, auf der die AVV läuft (bei mehreren Websites: Netzwerk-Website).
 * @var \Core\Block $b  @var array $d
 */
use Klxm\Avv\Avv;
use Klxm\Avv\Repo;

$ok = Avv::here();
?>
<div class="<?= e(app()->theme->def['container_class'] ?? 'wrap') ?> avv-block">
  <?php if (trim((string) ($d['title'] ?? '')) !== '' || is_editing()): ?><h2 class="avv-block__title"<?= $b->edit('title') ?>><?= e((string) ($d['title'] ?? '')) ?></h2><?php endif; ?>
  <?php if (trim((string) ($d['text'] ?? '')) !== ''): ?><div class="avv-block__text"><?= rich((string) $d['text']) ?></div><?php endif; ?>
  <?php if (!$ok): ?>
    <?php if (is_editing()): ?><p class="avv-note"><?= e(__('Die AVV läuft nur auf der Netzwerk-Website bzw. mit eingeschalteter Funktion „AVV“.')) ?></p><?php endif; ?>
  <?php elseif (is_editing()): ?>
    <p class="avv-note"><?= e(__('Hier erscheint der Assistent „AVV abschließen“ (4 Schritte). Text und Anlagen pflegen Sie unter Verwaltung → AVV.')) ?></p>
  <?php else: ?>
    <link rel="stylesheet" href="<?= e(Avv::asset('css/avv.css')) ?>">
    <?= \Core\Theme::capture(dirname(__DIR__) . '/views/site/wizard.php', ['version' => Repo::ensureVersion(), 'contract' => [], 'token' => '', 'errors' => []]) ?>
  <?php endif; ?>
</div>
