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

  document.querySelectorAll('[data-confirm-action]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirmAction || 'Da li ste sigurni?')) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-confirm-open]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm('Da li ste spremni da otvorite svoj Mystery Box? Link važi samo za jedno otvaranje.')) {
        event.preventDefault();
        return;
      }
      const button = form.querySelector('button[type="submit"]');
      if (button) {
        button.dataset.label = button.textContent;
        button.disabled = true;
        button.textContent = 'Otvaram...';
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
  if (celebrateShell) {
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
    document.querySelectorAll('[data-action-form] button[type="submit"], form[data-confirm-open] button[type="submit"]').forEach((button) => {
      button.disabled = false;
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
