<?php
// "Testimonios": carrusel que se mueve con las flechas (lo maneja aqua.js).
$testimonios = [
    ['name' => 'Martina Silva', 'role' => 'Acuario plantado, CABA', 'quote' => 'Ahora puedo viajar sin preocuparme por mi acuario.', 'avatar' => 'MS'],
    ['name' => 'Nicolas Rivas', 'role' => 'Acuarista principiante', 'quote' => 'El sistema me alerto antes de que el agua se contaminara.', 'avatar' => 'NR'],
    ['name' => 'Laura Medina', 'role' => 'Criadora de bettas', 'quote' => 'La vista del panel es clara, rapida y me evita revisar sensores a mano.', 'avatar' => 'LM'],
];
?>
<section class="landing-section testimonial-section">
  <div class="container">
    <div class="section-heading" data-reveal>
      <p class="eyebrow">Testimonios</p>
      <h2>Usuarios que dejaron de adivinar y empezaron a medir.</h2>
    </div>

    <div class="testimonial-shell" data-testimonial-carousel data-reveal>
      <div class="testimonial-track">
        <?php foreach ($testimonios as $testimonial): ?>
          <article class="testimonial-card">
            <div class="avatar-photo" aria-hidden="true"><?= esc($testimonial['avatar']) ?></div>
            <blockquote>&ldquo;<?= $testimonial['quote'] ?>&rdquo;</blockquote>
            <strong><?= esc($testimonial['name']) ?></strong>
            <span><?= esc($testimonial['role']) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="testimonial-controls" aria-label="Controles de testimonios">
        <button type="button" data-testimonial-prev aria-label="Testimonio anterior">&lsaquo;</button>
        <button type="button" data-testimonial-next aria-label="Testimonio siguiente">&rsaquo;</button>
      </div>
    </div>
  </div>
</section>
