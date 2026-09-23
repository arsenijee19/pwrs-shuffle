(() => {
  const formattedMessage = (link) => `Poštovani,

u prilogu vam šaljemo Vaš jedinstveni link za PlayWorld Mystery Box:

${link}

Molimo Vas da otvorite link i ispratite instrukcije prikazane na stranici. Mystery Box možete otvoriti samo jednom, a rezultat nakon otvaranja nije moguće promeniti.

Nakon što otvorite Mystery Box, molimo Vas da nam pošaljete:

- screenshot osvojene nagrade, ili
- PDF potvrdu koju možete preuzeti na stranici rezultata.

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
    button.addEventListener('click', async () => {
      try {
        await copyToClipboard(button.dataset.copy || '');
        markCopied(button, 'Kopiraj link', 'Link kopiran');
      } catch (error) {
        button.textContent = 'Copy failed';
        setTimeout(() => { button.textContent = 'Kopiraj'; }, 1600);
      }
    });
  });

  document.querySelectorAll('[data-copy-formatted]').forEach((button) => {
    button.addEventListener('click', async () => {
      const link = button.dataset.copyFormatted || '';
      const message = formattedMessage(link);

      try {
        await copyToClipboard(message);
        markCopied(button, 'Copy formatted', 'Poruka kopirana');
      } catch (error) {
        button.textContent = 'Copy failed';
        setTimeout(() => { button.textContent = 'Copy formatted'; }, 1800);
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
        button.textContent = `Copied ${links.length}`;
        document.querySelectorAll('[data-copy-row]').forEach((row) => {
          row.classList.add('copied-row');
          const status = row.querySelector('[data-copy-status]');
          if (status) {
            status.textContent = 'Uključeno u bulk kopiranje';
          }
        });
      } catch (error) {
        button.textContent = 'Copy failed';
      }
    });
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
      if (!window.confirm('Ovu Mystery Box nagradu možete otvoriti samo jednom.')) {
        event.preventDefault();
        return;
      }
      const button = form.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Opening...';
      }
    });
  });

  document.querySelectorAll('[data-prize-id][data-field]').forEach((input) => {
    const original = () => input.dataset.currentValue || '';
    let timer;
    const state = input.parentElement?.querySelector('[data-inline-state]');
    const setState = (value, className = '') => {
      if (!state) return;
      state.textContent = value;
      state.className = `inline-save-state ${className}`;
    };
    const save = async () => {
      const value = input.value.trim();
      if (value === original()) return;
      input.disabled = true;
      setState('Čuvam...', 'is-saving');
      const form = new FormData();
      form.append('_csrf', document.querySelector('input[name="_csrf"]')?.value || '');
      form.append('field', input.dataset.field || '');
      form.append('value', value);
      try {
        const response = await fetch(`/admin/prizes/${encodeURIComponent(input.dataset.prizeId || '')}/inline`, {
          method: 'POST', body: form, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Izmena nije sačuvana.');
        input.dataset.currentValue = value;
        setState('Sačuvano', 'is-saved');
        setTimeout(() => setState(''), 1800);
      } catch (error) {
        input.value = original();
        setState(error.message || 'Greška', 'is-error');
      } finally {
        input.disabled = false;
      }
    };
    input.addEventListener('change', save);
    input.addEventListener('blur', () => { clearTimeout(timer); timer = setTimeout(save, 80); });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') { event.preventDefault(); input.blur(); }
      if (event.key === 'Escape') { input.value = original(); input.blur(); }
    });
  });

  const soundButton = document.querySelector('[data-sound-toggle]');
  if (soundButton) {
    let muted = localStorage.getItem('pw-sound-muted') === '1';
    soundButton.textContent = muted ? 'Sound off' : 'Sound on';
    soundButton.addEventListener('click', () => {
      muted = !muted;
      localStorage.setItem('pw-sound-muted', muted ? '1' : '0');
      soundButton.textContent = muted ? 'Sound off' : 'Sound on';
    });

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
