(() => {
  const form = document.getElementById('songsSearchForm');
  const qInput = document.getElementById('songsQ');
  const sortSelect = document.getElementById('songsSort');
  const perPageSelect = document.getElementById('songsPerPage');
  const viewInput = document.getElementById('songsView');
  const artistFixedInput = document.getElementById('songsArtistFixed');
  const artistIdFixedInput = document.getElementById('songsArtistIdFixed');
  const languageFixedInput = document.getElementById('songsLanguageFixed');
  const recentModeSelect = document.getElementById('recentMode');
  const idFixedInput = form?.querySelector('input[name="id"]');
  const pageInput = document.getElementById('songsPage');
  const suggestEl = document.getElementById('songsSuggest');
  const resultsEl = document.getElementById('songsResults');
  const pagerEl = document.getElementById('songsPager');
  const cancelBtn = document.getElementById('songsCancel');

  if (!form || !qInput || !sortSelect || !perPageSelect || !resultsEl || !pageInput || !viewInput) return;

  const viewButtons = Array.from(document.querySelectorAll('[data-songs-view]'));
  const pageRoute = String(form.dataset.pageRoute || '/songs');
  const apiRoute = String(form.dataset.apiRoute || '/api/songs');
  const renderMode = String(form.dataset.renderMode || 'songs');
  const isAuthed = document.body?.dataset?.auth === '1';
  const getView = () => {
    const v = String(viewInput.value || 'tile').toLowerCase();
    return v === 'list' ? 'list' : 'tile';
  };

  const setViewUi = (v) => {
    viewInput.value = v;
    viewButtons.forEach((b) => {
      const on = String(b.dataset.songsView || '') === v;
      b.classList.toggle('btn-secondary', on);
      b.classList.toggle('btn-outline-secondary', !on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };

  const buildApiUrl = () => {
    const url = new URL(window.location.origin + window.location.pathname);
    url.searchParams.set('r', apiRoute);
    url.searchParams.set('q', (qInput.value || '').trim());
    url.searchParams.set('sort', sortSelect.value || 'latest');
    url.searchParams.set('per_page', perPageSelect.value || '20');
    url.searchParams.set('page', pageInput.value || '1');
    if (idFixedInput?.value) url.searchParams.set('id', String(idFixedInput.value));
    if (recentModeSelect?.value) url.searchParams.set('mode', String(recentModeSelect.value));

    const params = new URLSearchParams(window.location.search);
    const artistId = (artistIdFixedInput?.value || params.get('artist_id') || '').trim();
    const artist = (artistFixedInput?.value || params.get('artist') || '').trim();
    const language = (languageFixedInput?.value || params.get('language') || '').trim();
    if (artistId) {
      url.searchParams.set('artist_id', artistId);
    } else if (artist) {
      url.searchParams.set('artist', artist);
    }
    if (language) url.searchParams.set('language', language);

    return url;
  };

  const syncUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('r', pageRoute);
    if (idFixedInput?.value) url.searchParams.set('id', String(idFixedInput.value));
    if (artistIdFixedInput) {
      if (artistIdFixedInput.value) url.searchParams.set('artist_id', String(artistIdFixedInput.value));
      else url.searchParams.delete('artist_id');
    }
    if (artistFixedInput) {
      if (artistFixedInput.value) url.searchParams.set('artist', String(artistFixedInput.value));
      else url.searchParams.delete('artist');
    }
    if (languageFixedInput?.value) url.searchParams.set('language', String(languageFixedInput.value));
    if (recentModeSelect?.value) url.searchParams.set('mode', String(recentModeSelect.value));

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

    const view = getView();
    if (view !== 'tile') url.searchParams.set('view', view);
    else url.searchParams.delete('view');

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
      const lang = String(s.language || '').trim();
      const langFlag = String(s.language_flag || '').trim();
      const flagHtml = langFlag
        ? `<img class="lang-flag lang-flag-md lang-flag-circle" src="${escapeAttr(langFlag)}" alt="${escapeAttr(
            lang
          )}" title="${escapeAttr(lang)}">`
        : '';

      const favorited = !!s.favorited;
      const favBtn = isAuthed
        ? `
          <button type="button"
                  class="btn btn-sm btn-link p-0 fav-btn song-action js-fav-toggle ${favorited ? 'text-danger' : 'text-muted'}"
                  data-song-id="${escapeAttr(s.id)}"
                  data-favorited="${favorited ? '1' : '0'}"
                  aria-label="Favorite"
                  aria-pressed="${favorited ? 'true' : 'false'}"
                  title="${favorited ? 'Remove from favorites' : 'Add to favorites'}">
            <i class="bi ${favorited ? 'bi-heart-fill' : 'bi-heart'}" aria-hidden="true"></i>
          </button>
        `
        : '';

      const playedAt = String(s.played_at || '').trim();
      const playedAtShort = playedAt ? escapeHtml(playedAt.slice(0, 16)) : '—';
      const metaLeft =
        renderMode === 'recent'
          ? `<i class="bi bi-clock-history me-1" aria-hidden="true"></i>${playedAtShort}`
          : `${Number(s.play_count || 0)} plays`;

      return `
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card song-card h-100 shadow-sm position-relative song-item">
            <a class="stretched-link" href="?r=/song&id=${encodeURIComponent(s.id)}" aria-label="Open song"></a>
            <div class="cover">${coverHtml}</div>
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="fw-semibold text-truncate">${escapeHtml(s.title || '')}</div>
                ${favBtn}
              </div>
              <div class="text-muted small text-truncate">${escapeHtml(s.artist || '')}</div>
              <div class="d-flex align-items-center justify-content-between text-muted small">
                <div>${metaLeft}</div>
                <div>${flagHtml}</div>
              </div>
            </div>
          </div>
        </div>
      `;
    });

    resultsEl.innerHTML = `<div class="row g-3" id="songsGrid">${cards.join('')}</div>`;
  };

  const renderList = (songs) => {
    if (!Array.isArray(songs) || songs.length === 0) {
      resultsEl.innerHTML = '<div class="alert alert-info">No songs found.</div>';
      return;
    }

    const rows = songs.map((s) => {
      const cover = (s.cover_url || '').trim();
      const coverHtml = cover
        ? `<img src="${escapeAttr(cover)}" alt="" style="width:44px;height:44px;object-fit:cover;">`
        : `<i class="bi bi-image" aria-hidden="true"></i>`;
      const lang = String(s.language || '').trim();
      const langFlag = String(s.language_flag || '').trim();
      const flagHtml = langFlag
        ? `<img class="lang-flag lang-flag-xs lang-flag-circle" src="${escapeAttr(langFlag)}" alt="${escapeAttr(
            lang
          )}" title="${escapeAttr(lang)}">`
        : '';

      const favorited = !!s.favorited;
      const favBtn = isAuthed
        ? `
          <button type="button"
                  class="btn btn-sm btn-link p-0 fav-btn song-action js-fav-toggle ${favorited ? 'text-danger' : 'text-muted'}"
                  data-song-id="${escapeAttr(s.id)}"
                  data-favorited="${favorited ? '1' : '0'}"
                  aria-label="Favorite"
                  aria-pressed="${favorited ? 'true' : 'false'}"
                  title="${favorited ? 'Remove from favorites' : 'Add to favorites'}">
            <i class="bi ${favorited ? 'bi-heart-fill' : 'bi-heart'}" aria-hidden="true"></i>
          </button>
        `
        : '';

      const playedAt = String(s.played_at || '').trim();
      const playedAtShort = playedAt ? escapeHtml(playedAt.slice(0, 16)) : '—';
      const metaRight =
        renderMode === 'recent'
          ? `<i class="bi bi-clock-history me-1" aria-hidden="true"></i>${playedAtShort}`
          : `<span>${Number(s.play_count || 0)} plays</span>`;

      return `
        <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 position-relative song-item">
          <a class="stretched-link" href="?r=/song&id=${encodeURIComponent(s.id)}" aria-label="Open song"></a>
          <div class="rounded bg-dark overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center text-white-50" style="width:44px;height:44px;">
            ${coverHtml}
          </div>
          <div class="flex-grow-1 text-truncate">
            <div class="d-flex align-items-center justify-content-between gap-2">
              <div class="fw-semibold text-truncate">${escapeHtml(s.title || '')}</div>
              ${favBtn}
            </div>
            <div class="text-muted small text-truncate">${escapeHtml(s.artist || '')}</div>
          </div>
          <div class="d-flex align-items-center gap-2 text-muted small text-nowrap">${flagHtml}${metaRight}</div>
        </div>
      `;
    });

    resultsEl.innerHTML = `<div class="list-group shadow-sm" id="songsList">${rows.join('')}</div>`;
  };

  const renderResults = (songs) => {
    const v = getView();
    if (v === 'list') renderList(songs);
    else renderGrid(songs);
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
      renderResults(songs);
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
  recentModeSelect?.addEventListener('change', () => {
    setPage(1);
    fetchAndRender({ showSuggestions: false });
  });

  viewButtons.forEach((b) => {
    b.addEventListener('click', () => {
      const v = String(b.dataset.songsView || 'tile');
      setViewUi(v === 'list' ? 'list' : 'tile');
      setPage(1);
      fetchAndRender({ showSuggestions: false });
    });
  });

  form.addEventListener('submit', (e) => {
    // prevent full reload if JS is active
    e.preventDefault();
    setPage(1);
    fetchAndRender({ showSuggestions: false });
  });

  cancelBtn?.addEventListener('click', (e) => {
    // Keep view/per-page; clear only the search query.
    e.preventDefault();
    if ((qInput.value || '').trim() === '') return;
    qInput.value = '';
    hideSuggest();
    setPage(1);
    fetchAndRender({ showSuggestions: false });
  });

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-clear-param]');
    if (!a) return;
    e.preventDefault();
    const clearParam = String(a.dataset.clearParam || '').trim();
    if (!clearParam) return;

    const url = new URL(window.location.href);
    url.searchParams.set('r', pageRoute);
    if (idFixedInput?.value) url.searchParams.set('id', String(idFixedInput.value));

    // Preserve current controls; drop the chosen param and reset to page 1.
    url.searchParams.delete(clearParam);
    url.searchParams.delete('page');

    const q = (qInput.value || '').trim();
    if (q) url.searchParams.set('q', q);
    else url.searchParams.delete('q');

    const sort = sortSelect.value || 'latest';
    if (sort && sort !== 'latest') url.searchParams.set('sort', sort);
    else url.searchParams.delete('sort');

    const perPage = String(Number(perPageSelect.value || 20) || 20);
    if (perPage !== '20') url.searchParams.set('per_page', perPage);
    else url.searchParams.delete('per_page');

    const view = getView();
    if (view !== 'tile') url.searchParams.set('view', view);
    else url.searchParams.delete('view');

    window.location.href = url.toString();
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

  // Initialize toggle state.
  setViewUi(getView());
})();
