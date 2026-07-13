(() => {
  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      await navigator.clipboard.writeText(button.dataset.copy || '');
      button.textContent = 'Kopirano';
      setTimeout(() => { button.textContent = 'Kopiraj'; }, 1200);
    });
  });

  document.querySelectorAll('[data-copy-formatted]').forEach((button) => {
    button.addEventListener('click', async () => {
      const link = button.dataset.copyFormatted || '';
      const message = `Poštovani,

u prilogu vam šaljemo Vaš jedinstveni link za PlayWorld Mystery Box:

*${link}*

Molimo Vas da otvorite link i ispratite instrukcije prikazane na stranici. Mystery Box možete otvoriti samo jednom, a rezultat nakon otvaranja nije moguće promeniti.

Nakon što otvorite Mystery Box, molimo Vas da nam pošaljete:

- screenshot osvojene nagrade, ili
- PDF potvrdu koju možete preuzeti na stranici rezultata.

Hvala Vam na učešću i želimo Vam puno sreće!

Srdačno,
PlayWorld tim`;

      await navigator.clipboard.writeText(message);
      button.textContent = 'Kopirano';
      setTimeout(() => { button.textContent = 'Copy formatted'; }, 1400);
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
