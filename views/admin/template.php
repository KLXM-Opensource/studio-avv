<?php
/**
 * Vertragstext: Entwurf bearbeiten (Titel, Text mit Platzhaltern, Anlage-A-Listen, Unterauftragnehmer, TOM), PDF-Vorschau,
 * als neue Fassung veröffentlichen; Liste der Fassungen. @var array $values  @var array $errors  @var bool $isDraft  @var array $versions
 */
use Core\Fields;
use Klxm\Avv\AdminController;
?>
<?php if ($isDraft): ?><p class="adm-flash adm-flash--info" role="note"><?= e(__('Sie bearbeiten einen Entwurf. Kunden sehen weiterhin die veröffentlichte Fassung, bis Sie veröffentlichen.')) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('/admin/avv/vorlage')) ?>" novalidate class="avv-tpl">
  <?= csrf_field() ?>
  <section class="adm-card"><div class="adm-fields"><?= Fields::renderForm(AdminController::templateFields(), $values, $errors, 'f') ?></div></section>
  <div class="adm-savebar avv-savebar">
    <button class="adm-btn" name="do" value="save"><?= e(__('Entwurf speichern')) ?></button>
    <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/avv/vorlage/vorschau.pdf')) ?>" target="_blank" rel="noopener"><?= icon('file-pdf') ?> <?= e(__('PDF-Vorschau des Entwurfs')) ?></a>
    <span class="avv-publish">
      <label class="adm-sr" for="avv-note"><?= e(__('Notiz zur Fassung')) ?></label>
      <input id="avv-note" name="note" maxlength="191" placeholder="<?= e(__('Was hat sich geändert? (optional)')) ?>">
      <button class="adm-btn adm-btn--primary" name="do" value="publish" data-confirm="<?= e(__('Als neue Fassung veröffentlichen? Neue Zeichnungen und Einladungen nutzen sie sofort; bereits unterzeichnete Vereinbarungen bleiben unverändert.')) ?>"><?= e(__('Als neue Fassung veröffentlichen')) ?></button>
    </span>
  </div>
</form>
<?php if ($isDraft): ?><form method="post" action="<?= e(url('/admin/avv/vorlage/verwerfen')) ?>" data-confirm="<?= e(__('Entwurf verwerfen?')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Entwurf verwerfen')) ?></button></form><?php endif; ?>

<section class="adm-card">
  <h2><?= e(__('Fassungen')) ?></h2>
  <table class="adm-table"><thead><tr><th scope="col"><?= e(__('Fassung')) ?></th><th scope="col"><?= e(__('Veröffentlicht')) ?></th><th scope="col"><?= e(__('Notiz')) ?></th><th scope="col"><?= e(__('Unterzeichnet')) ?></th></tr></thead><tbody>
  <?php foreach ($versions as $i => $v): ?>
    <tr><td><strong><?= (int) $v['number'] ?></strong><?= $i === 0 ? ' <span class="adm-badge adm-badge--ok">' . e(__('aktuell')) . '</span>' : '' ?></td><td><?= e(date('d.m.Y H:i', (int) strtotime((string) $v['created_at']))) ?><?= $v['created_by'] !== '' && $v['created_by'] !== 'system' ? ' · ' . e($v['created_by']) : '' ?></td><td><?= e($v['note']) ?></td>
      <td><?php if ((int) $v['signed']): ?><a href="<?= e(url('/admin/avv' . ($i === 0 ? '?status=signed' : '?status=outdated'))) ?>"><?= (int) $v['signed'] ?></a><?php else: ?>0<?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</section>
