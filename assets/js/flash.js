(() => {
  const AUTO_HIDE_MS = 3000;
  const HIDE_LEVELS = new Set(['alert-success', 'alert-info']);

  const shouldAutoHide = (el) => {
    if (!el || !(el instanceof HTMLElement)) return false;
    if (!el.classList.contains('alert')) return false;
    for (const cls of HIDE_LEVELS) {
      if (el.classList.contains(cls)) return true;
    }
    return false;
  };

  const hide = (el) => {
    el.style.transition = 'opacity 220ms ease';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 260);
  };

  window.addEventListener('load', () => {
    document.querySelectorAll('.alert').forEach((el) => {
      if (!shouldAutoHide(el)) return;
      setTimeout(() => {
        if (!document.body.contains(el)) return;
        hide(el);
      }, AUTO_HIDE_MS);
    });
  });
})();

