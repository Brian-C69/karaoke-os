(() => {
  const KEY = 'kos-theme';

  const prefersDark = () => {
    try {
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    } catch {
      return false;
    }
  };

  const normalize = (v) => (v === 'dark' ? 'dark' : 'light');

  const loadTheme = () => {
    const saved = (localStorage.getItem(KEY) || '').toLowerCase();
    if (saved === 'light' || saved === 'dark') return saved;
    return prefersDark() ? 'dark' : 'light';
  };

  const setTheme = (theme) => {
    const t = normalize(theme);
    document.documentElement.setAttribute('data-bs-theme', t);
    try {
      localStorage.setItem(KEY, t);
    } catch {
      // ignore
    }
    return t;
  };

  const current = setTheme(loadTheme());

  const updateToggle = (btn, theme) => {
    if (!btn) return;
    const isDark = theme === 'dark';
    btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    btn.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    const icon = btn.querySelector('i');
    if (icon) icon.className = `bi ${isDark ? 'bi-sun-fill' : 'bi-moon-stars-fill'}`;
  };

  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('themeToggle');
    updateToggle(btn, current);
    btn?.addEventListener('click', () => {
      const now = normalize(document.documentElement.getAttribute('data-bs-theme'));
      const next = now === 'dark' ? 'light' : 'dark';
      const applied = setTheme(next);
      updateToggle(btn, applied);
    });
  });
})();

