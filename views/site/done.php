<?php
/** Ergebnis nach der Zeichnung: Dank, PDF herunterladen, Prüfsumme. @var array $contract  @var string $token */
use Klxm\Avv\Avv;
use Klxm\Avv\Pdf;
?>
<div class="avv avv-done">
  <p class="avv-done__ok" aria-hidden="true"><?= icon('check-circle') ?></p>
  <h1><?= e(lt('Vielen Dank – die Vereinbarung ist unterzeichnet')) ?></h1>
  <p><?= e(lt('Eine Kopie mit dem PDF haben wir an {email} gesendet.', ['email' => (string) $contract['email']])) ?></p>
  <p><a class="<?= e(app()->theme->def['button_class'] ?? 'btn btn--primary') ?>" href="<?= e(Avv::url('pdf/' . $token)) ?>"><?= e(lt('PDF herunterladen')) ?></a></p>
  <p class="avv-help"><?= e(lt('Der Link gilt {n} Tage. Prüfsumme (SHA-256) des PDFs:', ['n' => Avv::DOWNLOAD_DAYS])) ?><br><code class="avv-hash"><?= e((string) $contract['pdf_hash']) ?></code></p>
  <p class="avv-help"><?= e(Pdf::downloadName($contract)) ?></p>
</div>
