<section class="panel narrow">
  <p class="eyebrow">Mystery Box</p>
  <h1><?= e($title ?? 'Link nije dostupan') ?></h1>
  <p class="lead"><?= e($reason ?? 'Ovaj link trenutno nije moguće koristiti.') ?></p>
  <?php if (!empty($expired)): ?>
    <div class="invalid-help">
      <p><strong>Ako ste nagradu već prijavili,</strong> ne morate ništa da radite. Naš tim će Vas kontaktirati tokom radnog vremena.</p>
      <p><strong>Ako niste,</strong> javite nam se i navedite Draw ID sa PDF potvrde ili poruke.</p>
      <div class="contact-icons contact-icons-inline">
        <?php require app_path('resources/views/partials/contact-icons.php'); ?>
      </div>
    </div>
  <?php endif; ?>
  <a class="primary" href="/faq">Pogledaj FAQ</a>
</section>
