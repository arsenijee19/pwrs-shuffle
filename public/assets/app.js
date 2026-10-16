(() => {
  const formattedMessage = (link) => `Poštovani,

u prilogu Vam šaljemo Vaš jedinstveni link za PlayWorld Mystery Box:

${link}

Molimo Vas da otvorite link i ispratite instrukcije prikazane na stranici. Mystery Box možete otvoriti samo jednom, a rezultat nakon otvaranja nije moguće promeniti.

Ako osvojite igru ili poklon, kliknite „Preuzmite nagradu“ na stranici rezultata i ostavite email i telefon. Screenshot nije potreban: naš tim će Vas kontaktirati tokom radnog vremena radi isporuke.

Hvala Vam na učešću i želimo Vam puno sreće!

Srdačno,
PlayWorld tim`;

  const copyToClipboard = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
  };

  const markCopied = (button, label, type = 'Kopirano') => {
    const row = button.closest('[data-copy-row]');
    const status = row ? row.querySelector('[data-copy-status]') : null;
    const time = new Date().toLocaleTimeString('sr-RS', { hour: '2-digit', minute: '2-digit' });

    button.textContent = type;
    button.classList.add('copied');
    if (row) {
      row.classList.add('copied-row');
    }
    if (status) {
      status.textContent = `${type} u ${time}`;
    }
  };

  document.querySelectorAll('[data-copy]').forEach((button) => {
    const original = button.textContent;
    button.addEventListener('click', async () => {
      try {
        await copyToClipboard(button.dataset.copy || '');
        markCopied(button, original, button.dataset.copiedLabel || 'Link kopiran');
      } catch (error) {
        button.textContent = 'Kopiranje nije uspelo';
        setTimeout(() => { button.textContent = original; }, 1800);
      }
    });
  });

  document.querySelectorAll('[data-copy-formatted]').forEach((button) => {
    button.addEventListener('click', async () => {
      const link = button.dataset.copyFormatted || '';
      const message = formattedMessage(link);

      try {
        await copyToClipboard(message);
        markCopied(button, 'Kopiraj poruku', 'Poruka kopirana');
      } catch (error) {
        button.textContent = 'Kopiranje nije uspelo';
        setTimeout(() => { button.textContent = 'Kopiraj poruku'; }, 1800);
      }
    });
  });

  document.querySelectorAll('[data-copy-all-formatted]').forEach((button) => {
    button.addEventListener('click', async () => {
      const links = [...document.querySelectorAll('[data-copy-formatted]')]
        .map((copyButton) => copyButton.dataset.copyFormatted || '')
        .filter(Boolean);

      if (!links.length) {
        return;
      }

      try {
        await copyToClipboard(links.map(formattedMessage).join('\n\n---\n\n'));
        button.textContent = `Kopirano: ${links.length}`;
        document.querySelectorAll('[data-copy-row]').forEach((row) => {
          row.classList.add('copied-row');
          const status = row.querySelector('[data-copy-status]');
          if (status) {
            status.textContent = 'Uključeno u bulk kopiranje';
          }
        });
      } catch (error) {
        button.textContent = 'Kopiranje nije uspelo';
      }
    });
  });

  document.querySelectorAll('table').forEach((table) => {
    const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    const rows = [...table.querySelectorAll('tbody tr')];
    if (!heads.length || !rows.length || rows.some((row) => row.querySelector('td[colspan]') || row.children.length !== heads.length)) return;
    rows.forEach((row) => [...row.children].forEach((cell, index) => cell.setAttribute('data-label', heads[index])));
    table.classList.add('is-stacked');
  });

  const fieldNames = { rarity: 'Rarity', weight: 'Weight', max_quantity: 'Legacy max', bonus_box_count: 'Broj bonus boxova', discount_percent: 'Popust u procentima' };
  document.querySelectorAll('[data-prize-id][data-field]').forEach((input) => {
    if (input.getAttribute('aria-label')) return;
    const name = input.closest('tr')?.children[1]?.textContent.trim() || '';
    input.setAttribute('aria-label', `${fieldNames[input.dataset.field] || input.dataset.field}${name ? ': ' + name : ''}`);
  });

  document.querySelectorAll('input[name="discount_enabled"]').forEach((toggle) => {
    const percent = toggle.form?.querySelector('input[name="discount_percent"]');
    if (!percent) return;
    const sync = () => {
      percent.disabled = !toggle.checked;
      percent.required = toggle.checked;
      percent.closest('label')?.classList.toggle('is-disabled', !toggle.checked);
    };
    toggle.addEventListener('change', sync);
    sync();
  });

  // Branded confirmation dialog (replaces the browser's native confirm box).
  const confirmDialog = ({ title, message, confirmLabel = 'Potvrdite', cancelLabel = 'Odustanite', tone = 'primary', icon = 'help' }) => new Promise((resolve) => {
    const previous = document.activeElement;
    const icons = {
      gift: '<path d="M4 11h16v9H4zM3 7.500h18V11H3zM12 7.500V20M12 7.500C10.500 4 7 4.500 7 6.500c0 1.500 2 1 5 1Zm0 0c1.500-3.500 5-3 5-1 0 1.500-2 1-5 1Z" fill="none" stroke="currentColor" stroke-width="1.800" stroke-linejoin="round"/>',
      alert: '<path d="M12 4 3 19.500h18L12 4Zm0 6v4.500M12 17.200h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
      help: '<circle cx="12" cy="12" r="8.500" fill="none" stroke="currentColor" stroke-width="1.900"/><path d="M9.700 9.600a2.400 2.400 0 1 1 3.500 2.100c-.7.400-1.200.9-1.200 1.700M12 16.600h.01" fill="none" stroke="currentColor" stroke-width="1.900" stroke-linecap="round"/>',
    };
    const root = document.createElement('div');
    root.className = 'dialog';
    root.innerHTML = `
      <div class="dialog-backdrop" data-dialog-cancel></div>
      <div class="dialog-card dialog-${tone}" role="alertdialog" aria-modal="true" aria-labelledby="dialog-title" aria-describedby="dialog-message">
        <span class="dialog-icon" aria-hidden="true"><svg viewBox="0 0 24 24">${icons[icon] || icons.help}</svg></span>
        <h2 id="dialog-title"></h2>
        <p id="dialog-message"></p>
        <div class="dialog-actions">
          <button type="button" class="ghost" data-dialog-cancel></button>
          <button type="button" class="${tone === 'danger' ? 'danger' : 'primary'}" data-dialog-confirm></button>
        </div>
      </div>`;
    root.querySelector('#dialog-title').textContent = title;
    root.querySelector('#dialog-message').textContent = message;
    root.querySelector('.ghost').textContent = cancelLabel;
    const confirmButton = root.querySelector('[data-dialog-confirm]');
    confirmButton.textContent = confirmLabel;
    document.body.appendChild(root);
    document.body.classList.add('dialog-open');
    requestAnimationFrame(() => {
      root.classList.add('is-open');
      void root.offsetWidth; // make the dialog rendered before moving focus into it
      (tone === 'danger' ? root.querySelector('.ghost') : confirmButton).focus({ preventScroll: true });
    });

    const close = (result) => {
      document.removeEventListener('keydown', onKey, true);
      root.classList.remove('is-open');
      document.body.classList.remove('dialog-open');
      setTimeout(() => root.remove(), 220);
      if (previous && previous.focus) previous.focus({ preventScroll: true });
      resolve(result);
    };
    const onKey = (event) => {
      if (event.key === 'Escape') { event.preventDefault(); close(false); }
      if (event.key === 'Tab') {
        const focusable = [...root.querySelectorAll('button')];
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    };
    document.addEventListener('keydown', onKey, true);
    root.querySelectorAll('[data-dialog-cancel]').forEach((el) => el.addEventListener('click', () => close(false)));
    confirmButton.addEventListener('click', () => close(true));
  });

  document.querySelectorAll('[data-confirm-action]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      if (form.dataset.confirmed === '1') return;
      event.preventDefault();
      const ok = await confirmDialog({
        title: 'Potvrdite radnju',
        message: form.dataset.confirmAction || 'Da li ste sigurni?',
        confirmLabel: form.dataset.confirmLabel || 'Da, nastavite',
        tone: 'danger',
        icon: 'alert',
      });
      if (ok) {
        form.dataset.confirmed = '1';
        form.submit();
      }
    });
  });

  // Small playful pieces that pop out of the box (generic shapes, no brand logos).
  const gameItems = [
    '<svg viewBox="0 0 48 48"><path d="M13 14h22c5.500 0 8.500 4.500 9.500 10.500l1.800 10c.5 3-1.300 5.500-4.300 5.500-2.300 0-3.700-1.200-5.300-3.200L33 33.500H15l-3.700 3.300c-1.600 2-3 3.200-5.300 3.200-3 0-4.800-2.500-4.300-5.500l1.800-10C4.500 18.500 7.500 14 13 14Z" fill="#f1f3ff" stroke="#8a93d6" stroke-width="2"/><path d="M13 20v8M9 24h8" stroke="#3a3f7a" stroke-width="3" stroke-linecap="round"/><circle cx="33" cy="21.500" r="2.300" fill="#ff5a5f"/><circle cx="37.500" cy="26" r="2.300" fill="#3ddc97"/><circle cx="28.500" cy="26" r="2.300" fill="#5aa7ff"/><circle cx="33" cy="30.500" r="2.300" fill="#ffd36b"/></svg>',
    '<svg viewBox="0 0 48 48"><rect x="8" y="35" width="32" height="8" rx="3" fill="#3a3f7a"/><rect x="21.500" y="17" width="5" height="19" rx="2" fill="#c9cef2"/><circle cx="24" cy="12" r="8" fill="#ff5a5f"/><circle cx="21" cy="9" r="2.500" fill="#fff" opacity=".7"/><circle cx="14" cy="39" r="1.800" fill="#3ddc97"/><circle cx="34" cy="39" r="1.800" fill="#ffd36b"/></svg>',
    '<svg viewBox="0 0 48 48"><path d="M24 9 40 37H8Z" fill="none" stroke="#3ddc97" stroke-width="6" stroke-linejoin="round"/></svg>',
    '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="14" fill="none" stroke="#ff5a5f" stroke-width="6"/></svg>',
    '<svg viewBox="0 0 48 48"><path d="M11 11l26 26M37 11 11 37" stroke="#5aa7ff" stroke-width="6" stroke-linecap="round"/></svg>',
    '<svg viewBox="0 0 48 48"><rect x="11" y="11" width="26" height="26" rx="3" fill="none" stroke="#ff8fd2" stroke-width="6"/></svg>',
    '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="18" fill="#3ddc97"/><text x="24" y="31.500" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="900" fill="#fff">A</text></svg>',
    '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="18" fill="#ff5a5f"/><text x="24" y="31.500" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="900" fill="#fff">B</text></svg>',
    '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="18" fill="#5aa7ff"/><text x="24" y="31.500" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="900" fill="#fff">X</text></svg>',
    '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="18" fill="#ffd36b"/><text x="24" y="31.500" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="900" fill="#fff">Y</text></svg>',
    '<svg viewBox="0 0 48 48"><path d="M18 6h12v12h12v12H30v12H18V30H6V18h12Z" fill="#3a3f7a" stroke="#c9cef2" stroke-width="2.500" stroke-linejoin="round"/><path d="M21 12h6" stroke="#8a93d6" stroke-width="3" stroke-linecap="round"/></svg>',
    '<svg viewBox="0 0 48 48"><path d="m24 4 5.500 12.500L43 18l-10 9.200 2.800 13.300L24 33.500 12.200 40.500 15 27.200 5 18l13.500-1.500Z" fill="#ffd36b" stroke="#f1a93a" stroke-width="2.500" stroke-linejoin="round"/></svg>',
    '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="17" fill="#ffd36b" stroke="#f1a93a" stroke-width="3"/><circle cx="24" cy="24" r="11" fill="none" stroke="#f1a93a" stroke-width="2.500"/><path d="m24 16 2.400 5 5.400.7-4 3.700 1 5.300L24 28l-4.800 2.700 1-5.300-4-3.700 5.400-.7Z" fill="#fff4cf"/></svg>',
    '<svg viewBox="0 0 48 48"><path d="M24 41C10 31 6 24 6 17.500 6 12 10 8.500 15 8.500c3.700 0 6.600 2 9 5.500 2.400-3.500 5.300-5.500 9-5.500 5 0 9 3.500 9 9 0 6.500-4 13.500-18 23.500Z" fill="#ff6b8e" stroke="#e0436b" stroke-width="2.500" stroke-linejoin="round"/></svg>',
    '<svg viewBox="0 0 48 48"><path d="M9 28v-4a15 15 0 0 1 30 0v4" fill="none" stroke="#c9cef2" stroke-width="4" stroke-linecap="round"/><rect x="5" y="25" width="9" height="15" rx="4" fill="#7a4fe3"/><rect x="34" y="25" width="9" height="15" rx="4" fill="#7a4fe3"/></svg>',
  ];

  // Full-screen "unwrap" reveal that plays while the box is opened on the server.
  const playUnwrap = (form) => {
    const TOTAL = 4700;
    const root = document.createElement('div');
    root.className = 'unwrap';
    root.setAttribute('role', 'status');
    root.setAttribute('aria-label', 'Otvaramo Vaš Mystery Box');
    root.innerHTML = `
      <div class="unwrap-rays"></div>
      <div class="unwrap-stage">
        <div class="unwrap-glow"></div>
        <div class="ug">
          <div class="ug-shake">
            <div class="ug-body"></div>
            <div class="ug-lid"></div>
            <div class="ug-ribbon-v"></div>
            <div class="ug-ribbon-h"></div>
            <div class="ug-paper ug-paper-l"></div>
            <div class="ug-paper ug-paper-r"></div>
            <div class="ug-bow"><i></i><i></i><b></b></div>
          </div>
        </div>
        <div class="unwrap-burst"></div>
        <div class="unwrap-sparks"></div>
        <div class="unwrap-fly"></div>
      </div>
      <p class="unwrap-caption">Otvaramo Vaš box<span>.</span><span>.</span><span>.</span></p>
      <button type="button" class="unwrap-skip">Preskočite</button>
      <div class="unwrap-flash"></div>`;

    const sparks = root.querySelector('.unwrap-sparks');
    const palette = ['#ffd36b', '#ffffff', '#8fe3c4', '#b69cff', '#ff9a8a', '#7fc4ff'];
    for (let i = 0; i < 22; i += 1) {
      const angle = (i / 22) * Math.PI * 2 + Math.random() * 0.35;
      const distance = 130 + Math.random() * 150;
      const spark = document.createElement('i');
      spark.style.setProperty('--x', `${Math.cos(angle) * distance}px`);
      spark.style.setProperty('--y', `${Math.sin(angle) * distance}px`);
      spark.style.setProperty('--c', palette[i % palette.length]);
      spark.style.setProperty('--d', `${(Math.random() * 0.25).toFixed(2)}s`);
      sparks.appendChild(spark);
    }

    const fly = root.querySelector('.unwrap-fly');
    const reach = Math.min(window.innerWidth * 0.42, 190);
    const order = gameItems.map((svg, index) => ({ svg, index })).sort(() => Math.random() - 0.5);
    for (let i = 0; i < 18; i += 1) {
      const item = order[i % order.length];
      const piece = document.createElement('span');
      piece.className = 'fly';
      piece.style.setProperty('--x', `${(Math.random() * 2 - 1) * reach}px`);
      piece.style.setProperty('--up', `${-(150 + Math.random() * 200)}px`);
      piece.style.setProperty('--down', `${70 + Math.random() * 120}px`);
      piece.style.setProperty('--r', `${(Math.random() * 2 - 1) * 540}deg`);
      piece.style.setProperty('--t', `${(1.45 + Math.random() * 0.5).toFixed(2)}s`);
      piece.style.setProperty('--dl', `${(2.85 + i * 0.05).toFixed(2)}s`);
      piece.innerHTML = `<span class="fly-y"><span class="fly-spin">${item.svg}</span></span>`;
      fly.appendChild(piece);
    }

    document.body.appendChild(root);
    document.body.classList.add('dialog-open');
    requestAnimationFrame(() => {
      root.classList.add('is-open');
      void root.offsetWidth;
      root.classList.add('is-playing');
    });

    const data = new FormData(form);
    data.set('animated', '1');
    const request = fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then((response) => (response.ok ? response.json() : null))
      .then((payload) => (payload && payload.ok ? payload.url : null))
      .catch(() => null);

    let skipped = false;
    const skipButton = root.querySelector('.unwrap-skip');
    const skipSignal = new Promise((resolve) => skipButton.addEventListener('click', () => { skipped = true; root.classList.add('is-skipped'); resolve(); }));
    const timer = new Promise((resolve) => setTimeout(resolve, TOTAL));

    let failed = false;
    const fail = () => {
      if (failed) return;
      failed = true;
      root.remove();
      document.body.classList.remove('dialog-open');
      form.dataset.confirmed = '1';
      form.submit(); // let the server show its regular, readable error page
    };
    request.then((url) => { if (!url) fail(); });

    Promise.all([request, Promise.race([timer, skipSignal])]).then(async ([url]) => {
      if (failed || !url) return;
      if (skipped) await new Promise((resolve) => setTimeout(resolve, 260));
      window.location.assign(url);
    });
  };

  document.querySelectorAll('[data-confirm-open]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      if (form.dataset.confirmed === '1') return;
      event.preventDefault();
      const ok = await confirmDialog({
        title: 'Spremni ste da otvorite box?',
        message: 'Svaki link važi samo za jedno otvaranje. Rezultat se odmah čuva i nije ga moguće promeniti.',
        confirmLabel: 'Otvorite box',
        cancelLabel: 'Još ne',
        icon: 'gift',
      });
      if (!ok) return;
      form.dataset.confirmed = '1';
      const button = form.querySelector('button[type="submit"]');
      if (button) {
        button.dataset.label = button.textContent;
        button.disabled = true;
        button.textContent = 'Otvaram...';
      }
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        form.submit();
      } else {
        playUnwrap(form);
      }
    });
  });

  document.querySelectorAll('[data-contact-toggle]').forEach((button) => {
    const panel = document.getElementById(button.getAttribute('aria-controls') || '');
    const label = button.querySelector('[data-contact-label]') || button;
    if (!panel) return;

    const links = () => panel.querySelectorAll('a');
    links().forEach((link) => link.setAttribute('tabindex', '-1'));

    let closeTimer;
    button.addEventListener('click', () => {
      const open = !panel.classList.contains('is-visible');
      clearTimeout(closeTimer);
      button.setAttribute('aria-expanded', String(open));
      label.textContent = open ? 'Zatvorite kontakt' : 'Kontaktirajte nas';
      panel.setAttribute('aria-hidden', String(!open));
      links().forEach((link) => (open ? link.removeAttribute('tabindex') : link.setAttribute('tabindex', '-1')));

      if (open) {
        // Layout changes once, instantly; afterwards only transform/opacity are animated.
        panel.classList.add('is-open');
        void panel.offsetWidth;
        panel.classList.add('is-visible');
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        panel.classList.remove('is-visible');
        closeTimer = setTimeout(() => panel.classList.remove('is-open'), 220);
      }
    });
  });

  document.querySelectorAll('form[data-prize-save]').forEach((form) => {
    let submitted = false;
    form.addEventListener('submit', (event) => {
      if (submitted) {
        event.preventDefault();
        return;
      }

      submitted = true;
      const button = form.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Čuvam nagradu...';
      }
    });
  });

  document.querySelectorAll('[data-code-import]').forEach((textarea) => {
    const counter = textarea.parentElement?.querySelector('[data-code-count]');
    const updateCount = () => {
      if (!counter) return;
      const count = textarea.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).length;
      counter.textContent = `${count} ${count === 1 ? 'kod' : 'kodova'} spremno za unos`;
    };
    textarea.addEventListener('input', updateCount);
    updateCount();
  });

  document.querySelectorAll('[data-prize-id][data-field]').forEach((input) => {
    const original = () => input.dataset.currentValue || '';
    let timer;
    let saving = false;
    const state = input.parentElement?.querySelector('[data-inline-state]');
    const setState = (value, className = '') => {
      if (!state) return;
      state.textContent = value;
      state.className = `inline-save-state ${className}`;
    };
    const syncRow = (prize) => {
      if (!prize) return;
      const rawValue = prize[input.dataset.field || ''];
      const savedValue = rawValue === null || rawValue === undefined ? '' : String(rawValue);
      input.value = savedValue;
      input.dataset.currentValue = savedValue;

    };
    const save = async () => {
      if (saving) return;
      const value = input.value.trim();
      if (value === original()) return;
      saving = true;
      input.disabled = true;
      setState('Čuvam...', 'is-saving');
      const form = new FormData();
      const row = input.closest('tr');
      const csrf = row?.querySelector('input[name="_csrf"]') || document.querySelector('input[name="_csrf"]');
      form.append('_csrf', csrf?.value || '');
      form.append('field', input.dataset.field || '');
      form.append('value', value);
      try {
        const response = await fetch(`/admin/prizes/${encodeURIComponent(input.dataset.prizeId || '')}/inline`, {
          method: 'POST', body: form, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        });
        const body = await response.text();
        let data;
        try {
          data = JSON.parse(body);
        } catch {
          throw new Error('Sesija je istekla ili server nije vratio validan odgovor. Osvežite stranicu.');
        }
        if (!response.ok || !data.ok) throw new Error(data.error || 'Izmena nije sačuvana.');
        syncRow(data.prize);
        setState('Sačuvano', 'is-saved');
        setTimeout(() => setState(''), 1800);
      } catch (error) {
        input.value = original();
        setState(error.message || 'Greška', 'is-error');
      } finally {
        saving = false;
        input.disabled = false;
      }
    };
    const scheduleSave = (delay = 120) => {
      clearTimeout(timer);
      timer = setTimeout(save, delay);
    };
    input.addEventListener('change', () => scheduleSave(0));
    input.addEventListener('blur', () => scheduleSave(160));
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        clearTimeout(timer);
        save();
      }
      if (event.key === 'Escape') {
        clearTimeout(timer);
        input.value = original();
        setState('');
        input.blur();
      }
    });
  });

  const drawer = document.querySelector('[data-drawer]');
  const navToggle = document.querySelector('[data-nav-toggle]');
  if (drawer && navToggle) {
    const closeButton = drawer.querySelector('.drawer-close');
    const setDrawer = (open) => {
      drawer.classList.toggle('is-open', open);
      drawer.setAttribute('aria-hidden', String(!open));
      navToggle.setAttribute('aria-expanded', String(open));
      document.body.classList.toggle('drawer-open', open);
      if (open) {
        setTimeout(() => closeButton?.focus({ preventScroll: true }), 60);
      } else if (drawer.contains(document.activeElement)) {
        navToggle.focus({ preventScroll: true });
      }
    };
    navToggle.addEventListener('click', () => setDrawer(!drawer.classList.contains('is-open')));
    drawer.querySelectorAll('[data-drawer-close]').forEach((el) => el.addEventListener('click', () => setDrawer(false)));
    drawer.querySelectorAll('.drawer-links a, .drawer-cta').forEach((el) => el.addEventListener('click', () => setDrawer(false)));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('is-open')) setDrawer(false);
    });
    window.matchMedia('(min-width: 761px)').addEventListener('change', (event) => {
      if (event.matches) setDrawer(false);
    });
    window.addEventListener('pageshow', () => setDrawer(false));
  }

  const topbar = document.querySelector('.topbar');
  if (topbar) {
    let lastY = window.scrollY;
    let ticking = false;
    const mobile = window.matchMedia('(max-width: 760px)');
    const update = () => {
      ticking = false;
      const y = window.scrollY;
      if (!mobile.matches || y < 90) {
        topbar.classList.remove('is-hidden');
      } else if (y > lastY + 8) {
        if (!topbar.contains(document.activeElement) && !document.body.classList.contains('drawer-open')) topbar.classList.add('is-hidden');
      } else if (y < lastY - 8) {
        topbar.classList.remove('is-hidden');
      }
      lastY = y;
    };
    window.addEventListener('scroll', () => {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(update);
      }
    }, { passive: true });
  }

  const celebrateShell = document.querySelector('.result-shell.celebrate');
  if (celebrateShell && celebrateShell.querySelector('.box-animation')) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    setTimeout(() => {
      const result = celebrateShell.querySelector('.result');
      if (!result || window.scrollY > 40) return;
      result.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }, 1700);
  }

  const focusActionCard = (card) => {
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    card.classList.remove('is-highlighted');
    void card.offsetWidth;
    card.classList.add('is-highlighted');
    const input = card.querySelector('input:not([type="hidden"])');
    if (input && !input.value) {
      setTimeout(() => input.focus({ preventScroll: true }), 550);
    }
  };

  document.querySelectorAll('[data-sticky-action]').forEach((bar) => {
    const target = document.getElementById(bar.dataset.target || '');
    if (!target) return;

    const celebrating = document.querySelector('.result-shell.celebrate');
    let targetVisible = false;
    let typing = false;
    const sync = () => bar.classList.toggle('is-away', targetVisible || typing);

    setTimeout(() => {
      bar.hidden = false;
      document.body.classList.add('has-sticky-action');
    }, celebrating ? 1500 : 300);

    if ('IntersectionObserver' in window) {
      const watched = target.querySelector('[data-action-form]') || target;
      new IntersectionObserver((entries) => {
        targetVisible = entries.some((entry) => entry.intersectionRatio >= 0.35);
        sync();
      }, { threshold: [0, 0.35, 0.7] }).observe(watched);
    }

    target.addEventListener('focusin', () => { typing = true; sync(); });
    target.addEventListener('focusout', () => { typing = false; sync(); });

    bar.querySelector('a')?.addEventListener('click', (event) => {
      event.preventDefault();
      focusActionCard(target);
    });
  });

  document.querySelectorAll('[data-action-form]').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      if (button) {
        button.dataset.label = button.textContent;
        setTimeout(() => {
          button.disabled = true;
          button.textContent = 'Šaljem...';
        }, 0);
      }
    });
  });

  // Coming back with the browser's back button must not leave a stuck "sending" state.
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('.unwrap').forEach((el) => el.remove());
    document.body.classList.remove('dialog-open');
    document.querySelectorAll('[data-action-form] button[type="submit"], form[data-confirm-open] button[type="submit"]').forEach((button) => {
      button.disabled = false;
      const owner = button.closest('form'); if (owner) delete owner.dataset.confirmed;
      if (button.dataset.label) button.textContent = button.dataset.label;
    });
  });

  const soundButton = document.querySelector('[data-sound-toggle]');
  if (soundButton) {
    let muted = localStorage.getItem('pw-sound-muted') === '1';
    const soundLabel = soundButton.querySelector('[data-sound-label]');
    const renderSound = () => {
      if (soundLabel) soundLabel.textContent = muted ? 'Zvuk isključen' : 'Zvuk uključen';
      soundButton.classList.toggle('is-muted', muted);
      soundButton.setAttribute('aria-pressed', String(!muted));
    };
    renderSound();
    soundButton.addEventListener('click', () => {
      muted = !muted;
      localStorage.setItem('pw-sound-muted', muted ? '1' : '0');
      renderSound();
    });

    if (!muted && celebrateShell && navigator.vibrate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setTimeout(() => navigator.vibrate([18, 40, 28]), 1000);
    }

    if (!muted && window.AudioContext && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setTimeout(() => {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(523.25, ctx.currentTime);
        osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.12);
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.38);
      }, 950);
    }
  }
})();
