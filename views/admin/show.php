<?php
/**
 * Eine Vereinbarung: Angaben, Status, Prüfsumme, frühere/neuere Zeichnungen; PDF, Einladung erneut senden bzw. neue Zeichnung
 * anfordern, beenden. @var array $c  @var ?array $version  @var ?array $current  @var array $older  @var array $newer
 */
use Klxm\Avv\Avv;

$status = ['open' => __('Offen – Einladung gesendet, noch nicht unterzeichnet'), 'signed' => __('Unterzeichnet'), 'replaced' => __('Ersetzt durch eine neuere Zeichnung'), 'revoked' => __('Beendet')];
$when = fn($t) => $t ? date('d.m.Y H:i', (int) strtotime((string) $t)) : '–';
$details = json_decode((string) $c['details'], true) ?: [];
$lists = $version ? Avv::lists($version) : [];
$outdated = $c['status'] === 'signed' && $current && (int) $c['version_id'] !== (int) $current['id'];
?>
<p class="avv-back"><a href="<?= e(url('/admin/avv')) ?>">‹ <?= e(__('Alle Verträge')) ?></a></p>
<div class="avv-show">
  <section class="adm-card">
    <h2><?= e($c['company'] ?: ($c['contact'] ?: $c['email'])) ?></h2>
    <p class="avv-status avv-status--<?= e($c['status']) ?>"><?= e($status[$c['status']] ?? $c['status']) ?><?= $outdated ? ' · ' . e(__('ältere Fassung ({v}) – aktuell ist {c}', ['v' => $version['number'] ?? '?', 'c' => $current['number']])) : '' ?></p>
    <dl class="avv-dl">
      <dt><?= e(__('Firma')) ?></dt><dd><?= e($c['company'] ?: '–') ?></dd>
      <dt><?= e(__('Anschrift')) ?></dt><dd><?= e(trim($c['street'] . ', ' . $c['zip_city'], ', ') ?: '–') ?></dd>
      <dt><?= e(__('E-Mail')) ?></dt><dd><?= e($c['email'] ?: '–') ?></dd>
      <?php if ($c['customer_no'] !== ''): ?><dt><?= e(__('Kundennummer')) ?></dt><dd><?= e($c['customer_no']) ?></dd><?php endif; ?>
      <dt><?= e(__('Unterzeichnet von')) ?></dt><dd><?= e($c['signer'] !== '' ? $c['signer'] . ($c['signer_role'] !== '' ? ', ' . $c['signer_role'] : '') : '–') ?><?= $c['sig_kind'] !== '' && $c['signed_at'] ? ' · ' . e($c['sig_kind'] === 'drawn' ? __('von Hand gezeichnet') : __('mit Namen')) : '' ?></dd>
      <dt><?= e(__('Fassung')) ?></dt><dd><?= e((string) ($version['number'] ?? '–')) ?><?= $version && $version['note'] !== '' ? ' · ' . e($version['note']) : '' ?></dd>
      <dt><?= e(__('Eingeladen')) ?></dt><dd><?= e($when($c['invited_at'])) ?><?= $c['invited_by'] !== '' ? ' · ' . e(__('von {name}', ['name' => $c['invited_by']])) : '' ?><?= $c['source'] === 'self' ? e(__('über die Website (selbst ausgefüllt)')) : '' ?></dd>
      <dt><?= e(__('Unterzeichnet')) ?></dt><dd><?= e($when($c['signed_at'])) ?></dd>
      <?php if ($c['pdf_hash'] !== ''): ?><dt><?= e(__('Prüfsumme (SHA-256)')) ?></dt><dd><code class="avv-hash"><?= e($c['pdf_hash']) ?></code></dd><?php endif; ?>
    </dl>
    <?php foreach ($lists as $key => $l): $sel = array_values(array_filter(array_merge((array) ($details[$key] ?? []), [trim((string) ($details[$key . '_other'] ?? ''))]))); if (!$sel) continue; ?>
    <h3 class="avv-h3"><?= e($l['label']) ?></h3><ul class="avv-ul"><?php foreach ($sel as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul>
    <?php endforeach; ?>
  </section>

  <aside class="adm-card avv-actions">
    <h2><?= e(__('Aktionen')) ?></h2>
    <?php if ($c['pdf_file'] !== ''): ?><p><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/avv/' . $c['id'] . '/pdf')) ?>"><?= icon('download-simple') ?> <?= e(__('PDF herunterladen')) ?></a></p><?php endif; ?>
    <?php if ($c['status'] === 'open'): ?>
      <form method="post" action="<?= e(url('/admin/avv/' . $c['id'] . '/erneut')) ?>"><?= csrf_field() ?><button class="adm-btn"><?= icon('paper-plane-tilt') ?> <?= e(__('Einladung erneut senden')) ?></button></form>
      <p class="adm-muted"><?= e(__('Gültig bis {date}. Erneut senden erzeugt einen neuen Link.', ['date' => $when($c['sign_expires'])])) ?></p>
      <form method="post" action="<?= e(url('/admin/avv/' . $c['id'] . '/beenden')) ?>" data-confirm="<?= e(__('Einladung zurückziehen? Der Link gilt dann nicht mehr.')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--ghost adm-btn--danger-text"><?= e(__('Einladung zurückziehen')) ?></button></form>
    <?php elseif ($c['status'] === 'signed'): ?>
      <form method="post" action="<?= e(url('/admin/avv/' . $c['id'] . '/erneut')) ?>" class="avv-renew"><?= csrf_field() ?>
        <label for="avv-msg"><?= e(__('Neue Zeichnung anfordern')) ?></label>
        <p class="adm-muted"><?= e(__('Der Kunde unterzeichnet die aktuelle Fassung ({n}); seine Angaben sind vorausgefüllt. Danach gilt diese Zeichnung als ersetzt.', ['n' => $current['number'] ?? '?'])) ?></p>
        <textarea id="avv-msg" name="message" rows="2" maxlength="1000" placeholder="<?= e(__('Persönliche Nachricht (optional), z. B. was sich geändert hat')) ?>"></textarea>
        <button class="adm-btn<?= $outdated ? ' adm-btn--primary' : '' ?>"><?= icon('paper-plane-tilt') ?> <?= e(__('Zur Zeichnung einladen')) ?></button></form>
      <form method="post" action="<?= e(url('/admin/avv/' . $c['id'] . '/beenden')) ?>" data-confirm="<?= e(__('Vereinbarung als beendet markieren (z. B. Zusammenarbeit beendet)? Das PDF bleibt erhalten.')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--ghost adm-btn--danger-text"><?= e(__('Als beendet markieren')) ?></button></form>
    <?php endif; ?>
    <details class="avv-danger">
      <summary><?= e(__('Endgültig löschen …')) ?></summary>
      <form method="post" action="<?= e(url('/admin/avv/' . $c['id'] . '/loeschen')) ?>" data-confirm="<?= e(__('Vereinbarung mit {name} endgültig löschen? Angaben, Unterschrift und PDF werden entfernt und lassen sich nicht wiederherstellen.', ['name' => $c['company'] ?: $c['email']])) ?>" data-confirm-ok="<?= e(__('Endgültig löschen')) ?>"><?= csrf_field() ?>
        <?php if ($c['status'] !== 'open'): ?>
        <p class="avv-danger__warn"><?= icon('warning') ?> <?= e(__('Diese Vereinbarung ist unterzeichnet. Nach dem Löschen fehlt Ihnen der Nachweis nach Art. 28 DS-GVO – laden Sie das PDF vorher herunter, wenn Sie es aufbewahren müssen. „Als beendet markieren“ behält den Nachweis.')) ?></p>
        <label class="f-check"><input type="checkbox" name="confirm" value="1" required> <span><?= e(__('Ich habe verstanden, dass der Nachweis der Unterzeichnung verloren geht.')) ?></span></label>
        <?php endif; ?>
        <button class="adm-btn adm-btn--small adm-btn--danger"><?= icon('trash') ?> <?= e(__('Endgültig löschen')) ?></button>
      </form>
    </details>
    <?php if ($older || $newer): ?>
    <h3 class="avv-h3"><?= e(__('Verlauf')) ?></h3>
    <ul class="avv-hist">
      <?php foreach ($newer as $n): ?><li><a href="<?= e(url('/admin/avv/' . $n['id'])) ?>"><?= e($n['status'] === 'open' ? __('Neue Zeichnung angefordert ({date})', ['date' => $when($n['invited_at'])]) : __('Neuere Zeichnung ({date})', ['date' => $when($n['signed_at'])])) ?></a></li><?php endforeach; ?>
      <?php foreach ($older as $o): ?><li><a href="<?= e(url('/admin/avv/' . $o['id'])) ?>"><?= e(__('Frühere Zeichnung ({date})', ['date' => $when($o['signed_at'])])) ?></a></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </aside>
</div>
