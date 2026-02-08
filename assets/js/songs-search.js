(() => {
  const form = document.getElementById('songsSearchForm');
  const qInput = document.getElementById('songsQ');
  const sortSelect = document.getElementById('songsSort');
  const perPageSelect = document.getElementById('songsPerPage');
  const pageInput = document.getElementById('songsPage');
  const suggestEl = document.getElementById('songsSuggest');
  const resultsEl = document.getElementById('songsResults');
  const pagerEl = document.getElementById('songsPager');

  if (!form || !qInput || !sortSelect || !perPageSelect || !resultsEl || !pageInput) return;

  const buildApiUrl = () => {
    const url = new URL(window.location.origin + window.location.pathname);
    url.searchParams.set('r', '/api/songs');
    url.searchParams.set('q', (qInput.value || '').trim());
    url.searchParams.set('sort', sortSelect.value || 'latest');
    url.searchParams.set('per_page', perPageSelect.value || '20');
    url.searchParams.set('page', pageInput.value || '1');

    const params = new URLSearchParams(window.location.search);
    const artist = params.get('artist');
    const language = params.get('language');
    if (artist) url.searchParams.set('artist', artist);
    if (language) url.searchParams.set('language', language);

    return url;
  };

  const syncUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('r', '/songs');

    const q = (qInput.value || '').trim();
    if (q) url.searchParams.set('q', q);
    else url.searchParams.delete('q');

    const sort = sortSelect.value || 'latest';
    if (sort && sort !== 'latest') url.searchParams.set('sort', sort);
    else url.searchParams.delete('sort');

    const perPage = String(Number(perPageSelect.value || 20) || 20);
    if (perPage !== '20') url.searchParams.set('per_page', perPage);
    else url.searchParams.delete('per_page');

    const page = String(Number(pageInput.value || 1) || 1);
    if (page !== '1') url.searchParams.set('page', page);
    else url.searchParams.delete('page');

    window.history.replaceState({}, '', url.toString());
  };

  const hideSuggest = () => {
    if (!suggestEl) return;
    suggestEl.classList.add('d-none');
    suggestEl.innerHTML = '';
  };

  const showSuggest = (songs) => {
    if (!suggestEl) return;
    suggestEl.innerHTML = '';
    if (!songs || songs.length === 0) {
      hideSuggest();
      return;
    }
    songs.slice(0, 8).forEach((s) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'list-group-item list-group-item-action';
      btn.innerHTML = `
        <div class="d-flex align-items-center justify-content-between gap-2">
          <div class="text-truncate">
            <div class="fw-semibold text-truncate">${escapeHtml(s.title || '')}</div>
            <div class="text-muted small text-truncate">${escapeHtml(s.artist || '')}</div>
          </div>
          <i class="bi bi-arrow-up-right-square text-muted" aria-hidden="true"></i>
        </div>
      `;
      btn.addEventListener('click', () => {
        window.location.href = `?r=/song&id=${encodeURIComponent(s.id)}`;
      });
      suggestEl.appendChild(btn);
    });
    suggestEl.classList.remove('d-none');
  };

  const renderGrid = (songs) => {
    if (!Array.isArray(songs) || songs.length === 0) {
      resultsEl.innerHTML = '<div class="alert alert-info">No songs found.</div>';
      return;
    }

    const cards = songs.map((s) => {
      const cover = (s.cover_url || '').trim();
      const coverHtml = cover
        ? `<img src="${escapeAttr(cover)}" alt="">`
        : `<div class="placeholder"><i class="bi bi-image me-1" aria-hidden="true"></i>No cover</div>`;

      return `
        <div class="col-6 col-md-4 col-lg-3">
          <a class="card song-card h-100 shadow-sm text-decoration-none" href="?r=/song&id=${encodeURIComponent(
            s.id
          )}">
            <div class="cover">${coverHtml}</div>
            <div class="card-body">
              <div class="fw-semibold text-dark text-truncate">${escapeHtml(s.title || '')}</div>
              <div class="text-muted small text-truncate">${escapeHtml(s.artist || '')}</div>
              <div class="text-muted small">${Number(s.play_count || 0)} plays</div>
            </div>
          </a>
        </div>
      `;
    });

    resultsEl.innerHTML = `<div class="row g-3" id="songsGrid">${cards.join('')}</div>`;
  };

  const renderPager = (pager) => {
    if (!pagerEl) return;
    if (!pager || !pager.pages || pager.pages <= 1) {
      pagerEl.innerHTML = '';
      return;
    }

    const page = Number(pager.page || 1);
    const pages = Number(pager.pages || 1);

    const mk = (p, label, disabled = false, active = false) => {
      const li = document.createElement('li');
      li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.dataset.page = String(p);
      a.textContent = label;
      li.appendChild(a);
      return li;
    };

    const ul = document.createElement('ul');
    ul.className = 'pagination mb-0';
    ul.appendChild(mk(Math.max(1, page - 1), 'Prev', page <= 1, false));

    const start = Math.max(1, page - 2);
    const end = Math.min(pages, page + 2);
    for (let p = start; p <= end; p++) {
      ul.appendChild(mk(p, String(p), false, p === page));
    }

    ul.appendChild(mk(Math.min(pages, page + 1), 'Next', page >= pages, false));

    const nav = document.createElement('nav');
    nav.setAttribute('aria-label', 'Songs pages');
    nav.appendChild(ul);

    pagerEl.innerHTML = '';
    pagerEl.appendChild(nav);
  };

  const escapeHtml = (s) =>
    String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const escapeAttr = (s) => escapeHtml(s).replaceAll('`', '&#096;');

  let timer = null;
  let lastReq = 0;

  const fetchAndRender = async ({ showSuggestions = true } = {}) => {
    const reqId = ++lastReq;
    try {
      const url = buildApiUrl();
      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (reqId !== lastReq) return;
      const songs = data.songs || [];
      renderGrid(songs);
      renderPager(data.pager || null);
      syncUrl();
      if (showSuggestions && (qInput.value || '').trim()) showSuggest(songs);
      else hideSuggest();
    } catch {
      // ignore
    }
  };

  const setPage = (p) => {
    const v = String(Math.max(1, Number(p || 1)));
    pageInput.value = v;
  };

  const schedule = () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => fetchAndRender({ showSuggestions: true }), 250);
  };

  qInput.addEventListener('input', () => {
    setPage(1);
    schedule();
  });
  sortSelect.addEventListener('change', () => {
    setPage(1);
    fetchAndRender({ showSuggestions: false });
  });
  perPageSelect.addEventListener('change', () => {
    setPage(1);
    fetchAndRender({ showSuggestions: false });
  });

  form.addEventListener('submit', (e) => {
    // prevent full reload if JS is active
    e.preventDefault();
    setPage(1);
    fetchAndRender({ showSuggestions: false });
  });

  pagerEl?.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-page]');
    if (!a) return;
    e.preventDefault();
    setPage(a.dataset.page);
    fetchAndRender({ showSuggestions: false });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  qInput.addEventListener('blur', () => setTimeout(hideSuggest, 150));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hideSuggest();
  });
})();
