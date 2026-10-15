<?php
/**
 * Assistent „AVV abschließen“ – vier Schritte (Unternehmen · Details zum Auftrag · Vereinbarung lesen · Unterzeichnen).
 * Ohne JavaScript stehen alle Schritte untereinander; mit Skript (public/js/avv.js) einer nach dem anderen, Platzhalter im
 * Vertragstext füllen sich live, Unterschrift auf der Zeichenfläche. Im Block (blocks/avv.php) und unter /avv/zeichnen/{token}.
 * @var array $version  @var array $contract  @var string $token  @var array $errors  @var array $old
 */
use Klxm\Avv\Avv;
use Klxm\Avv\SiteController;

$errors ??= []; $old ??= []; $contract ??= []; $token ??= '';
$val = fn(string $k) => (string) ($old[$k] ?? $contract[$k] ?? '');
$details = json_decode((string) ($contract['details'] ?? ''), true) ?: [];
$checked = fn(string $list, string $opt) => in_array($opt, (array) ($old['d'][$list] ?? $details[$list] ?? []), true);
$err = fn(string $k) => isset($errors[$k]) ? '<p class="avv-err" id="avv-e-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="avv-e-' . e($k) . '"' : '';
$lists = Avv::lists($version);
$sigKind = ($old['sig_kind'] ?? 'typed') === 'drawn' ? 'drawn' : 'typed';
$ch = \Core\SpamGuard::challenge(SiteController::FORM);
$steps = [lt('Ihr Unternehmen'), lt('Details zum Auftrag'), lt('Vereinbarung lesen'), lt('Unterzeichnen')];
if (!$lists) unset($steps[1]);
$n = 0;
?>
<div class="avv" data-avv data-token-url="<?= e(Avv::url('token')) ?>">
  <?php if ($errors): ?><div class="avv-alert" role="alert"><?= e(lt('Bitte prüfen Sie die markierten Angaben.')) ?></div><?php endif; ?>
  <ol class="avv-steps" aria-label="<?= e(lt('Schritte')) ?>" data-avv-steps hidden>
    <?php foreach ($steps as $i => $s): ?><li data-avv-stepnav="<?= $i ?>"><span><?= ++$n ?></span><?= e($s) ?></li><?php endforeach; ?>
  </ol>
  <form method="post" action="<?= e(Avv::url('zeichnen')) ?>" class="avv-form" novalidate data-avv-form>
    <input type="hidden" name="_token" value="<?= e($ch['token']) ?>" data-avv-token><input type="hidden" name="_pow" value="" data-avv-pow>
    <input type="hidden" name="invite" value="<?= e($token) ?>">
    <div class="avv-hp" aria-hidden="true"><label><?= e(lt('Bitte leer lassen')) ?><input type="text" name="<?= e(\Core\SpamGuard::HONEYPOT) ?>" tabindex="-1" autocomplete="off"></label></div>

    <fieldset class="avv-step" data-avv-step="0">
      <legend><?= e(lt('Ihr Unternehmen')) ?></legend>
      <p class="avv-help"><?= e(lt('Diese Angaben stehen als Auftraggeber in der Vereinbarung.')) ?></p>
      <div class="avv-f"><label for="avv-company"><?= e(lt('Firma')) ?> <span class="avv-req">*</span></label><input id="avv-company" name="company" required maxlength="191" autocomplete="organization" value="<?= e($val('company')) ?>" data-avv-bind="firma"<?= $inv('company') ?>><?= $err('company') ?></div>
      <div class="avv-f"><label for="avv-street"><?= e(lt('Straße und Hausnummer')) ?> <span class="avv-req">*</span></label><input id="avv-street" name="street" required maxlength="191" autocomplete="street-address" value="<?= e($val('street')) ?>" data-avv-bind="anschrift"<?= $inv('street') ?>><?= $err('street') ?></div>
      <div class="avv-f"><label for="avv-zip"><?= e(lt('PLZ und Ort')) ?> <span class="avv-req">*</span></label><input id="avv-zip" name="zip_city" required maxlength="191" value="<?= e($val('zip_city')) ?>" data-avv-bind="plz_ort"<?= $inv('zip_city') ?>><?= $err('zip_city') ?></div>
      <div class="avv-row">
        <div class="avv-f"><label for="avv-email"><?= e(lt('E-Mail für das PDF')) ?> <span class="avv-req">*</span></label><input id="avv-email" name="email" type="email" required maxlength="191" autocomplete="email" value="<?= e($val('email')) ?>"<?= $inv('email') ?>><?= $err('email') ?></div>
        <div class="avv-f"><label for="avv-no"><?= e(lt('Kundennummer (falls vorhanden)')) ?></label><input id="avv-no" name="customer_no" maxlength="60" value="<?= e($val('customer_no')) ?>"></div>
      </div>
    </fieldset>

    <?php if ($lists): ?>
    <fieldset class="avv-step" data-avv-step="1">
      <legend><?= e(lt('Details zum Auftrag')) ?></legend>
      <p class="avv-help"><?= e(lt('Was trifft auf unsere Zusammenarbeit zu? Ihre Auswahl steht in Anlage A der Vereinbarung.')) ?></p>
      <?php foreach ($lists as $key => $l): ?>
      <div class="avv-group" role="group" aria-labelledby="avv-l-<?= e($key) ?>">
        <p class="avv-group__h" id="avv-l-<?= e($key) ?>"><?= e($l['label']) ?></p>
        <?php foreach ($l['options'] as $i => $o): ?>
        <label class="avv-check"><input type="checkbox" name="d[<?= e($key) ?>][]" value="<?= e($o) ?>"<?= $checked($key, $o) ? ' checked' : '' ?> data-avv-list="<?= e($key) ?>"> <span><?= e($o) ?></span></label>
        <?php endforeach; ?>
        <?php if ($l['other']): ?><div class="avv-f avv-f--other"><label for="avv-o-<?= e($key) ?>"><?= e(lt('Weitere')) ?></label><input id="avv-o-<?= e($key) ?>" name="d[<?= e($key) ?>_other]" maxlength="500" value="<?= e((string) ($old['d'][$key . '_other'] ?? $details[$key . '_other'] ?? '')) ?>" data-avv-other="<?= e($key) ?>"></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </fieldset>
    <?php endif; ?>

    <fieldset class="avv-step" data-avv-step="2">
      <legend><?= e(lt('Vereinbarung lesen')) ?></legend>
      <div class="avv-doc-box" tabindex="0" aria-label="<?= e(lt('Text der Vereinbarung')) ?>"><?= Avv::document($version, ['company' => $val('company'), 'street' => $val('street'), 'zip_city' => $val('zip_city'), 'signer' => $val('signer'), 'signer_role' => $val('signer_role'), 'details' => $details], true) ?></div>
      <label class="avv-check avv-check--agree"><input type="checkbox" name="agree" value="1" required<?= !empty($old['agree']) ? ' checked' : '' ?><?= $inv('agree') ?>> <span><?= e(lt('Ich habe die Vereinbarung gelesen und stimme ihr im Namen des Auftraggebers zu.')) ?></span></label><?= $err('agree') ?>
    </fieldset>

    <fieldset class="avv-step" data-avv-step="3">
      <legend><?= e(lt('Unterzeichnen')) ?></legend>
      <div class="avv-row">
        <div class="avv-f"><label for="avv-signer"><?= e(lt('Name der unterzeichnenden Person')) ?> <span class="avv-req">*</span></label><input id="avv-signer" name="signer" required maxlength="191" autocomplete="name" value="<?= e($val('signer')) ?>" data-avv-bind="unterzeichner"<?= $inv('signer') ?>><?= $err('signer') ?></div>
        <div class="avv-f"><label for="avv-role"><?= e(lt('Funktion (z. B. Geschäftsführung)')) ?></label><input id="avv-role" name="signer_role" maxlength="120" value="<?= e($val('signer_role')) ?>" data-avv-bind="funktion"></div>
      </div>
      <div class="avv-group" role="radiogroup" aria-labelledby="avv-sig-h">
        <p class="avv-group__h" id="avv-sig-h"><?= e(lt('Unterschrift')) ?></p>
        <label class="avv-check"><input type="radio" name="sig_kind" value="typed"<?= $sigKind === 'typed' ? ' checked' : '' ?> data-avv-sigkind> <span><?= e(lt('Mit Namen unterzeichnen (elektronisch)')) ?></span></label>
        <label class="avv-check"><input type="radio" name="sig_kind" value="drawn"<?= $sigKind === 'drawn' ? ' checked' : '' ?> data-avv-sigkind> <span><?= e(lt('Von Hand zeichnen (Maus, Finger oder Stift)')) ?></span></label>
      </div>
      <div class="avv-pad" data-avv-pad<?= $sigKind === 'drawn' ? '' : ' hidden' ?>>
        <p class="avv-err avv-pad__missing"><?= e(lt('Bitte unterschreiben Sie im Feld – oder wählen Sie „Mit Namen unterzeichnen“.')) ?></p>
        <canvas width="600" height="180" tabindex="0" aria-label="<?= e(lt('Zeichenfläche für die Unterschrift')) ?>" data-avv-canvas></canvas>
        <button type="button" class="avv-link" data-avv-clear><?= e(lt('Neu zeichnen')) ?></button>
        <input type="hidden" name="sig_png" value="" data-avv-png><?= $err('sig_png') ?>
      </div>
      <p class="avv-help"><?= e(lt('Mit „Verbindlich unterzeichnen“ wird die Vereinbarung elektronisch unterzeichnet (Zeitpunkt und Prüfsumme werden festgehalten). Sie erhalten sie sofort als PDF.')) ?></p>
    </fieldset>

    <div class="avv-nav">
      <button type="button" class="<?= e(app()->theme->def['button_class_secondary'] ?? 'btn btn--secondary') ?>" data-avv-prev hidden><?= e(lt('Zurück')) ?></button>
      <button type="button" class="<?= e(app()->theme->def['button_class'] ?? 'btn btn--primary') ?>" data-avv-next hidden><?= e(lt('Weiter')) ?></button>
      <button type="submit" class="<?= e(app()->theme->def['button_class'] ?? 'btn btn--primary') ?>" data-avv-submit><?= e(lt('Verbindlich unterzeichnen')) ?></button>
    </div>
  </form>
  <script src="<?= e(Avv::asset('js/avv.js')) ?>" defer></script>
</div>
