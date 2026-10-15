/*
 * AVV-Assistent (Erweiterung avv): Schritte einzeln zeigen, Pflichtfelder je Schritt prüfen, Platzhalter im Vertragstext live
 * füllen ([data-avv-bind] → [data-avv-ph]), Anlage-A-Auswahl in der Vorschau, Unterschrift auf der Zeichenfläche (Zeiger-
 * Ereignisse: Maus, Finger, Stift) → PNG im Feld sig_png. Frisches Prüf-Token beim Laden (Block steht auf gecachten Seiten).
 * Ohne Skript: alle Schritte untereinander, Unterschrift mit Namen.
 */
(() => {
  const root = document.querySelector('[data-avv]');
  if (!root || root.dataset.ready) return;
  root.dataset.ready = '1';
  const form = root.querySelector('[data-avv-form]');
  const steps = [...root.querySelectorAll('[data-avv-step]')];
  const navList = root.querySelector('[data-avv-steps]');
  const submit = root.querySelector('[data-avv-submit]');
  const prev = root.querySelector('[data-avv-prev]');
  const next = root.querySelector('[data-avv-next]');

  // Frisches Prüf-Token und – falls auf der Website eingeschaltet – Rechenaufgabe (Core\SpamGuard, wie die Formulare des Kerns)
  const enc = new TextEncoder();
  const solve = async (token, bits) => {
    if (!bits || !crypto.subtle) return '';
    const full = bits >> 3, mask = 0xff << (8 - (bits & 7)) & 0xff;
    for (let n = 0; ; n += 256) {
      const res = await Promise.all(Array.from({ length: 256 }, (_, i) => crypto.subtle.digest('SHA-256', enc.encode(token + ':' + (n + i)))));
      for (let i = 0; i < 256; i++) {
        const b = new Uint8Array(res[i]);
        if (b.subarray(0, full).every(x => !x) && !(b[full] & mask)) return String(n + i);
      }
    }
  };
  const tokenEl = root.querySelector('[data-avv-token]'), powEl = root.querySelector('[data-avv-pow]');
  const ready = fetch(root.dataset.tokenUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(r => r.ok ? r.json() : null)
    .then(async d => { if (d && d.token) { tokenEl.value = d.token; powEl.value = await solve(d.token, +d.difficulty || 0); } })
    .catch(() => {});

  // ---------------------------------------------------------------- Schritte
  let cur = 0;
  const firstError = steps.findIndex(s => s.querySelector('[aria-invalid="true"]'));
  if (firstError > 0) cur = firstError;
  const show = (i, focus) => {
    cur = Math.max(0, Math.min(steps.length - 1, i));
    steps.forEach((s, k) => { s.hidden = k !== cur; });
    navList.querySelectorAll('li').forEach((li, k) => {
      li.classList.toggle('is-done', k < cur);
      if (k === cur) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
    prev.hidden = cur === 0;
    next.hidden = cur === steps.length - 1;
    submit.hidden = cur !== steps.length - 1;
    if (focus) {
      const leg = steps[cur].querySelector('legend');
      leg.tabIndex = -1; leg.focus({ preventScroll: true });
      root.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
    if (steps[cur].querySelector('[data-avv-canvas]')) sizeCanvas();
  };
  const valid = (step) => {
    for (const el of step.querySelectorAll('input[required]')) {
      if (el.closest('[hidden]')) continue;
      if (!el.checkValidity()) { el.reportValidity(); el.focus(); return false; }
    }
    return true;
  };
  navList.hidden = false;
  next.addEventListener('click', () => { if (valid(steps[cur])) show(cur + 1, true); });
  prev.addEventListener('click', () => show(cur - 1, true));
  form.addEventListener('keydown', e => {   // Enter in Textfeldern: weiter statt absenden
    if (e.key === 'Enter' && e.target.matches('input:not([type=checkbox]):not([type=radio])') && cur < steps.length - 1) { e.preventDefault(); next.click(); }
  });

  // ---------------------------------------------------------------- Live-Vorschau
  const fill = () => {
    root.querySelectorAll('[data-avv-bind]').forEach(inp => {
      const v = inp.value.trim();
      root.querySelectorAll(`[data-avv-ph="${inp.dataset.avvBind}"]`).forEach(ph => {
        if (!ph.dataset.empty) ph.dataset.empty = ph.textContent;
        ph.textContent = v || ph.dataset.empty;
        ph.classList.toggle('is-filled', !!v);
      });
    });
    root.querySelectorAll('ul[data-avv-list]').forEach(ul => {
      const key = ul.dataset.avvList;
      const items = [...root.querySelectorAll(`input[data-avv-list="${key}"]:checked`)].map(c => c.value);
      const other = root.querySelector(`[data-avv-other="${key}"]`)?.value.trim();
      if (other) items.push(other);
      ul.replaceChildren(...(items.length ? items : [ul.dataset.empty || '…']).map(t => { const li = document.createElement('li'); li.textContent = t; return li; }));
    });
  };
  root.querySelectorAll('ul[data-avv-list]').forEach(ul => { ul.dataset.empty = ul.textContent.trim(); });
  form.addEventListener('input', fill);
  form.addEventListener('change', fill);
  fill();

  // ---------------------------------------------------------------- Unterschrift
  const pad = root.querySelector('[data-avv-pad]');
  const canvas = root.querySelector('[data-avv-canvas]');
  const png = root.querySelector('[data-avv-png]');
  const ctx = canvas.getContext('2d');
  let drawing = false, last = null, inked = false;
  function sizeCanvas() {
    const w = canvas.clientWidth;
    if (!w || canvas.dataset.w === String(w)) return;
    const ratio = Math.max(1, window.devicePixelRatio || 1);
    canvas.width = w * ratio; canvas.height = 180 * ratio; canvas.dataset.w = String(w);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#13213c';
    inked = false; png.value = '';
  }
  const pos = e => { const r = canvas.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
  canvas.addEventListener('pointerdown', e => { drawing = true; last = pos(e); canvas.setPointerCapture(e.pointerId); e.preventDefault(); });
  canvas.addEventListener('pointermove', e => {
    if (!drawing) return;
    const p = pos(e);
    ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(p[0], p[1]); ctx.stroke();
    last = p; inked = true;
  });
  const end = () => { if (!drawing) return; drawing = false; if (inked) png.value = canvas.toDataURL('image/png'); };
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointercancel', end);
  root.querySelector('[data-avv-clear]').addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); inked = false; png.value = ''; });
  root.querySelectorAll('[data-avv-sigkind]').forEach(r => r.addEventListener('change', () => {
    pad.hidden = root.querySelector('[data-avv-sigkind]:checked')?.value !== 'drawn';
    if (!pad.hidden) sizeCanvas();
  }));
  form.addEventListener('submit', e => {
    const drawn = root.querySelector('[data-avv-sigkind]:checked')?.value === 'drawn';
    if (drawn && !png.value) { e.preventDefault(); show(steps.length - 1, false); pad.classList.add('is-missing'); canvas.focus(); return; }
    if (!valid(steps[cur])) { e.preventDefault(); return; }
    for (let i = 0; i < steps.length; i++) if (!valid(steps[i])) { e.preventDefault(); show(i, false); valid(steps[i]); return; }
    submit.disabled = true;
    if (!form.dataset.solved) {   // Rechenaufgabe abwarten, dann wirklich absenden
      e.preventDefault();
      ready.then(() => { form.dataset.solved = '1'; form.submit(); });
    }
  });

  show(cur, false);
})();
