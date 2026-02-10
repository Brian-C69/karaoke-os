(() => {
  document.addEventListener('contextmenu', (e) => {
    const allow = e.target.closest('input, textarea, select, [contenteditable="true"]');
    if (allow) return;
    e.preventDefault();
  });
})();

