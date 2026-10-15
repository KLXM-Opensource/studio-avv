<?php
/**
 * Verträge: Hinweise (Angaben fehlen, ältere Fassungen), Filter, Liste, Kunden einladen.
 * @var array $rows  @var string $filter  @var string $q  @var ?array $current  @var array $missing  @var bool $pdfOk  @var array $counts  @var array $invite
 */
use Core\Fields;
use Klxm\Avv\AdminController;

$status = ['open' => [__('Offen'), 'warn'], 'signed' => [__('Unterzeichnet'), 'ok'], 'replaced' => [__('Ersetzt'), 'muted'], 'revoked' => [__('Beendet'), 'muted']];
$date = fn($t) => $t ? date('d.m.Y', (int) strtotime((string) $t)) : '–';
?>
<?php if (!$pdfOk): ?><p class="adm-flash adm-flash--error" role="alert"><?= e(__('dompdf fehlt – bitte „composer install“ im Ordner der Erweiterung ausführen. Bis dahin kann niemand unterzeichnen.')) ?></p><?php endif; ?>
<?php if ($missing): ?><p class="adm-flash adm-flash--warn" role="alert"><?= e(__('Angaben des Auftragnehmers fehlen: {list}.', ['list' => implode(', ', $missing)])) ?> <a href="<?= e(url('/admin/avv/einstellungen')) ?>"><?= e(__('Jetzt ergänzen')) ?></a></p><?php endif; ?>
<?php if ($counts['outdated']): ?>
<section class="avv-callout">
  <p><strong><?= e(__('{n} Kunden haben eine ältere Fassung unterzeichnet.', ['n' => $counts['outdated']])) ?></strong> <?= e(__('Aktuell gilt Fassung {n}.', ['n' => $current['number'] ?? '?'])) ?></p>
  <form method="post" action="<?= e(url('/admin/avv/alle-erneuern')) ?>" data-confirm="<?= e(__('Alle {n} Kunden mit älterer Fassung per E-Mail zur Zeichnung der aktuellen Fassung einladen? Ihre bisherigen Angaben sind vorausgefüllt.', ['n' => $counts['outdated']])) ?>"><?= csrf_field() ?>
    <button class="adm-btn adm-btn--small adm-btn--primary"><?= e(__('Alle zur neuen Fassung einladen')) ?></button></form>
</section>
<?php endif; ?>

<div class="avv-bar">
  <nav class="net-seg" aria-label="<?= e(__('Filter')) ?>">
    <?php foreach (['' => [__('Alle'), null], 'open' => [__('Offen'), $counts['open']], 'signed' => [__('Unterzeichnet'), $counts['signed']], 'outdated' => [__('Ältere Fassung'), $counts['outdated']]] as $k => [$l, $n]): ?>
    <a href="<?= e(url('/admin/avv' . ($k !== '' ? '?status=' . $k : ''))) ?>"<?= $filter === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?><?= $n ? ' <span class="avv-n">' . (int) $n . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </nav>
  <form method="get" action="<?= e(url('/admin/avv')) ?>" class="net-search avv-search"><?php if ($filter): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <label><span class="adm-sr"><?= e(__('Suchen')) ?></span><?= icon('magnifying-glass', ['class' => 'net-search__ico']) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Firma, E-Mail, Name …')) ?>"></label></form>
</div>

<?php if (!$rows): ?>
<section class="adm-card avv-empty"><p><?= e($q !== '' || $filter !== '' ? __('Keine Vereinbarung passt zum Filter.') : __('Noch keine Vereinbarungen. Laden Sie Kunden ein – oder setzen Sie den Block „AVV abschließen“ auf eine Seite der Website.')) ?></p></section>
<?php else: ?>
<div class="adm-card avv-table-card"><table class="adm-table avv-table">
  <thead><tr><th scope="col"><?= e(__('Kunde')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><?= e(__('Fassung')) ?></th><th scope="col"><?= e(__('Datum')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktionen')) ?></span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): [$sl, $st] = $status[$c['status']] ?? [$c['status'], 'muted']; $old = $c['status'] === 'signed' && $current && (int) $c['version_id'] !== (int) $current['id']; ?>
    <tr><td><a class="avv-row__name" href="<?= e(url('/admin/avv/' . $c['id'])) ?>"><?= e($c['company'] ?: ($c['contact'] ?: $c['email'])) ?></a><br><small class="adm-muted"><?= e($c['email']) ?><?= $c['customer_no'] !== '' ? ' · ' . e(__('Nr. {n}', ['n' => $c['customer_no']])) : '' ?></small></td>
      <td><span class="adm-badge adm-badge--<?= e($st) ?>"><?= e($sl) ?></span><?php if ($old): ?> <span class="adm-badge adm-badge--warn" title="<?= e(__('Ältere Fassung')) ?>"><?= e(__('ältere Fassung')) ?></span><?php endif; ?></td>
      <td><?= (int) ($c['version_number'] ?? 0) ?></td>
      <td><?= e($c['status'] === 'open' ? __('eingeladen {date}', ['date' => $date($c['invited_at'])]) : $date($c['signed_at'])) ?></td>
      <td class="adm-actions"><?php if ($c['pdf_file'] !== ''): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/avv/' . $c['id'] . '/pdf')) ?>"><?= icon('download-simple') ?> PDF</a><?php endif; ?>
        <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/avv/' . $c['id'])) ?>"><?= e(__('Öffnen')) ?></a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>

<section class="adm-card avv-invite" id="einladen" aria-labelledby="avv-inv-h">
  <h2 id="avv-inv-h"><?= e(__('Kunden einladen')) ?></h2>
  <p class="adm-muted"><?= e(__('Der Kunde erhält einen persönlichen Link ({n} Tage gültig), füllt die Vereinbarung aus und unterzeichnet. Sie bekommen das PDF per E-Mail und finden es hier.', ['n' => \Klxm\Avv\Avv::INVITE_DAYS])) ?></p>
  <form method="post" action="<?= e(url('/admin/avv/einladen')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="adm-fields"><?= Fields::renderForm(AdminController::inviteFields(), $invite, [], 'f') ?></div>
    <button class="adm-btn adm-btn--primary"><?= icon('paper-plane-tilt') ?> <?= e(__('Einladung senden')) ?></button>
  </form>
</section>
