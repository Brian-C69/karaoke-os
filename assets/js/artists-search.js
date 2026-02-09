(() => {
  const form = document.getElementById('artistsSearchForm');
  const qInput = document.getElementById('artistsQ');
  const sortSelect = document.getElementById('artistsSort');
  const perPageSelect = document.getElementById('artistsPerPage');
  const viewInput = document.getElementById('artistsView');
  const pageInput = document.getElementById('artistsPage');
  const resultsEl = document.getElementById('artistsResults');
  const pagerEl = document.getElementById('artistsPager');
  const cancelBtn = document.getElementById('artistsCancel');

  if (!form || !qInput || !sortSelect || !perPageSelect || !resultsEl || !pageInput || !viewInput) return;

  const viewButtons = Array.from(document.querySelectorAll('[data-artists-view]'));
  const getView = () => {
    const v = String(viewInput.value || 'tile').toLowerCase();
    return v === 'list' ? 'list' : 'tile';
  };

  const setViewUi = (v) => {
    viewInput.value = v;
    viewButtons.forEach((b) => {
      const on = String(b.dataset.artistsView || '') === v;
      b.classList.toggle('btn-secondary', on);
      b.classList.toggle('btn-outline-secondary', !on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };

  const buildApiUrl = () => {
    const url = new URL(window.location.origin + window.location.pathname);
    url.searchParams.set('r', '/api/artists');
    url.searchParams.set('q', (qInput.value || '').trim());
    url.searchParams.set('sort', sortSelect.value || 'latest');
    url.searchParams.set('per_page', perPageSelect.value || '20');
    url.searchParams.set('page', pageInput.value || '1');
    return url;
  };

  const syncUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('r', '/artists');

    const q = (qInput.value || '').trim();
    if (q) url.searchParams.set('q', q);
    else url.searchParams.delete('q');

    const sort = String(sortSelect.value || 'latest');
    if (sort !== 'latest') url.searchParams.set('sort', sort);
    else url.searchParams.delete('sort');

    const perPage = String(Number(perPageSelect.value || 20) || 20);
    if (perPage !== '20') url.searchParams.set('per_page', perPage);
    else url.searchParams.delete('per_page');

    const page = String(Number(pageInput.value || 1) || 1);
    if (page !== '1') url.searchParams.set('page', page);
    else url.searchParams.delete('page');

    const view = getView();
    if (view !== 'tile') url.searchParams.set('view', view);
    else url.searchParams.delete('view');

    window.history.replaceState({}, '', url.toString());
  };

  const escapeHtml = (s) =>
    String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const escapeAttr = (s) => escapeHtml(s).replaceAll('`', '&#096;');

  const isExternal = (u) => /^https?:\/\//i.test(String(u || ''));
  const toImgSrc = (u) => {
    const raw = String(u || '').trim();
    if (!raw) return '';
    if (isExternal(raw)) return raw;
    return raw.replace(/^\//, '');
  };

  const renderGrid = (artists) => {
    if (!Array.isArray(artists) || artists.length === 0) {
      resultsEl.innerHTML = '<div class="alert alert-info">No artists found.</div>';
      return;
    }

    const cards = artists.map((a) => {
      const imgSrc = toImgSrc(a.image_url || '');
      const imgHtml = imgSrc
        ? `<img src="${escapeAttr(imgSrc)}" alt="" style="object-fit:cover; width:100%; height:100%;">`
        : `<div class="w-100 h-100 d-flex align-items-center justify-content-center text-white-50"><i class="bi bi-person-circle fs-1" aria-hidden="true"></i></div>`;

      return `
        <div class="col-6 col-md-4 col-lg-3">
          <a class="card h-100 shadow-sm text-decoration-none" href="?r=/artist&id=${encodeURIComponent(a.id)}">
            <div class="ratio ratio-1x1 bg-dark overflow-hidden">${imgHtml}</div>
            <div class="card-body">
              <div class="fw-semibold text-truncate">${escapeHtml(a.name || '')}</div>
              <div class="text-muted small">${Number(a.song_count || 0)} songs · ${Number(a.play_count || 0)} plays</div>
            </div>
          </a>
        </div>
      `;
    });

    resultsEl.innerHTML = `<div class="row g-3" id="artistsGrid">${cards.join('')}</div>`;
  };

  const renderList = (artists) => {
    if (!Array.isArray(artists) || artists.length === 0) {
      resultsEl.innerHTML = '<div class="alert alert-info">No artists found.</div>';
      return;
    }

    const rows = artists.map((a) => {
      const imgSrc = toImgSrc(a.image_url || '');
      const imgHtml = imgSrc
        ? `<img src="${escapeAttr(imgSrc)}" alt="" style="width:44px;height:44px;object-fit:cover;">`
        : `<i class="bi bi-person-circle" aria-hidden="true"></i>`;

      return `
        <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="?r=/artist&id=${encodeURIComponent(
          a.id
        )}">
          <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
            ${imgHtml}
          </div>
          <div class="flex-grow-1 text-truncate">
            <div class="fw-semibold text-truncate">${escapeHtml(a.name || '')}</div>
            <div class="text-muted small text-truncate">${Number(a.song_count || 0)} songs · ${Number(a.play_count || 0)} plays</div>
          </div>
        </a>
      `;
    });

    resultsEl.innerHTML = `<div class="list-group shadow-sm" id="artistsList">${rows.join('')}</div>`;
  };

  const renderResults = (artists) => {
    const v = getView();
    if (v === 'list') renderList(artists);
    else renderGrid(artists);
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
    nav.setAttribute('aria-label', 'Artists pages');
    nav.appendChild(ul);

    pagerEl.innerHTML = '';
    pagerEl.appendChild(nav);
  };

  let timer = null;
  let lastReq = 0;

  const fetchAndRender = async () => {
    const reqId = ++lastReq;
    try {
      const url = buildApiUrl();
      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (reqId !== lastReq) return;
      renderResults(data.artists || []);
      renderPager(data.pager || null);
      syncUrl();
    } catch {
      // ignore
    }
  };

  const setPage = (p) => {
    pageInput.value = String(Math.max(1, Number(p || 1)));
  };

  const schedule = () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => fetchAndRender(), 250);
  };

  qInput.addEventListener('input', () => {
    setPage(1);
    schedule();
  });
  sortSelect.addEventListener('change', () => {
    setPage(1);
    fetchAndRender();
  });
  perPageSelect.addEventListener('change', () => {
    setPage(1);
    fetchAndRender();
  });

  viewButtons.forEach((b) => {
    b.addEventListener('click', () => {
      const v = String(b.dataset.artistsView || 'tile');
      setViewUi(v === 'list' ? 'list' : 'tile');
      setPage(1);
      fetchAndRender();
    });
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    setPage(1);
    fetchAndRender();
  });

  cancelBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    if ((qInput.value || '').trim() === '') return;
    qInput.value = '';
    setPage(1);
    fetchAndRender();
  });

  pagerEl?.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-page]');
    if (!a) return;
    e.preventDefault();
    setPage(a.dataset.page);
    fetchAndRender();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  setViewUi(getView());
})();
