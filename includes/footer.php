<?php // includes/footer.php ?>
<footer class="eg-footer">
  <p>
    © <?= date('Y') ?> Institución Educativa Dinamarca — Sistema de Seguimiento a Egresados<br>
    <small>Sede Principal: Calle 91 # 65-119 &nbsp;|&nbsp; Tel: 604 257 3923 &nbsp;|&nbsp; iedinamarca@dinamarca.edu.co</small>
  </p>
</footer>
<script>
// Tabs genéricos
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const grupo = btn.closest('[data-tabs]') || btn.parentElement.parentElement;
    grupo.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));
    grupo.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
    btn.classList.add('activo');
    const panel = document.getElementById(btn.dataset.tab);
    if (panel) panel.classList.add('activo');
  });
});

// Modal genérico
document.querySelectorAll('[data-modal-abrir]').forEach(btn => {
  btn.addEventListener('click', () => {
    const modal = document.getElementById(btn.dataset.modalAbrir);
    if (modal) modal.classList.add('abierto');
  });
});
document.querySelectorAll('.modal-cerrar, [data-modal-cerrar]').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.modal-overlay').classList.remove('abierto');
  });
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('abierto');
  });
});

// Confirmaciones de eliminación
document.querySelectorAll('[data-confirmar]').forEach(btn => {
  btn.addEventListener('click', e => {
    if (!confirm(btn.dataset.confirmar || '¿Está seguro?')) e.preventDefault();
  });
});

// Auto-ocultar alertas
setTimeout(() => {
  document.querySelectorAll('.alerta').forEach(a => {
    a.style.transition = 'opacity .5s';
    a.style.opacity    = '0';
    setTimeout(() => a.remove(), 500);
  });
}, 5000);
</script>
</body>
</html>
