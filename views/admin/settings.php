<?php
/** Einstellungen: Auftragnehmer, Gegenzeichnung, E-Mails; Einbindung. @var array $values  @var array $errors */
use Core\Fields;
use Klxm\Avv\AdminController;
?>
<form method="post" action="<?= e(url('/admin/avv/einstellungen')) ?>" novalidate>
  <?= csrf_field() ?>
  <section class="adm-card"><div class="adm-fields"><?= Fields::renderForm(AdminController::settingsFields(), $values, $errors, 'f') ?></div></section>
  <div class="adm-savebar"><button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button></div>
</form>
<section class="adm-card">
  <h2><?= e(__('Einbindung')) ?></h2>
  <ul>
    <li><?= e(__('Selbst ausfüllen lassen: Block „AVV abschließen“ auf eine Seite der Website setzen (z. B. „AVV“ im Fuß verlinkt).')) ?></li>
    <li><?= e(__('Einzelne Kunden: unter „Verträge“ einladen – mit persönlichem Link.')) ?></li>
    <li><?= e(__('E-Mail-Konto für AVV-Mails: Grundeinstellungen → E-Mail-Versand → Verwendung „AVV“.')) ?></li>
  </ul>
</section>
