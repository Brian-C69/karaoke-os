(() => {
  const init = (root) => {
    const all = root.querySelector('[data-bulk-all]');
    const items = Array.from(root.querySelectorAll('[data-bulk-item]'));
    const countEl = root.querySelector('[data-bulk-count]');
    const applyBtn = root.querySelector('[data-bulk-apply]');

    if (!all || items.length === 0) return;

    const update = () => {
      const checked = items.filter((c) => c.checked).length;
      if (countEl) countEl.textContent = String(checked);
      if (applyBtn) applyBtn.disabled = checked === 0;
      all.checked = checked > 0 && checked === items.length;
      all.indeterminate = checked > 0 && checked < items.length;
    };

    all.addEventListener('change', () => {
      items.forEach((c) => {
        c.checked = all.checked;
      });
      update();
    });

    items.forEach((c) => c.addEventListener('change', update));
    update();
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bulk-root]').forEach((el) => init(el));
  });
})();

