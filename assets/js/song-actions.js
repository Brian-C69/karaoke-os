(() => {
  const meta = document.querySelector('meta[name="csrf-token"]');
  const csrf = meta?.getAttribute('content') || '';
  const isAuthed = document.body?.dataset?.auth === '1';

  const postForm = async (route, fields) => {
    const body = new URLSearchParams();
    Object.entries(fields || {}).forEach(([k, v]) => body.set(k, String(v)));
    if (csrf) body.set('csrf', csrf);
    const res = await fetch(`?r=${encodeURIComponent(route)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: body.toString(),
    });
    const data = await res.json().catch(() => ({}));
    return { res, data };
  };

  const setFavUi = (btn, favorited) => {
    btn.dataset.favorited = favorited ? '1' : '0';
    btn.setAttribute('aria-pressed', favorited ? 'true' : 'false');
    btn.title = favorited ? 'Remove from favorites' : 'Add to favorites';
    btn.classList.toggle('text-danger', !!favorited);
    btn.classList.toggle('text-muted', !favorited);
    const i = btn.querySelector('i');
    if (i) {
      i.classList.toggle('bi-heart-fill', !!favorited);
      i.classList.toggle('bi-heart', !favorited);
    }
  };

  const cssEscape = (s) => {
    const str = String(s);
    if (window.CSS?.escape) return window.CSS.escape(str);
    return str.replace(/[^a-zA-Z0-9_-]/g, (ch) => `\\${ch}`);
  };

  const updateFavButtons = (songId, favorited) => {
    document
      .querySelectorAll(`.js-fav-toggle[data-song-id="${cssEscape(songId)}"]`)
      .forEach((btn) => setFavUi(btn, favorited));
  };

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-fav-toggle');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    if (!isAuthed) {
      window.location.href = '?r=/login';
      return;
    }

    const songId = Number(btn.dataset.songId || 0);
    if (!songId) return;

    btn.disabled = true;
    try {
      const { res, data } = await postForm('/api/favorite/toggle', { song_id: songId });
      if (!res.ok || !data || data.ok !== true) return;
      updateFavButtons(songId, !!data.favorited);
      if (!data.favorited) {
        const form = document.getElementById('songsSearchForm');
        const pageRoute = String(form?.dataset?.pageRoute || '');
        if (pageRoute === '/favorites') {
          btn.closest('.song-item')?.remove();
        }
      }
    } finally {
      btn.disabled = false;
    }
  });

  const playlistBtn = document.getElementById('addToPlaylistBtn');
  const modalEl = document.getElementById('playlistModal');
  if (!playlistBtn || !modalEl) return;

  const modal = window.bootstrap?.Modal?.getOrCreateInstance(modalEl);
  const listEl = modalEl.querySelector('[data-playlist-list]');
  const selectEl = modalEl.querySelector('[data-playlist-select]');
  const createInput = modalEl.querySelector('[data-playlist-create-name]');
  const createBtn = modalEl.querySelector('[data-playlist-create-btn]');
  const addBtn = modalEl.querySelector('[data-playlist-add-btn]');
  const msgEl = modalEl.querySelector('[data-playlist-msg]');

  const showMsg = (text, level = 'muted') => {
    if (!msgEl) return;
    msgEl.className = `small text-${level}`;
    msgEl.textContent = text || '';
  };

  const fetchPlaylists = async () => {
    const res = await fetch('?r=/api/playlists', { headers: { Accept: 'application/json' } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data || data.ok !== true) return [];
    return Array.isArray(data.items) ? data.items : [];
  };

  const renderPlaylists = (items) => {
    if (selectEl) selectEl.innerHTML = '';
    if (listEl) listEl.innerHTML = '';
    if (!items || items.length === 0) {
      if (listEl) listEl.innerHTML = '<div class="text-muted small">No playlists yet. Create one below.</div>';
      return;
    }

    const opts = items
      .map((p) => `<option value="${escapeAttr(p.id)}">${escapeHtml(p.name)} (${Number(p.song_count || 0)})</option>`)
      .join('');
    if (selectEl) selectEl.innerHTML = `<option value="">Choose playlist…</option>${opts}`;

    if (listEl) {
      listEl.innerHTML = items
        .map(
          (p) => `
          <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between" data-playlist-pick="${escapeAttr(
            p.id
          )}">
            <span class="text-truncate">${escapeHtml(p.name)}</span>
            <span class="badge text-bg-secondary">${Number(p.song_count || 0)}</span>
          </button>`
        )
        .join('');
    }
  };

  const escapeHtml = (s) =>
    String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  const escapeAttr = (s) => escapeHtml(s).replaceAll('`', '&#096;');

  const songId = Number(playlistBtn.dataset.songId || 0);

  playlistBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!isAuthed) {
      window.location.href = '?r=/login';
      return;
    }
    showMsg('');
    renderPlaylists([]);
    modal?.show();
    const items = await fetchPlaylists();
    renderPlaylists(items);
  });

  listEl?.addEventListener('click', (e) => {
    const pick = e.target.closest('[data-playlist-pick]');
    if (!pick) return;
    const id = String(pick.dataset.playlistPick || '');
    if (selectEl) selectEl.value = id;
  });

  addBtn?.addEventListener('click', async () => {
    showMsg('');
    const playlistId = Number(selectEl?.value || 0);
    if (!playlistId) {
      showMsg('Choose a playlist first.', 'danger');
      return;
    }
    addBtn.disabled = true;
    try {
      const { res, data } = await postForm('/api/playlists/add-song', { playlist_id: playlistId, song_id: songId });
      if (!res.ok || !data || data.ok !== true) {
        showMsg('Could not add to playlist.', 'danger');
        return;
      }
      showMsg(data.added ? 'Added to playlist.' : 'Already in that playlist.', 'success');
      setTimeout(() => modal?.hide(), 450);
    } finally {
      addBtn.disabled = false;
    }
  });

  createBtn?.addEventListener('click', async () => {
    showMsg('');
    const name = String(createInput?.value || '').trim();
    if (!name) {
      showMsg('Enter a playlist name.', 'danger');
      return;
    }
    createBtn.disabled = true;
    try {
      const created = await postForm('/api/playlists/create', { name });
      if (!created.res.ok || !created.data || created.data.ok !== true) {
        showMsg('Could not create playlist (maybe duplicate name).', 'danger');
        return;
      }
      if (createInput) createInput.value = '';
      const playlistId = Number(created.data.id || 0);
      const added = await postForm('/api/playlists/add-song', { playlist_id: playlistId, song_id: songId });
      if (!added.res.ok || !added.data || added.data.ok !== true) {
        showMsg('Created playlist, but could not add song.', 'danger');
        return;
      }
      showMsg('Created playlist and added song.', 'success');
      setTimeout(() => modal?.hide(), 550);
    } finally {
      createBtn.disabled = false;
    }
  });
})();
