(() => {
  const form = document.querySelector('form[data-song-form="1"]');
  if (!form) return;

  const titleInput = form.querySelector('input[name="title"]');
  const artistInput = form.querySelector('input[name="artist"]');
  const driveInput = form.querySelector('input[name="drive"]');
  const languageInput = form.querySelector('input[name="language"]');
  const albumInput = form.querySelector('input[name="album"]');
  const coverInput = form.querySelector('input[name="cover_url"]');
  const genreInput = form.querySelector('input[name="genre"]');
  const yearInput = form.querySelector('input[name="year"]');
  const coverImg = document.getElementById('coverPreview');
  const coverPlaceholder = document.getElementById('coverPlaceholder');
  const metaBadge = document.getElementById('metaBadge');
  const dupBox = document.getElementById('dupBox');
  const dupList = document.getElementById('dupList');
  const submitBtn = form.querySelector('button[type="submit"]');
  const artistSuggest = document.getElementById('artistSuggest');
  const titleSuggest = document.getElementById('titleSuggest');
  const pickCoverBtn = document.getElementById('pickCoverBtn');
  const coverModalEl = document.getElementById('coverPickerModal');
  const coverCandidatesEl = document.getElementById('coverCandidates');
  const coverPickerStatus = document.getElementById('coverPickerStatus');
  const refreshCoverBtn = document.getElementById('refreshCoverBtn');

  const setMetaBadge = (text, level = 'secondary') => {
    if (!metaBadge) return;
    metaBadge.className = `badge text-bg-${level}`;
    metaBadge.textContent = text;
  };

  const setCoverPreview = (url) => {
    if (!coverImg) return;
    const v = (url || '').trim();
    if (!v) {
      coverImg.style.display = 'none';
      if (coverPlaceholder) coverPlaceholder.style.display = '';
      return;
    }
    coverImg.src = v;
    // show placeholder until load succeeds
    coverImg.style.display = 'none';
    if (coverPlaceholder) coverPlaceholder.style.display = '';
  };

  if (coverImg) {
    coverImg.addEventListener('load', () => {
      coverImg.style.display = '';
      if (coverPlaceholder) coverPlaceholder.style.display = 'none';
    });
    coverImg.addEventListener('error', () => {
      coverImg.style.display = 'none';
      if (coverPlaceholder) coverPlaceholder.style.display = '';
    });
  }

  const setCoverStatus = (text) => {
    if (!coverPickerStatus) return;
    coverPickerStatus.textContent = text;
  };

  const showSuggest = (el, items) => {
    if (!el) return;
    el.innerHTML = '';
    if (!items || items.length === 0) {
      el.classList.add('d-none');
      return;
    }
    items.forEach((it) => {
      const a = document.createElement('button');
      a.type = 'button';
      a.className = 'list-group-item list-group-item-action';
      a.textContent = typeof it === 'string' ? it : `${it.title} — ${it.artist}`;
      a.addEventListener('click', () => {
        if (typeof it === 'string') {
          artistInput.value = it;
          hideSuggest(artistSuggest);
          scheduleLookup();
          scheduleDupes();
          if (titleInput) titleInput.focus();
        } else if (it && typeof it === 'object') {
          titleInput.value = it.title || '';
          artistInput.value = it.artist || artistInput.value;
          hideSuggest(titleSuggest);
          scheduleLookup();
          scheduleDupes();
          if (driveInput) driveInput.focus();
        }
      });
      el.appendChild(a);
    });
    el.classList.remove('d-none');
  };

  const hideSuggest = (el) => {
    if (!el) return;
    el.classList.add('d-none');
  };

  const setDupes = (matches) => {
    if (!dupBox || !dupList || !submitBtn) return;
    dupList.innerHTML = '';
    if (!matches || matches.length === 0) {
      dupBox.classList.add('d-none');
      submitBtn.disabled = false;
      return;
    }
    dupBox.classList.remove('d-none');
    matches.forEach((m) => {
      const li = document.createElement('li');
      li.textContent = `#${m.id} — ${m.title} / ${m.artist}`;
      dupList.appendChild(li);
    });
    submitBtn.disabled = true;
  };

  const getParams = () => {
    const title = (titleInput?.value || '').trim();
    const artist = (artistInput?.value || '').trim();
    const drive = (driveInput?.value || '').trim();
    return { title, artist, drive };
  };

  const getExcludeId = () => {
    try {
      const params = new URLSearchParams(window.location.search);
      const id = Number(params.get('id') || 0);
      return Number.isFinite(id) && id > 0 ? id : 0;
    } catch {
      return 0;
    }
  };

  let lookupTimer = null;
  let dupTimer = null;
  let artistTimer = null;
  let titleTimer = null;

  const suggestArtists = async () => {
    if (!artistInput || !artistSuggest) return;
    const q = (artistInput.value || '').trim();
    try {
      const url = new URL(window.location.origin + window.location.pathname);
      url.searchParams.set('r', '/admin/api/artist-suggest');
      url.searchParams.set('q', q);
      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      showSuggest(artistSuggest, data.items || []);
    } catch {
      hideSuggest(artistSuggest);
    }
  };

  const suggestSongs = async () => {
    if (!titleInput || !titleSuggest) return;
    const q = (titleInput.value || '').trim();
    const artist = (artistInput?.value || '').trim();
    if (!q && !artist) {
      hideSuggest(titleSuggest);
      return;
    }
    try {
      const url = new URL(window.location.origin + window.location.pathname);
      url.searchParams.set('r', '/admin/api/song-suggest');
      url.searchParams.set('q', q);
      url.searchParams.set('artist', artist);
      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      showSuggest(titleSuggest, data.items || []);
    } catch {
      hideSuggest(titleSuggest);
    }
  };

  const checkDupes = async () => {
    const { title, artist, drive } = getParams();
    if (!title || !artist) {
      setDupes([]);
      return;
    }
    try {
      const url = new URL(window.location.origin + window.location.pathname);
      url.searchParams.set('r', '/admin/api/song-check');
      url.searchParams.set('title', title);
      url.searchParams.set('artist', artist);
      if (drive) url.searchParams.set('drive', drive);
      const excludeId = getExcludeId();
      if (excludeId) url.searchParams.set('exclude_id', String(excludeId));
      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      setDupes(data.matches || []);
    } catch {
      // ignore
    }
  };

  const doLookup = async () => {
    const { title, artist } = getParams();
    if (!title || !artist) return;

    // Only auto-fill if fields are empty (no override).
    const langEmpty = languageInput && !languageInput.value.trim();
    const albumEmpty = albumInput && !albumInput.value.trim();
    const coverEmpty = coverInput && !coverInput.value.trim();
    const genreEmpty = genreInput && !genreInput.value.trim();
    const yearEmpty = yearInput && !yearInput.value.trim();
    if (!langEmpty && !albumEmpty && !coverEmpty && !genreEmpty && !yearEmpty) return;

    setMetaBadge('Fetching cover…', 'info');
    try {
      const url = new URL(window.location.origin + window.location.pathname);
      url.searchParams.set('r', '/admin/api/song-lookup');
      url.searchParams.set('title', title);
      url.searchParams.set('artist', artist);
      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      const meta = data.meta || null;
      if (meta && typeof meta === 'object') {
        if (langEmpty && meta.language && languageInput) languageInput.value = meta.language;
        if (albumEmpty && meta.album && albumInput) albumInput.value = meta.album;
        if (coverEmpty && meta.cover_url && coverInput) coverInput.value = meta.cover_url;
        if (genreEmpty && meta.genre && genreInput) genreInput.value = meta.genre;
        if (yearEmpty && meta.year && yearInput) yearInput.value = String(meta.year);
        if (coverInput?.value) setCoverPreview(coverInput.value);
        setMetaBadge(`Meta: ${meta.source || 'found'}`, 'success');
      } else {
        setMetaBadge('Meta: not found', 'secondary');
      }
    } catch {
      setMetaBadge('Meta: error', 'warning');
    }
  };

  const loadCoverCandidates = async () => {
    if (!coverCandidatesEl) return;
    const { title, artist } = getParams();
    if (!title || !artist) {
      setCoverStatus('Enter Title + Artist first.');
      coverCandidatesEl.innerHTML = '';
      return;
    }

    setCoverStatus('Loading candidates…');
    coverCandidatesEl.innerHTML = '';
    try {
      const url = new URL(window.location.origin + window.location.pathname);
      url.searchParams.set('r', '/admin/api/song-candidates');
      url.searchParams.set('title', title);
      url.searchParams.set('artist', artist);
      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      const candidates = data.candidates || [];
      if (!candidates || candidates.length === 0) {
        setCoverStatus('No candidates found.');
        return;
      }
      setCoverStatus(`${candidates.length} candidates found.`);

      candidates.forEach((c) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action';

        const cover = (c.cover_url || '').trim();
        const album = (c.album || '').trim();

        const row = document.createElement('div');
        row.className = 'd-flex gap-3';

        const thumb = document.createElement('div');
        thumb.className = 'rounded overflow-hidden bg-dark d-flex align-items-center justify-content-center text-white-50';
        thumb.style.width = '64px';
        thumb.style.height = '64px';
        thumb.style.flex = '0 0 auto';

        const ph = document.createElement('i');
        ph.className = 'bi bi-image';
        ph.setAttribute('aria-hidden', 'true');
        thumb.appendChild(ph);

        if (cover) {
          const img = document.createElement('img');
          img.alt = '';
          img.src = cover;
          img.style.width = '64px';
          img.style.height = '64px';
          img.style.objectFit = 'cover';
          img.style.display = 'none';
          img.addEventListener('load', () => {
            ph.style.display = 'none';
            img.style.display = 'block';
          });
          img.addEventListener('error', () => {
            ph.style.display = '';
            img.style.display = 'none';
          });
          thumb.appendChild(img);
        }

        const body = document.createElement('div');
        body.className = 'flex-grow-1';

        const t = document.createElement('div');
        t.className = 'fw-semibold';
        t.textContent = c.title || '';
        const a = document.createElement('div');
        a.className = 'text-muted small';
        a.textContent = c.artist || '';
        const al = document.createElement('div');
        al.className = 'text-muted small';
        al.textContent = album;
        const b = document.createElement('span');
        b.className = 'badge text-bg-secondary mt-1';
        b.textContent = c.source || '';

        body.appendChild(t);
        body.appendChild(a);
        if (album) body.appendChild(al);
        body.appendChild(b);

        row.appendChild(thumb);
        row.appendChild(body);
        btn.appendChild(row);

        btn.addEventListener('click', () => {
          if (coverInput && cover) coverInput.value = cover;
          if (albumInput && album && !albumInput.value.trim()) albumInput.value = album;
          if (languageInput && c.language && !languageInput.value.trim()) languageInput.value = c.language;
          if (genreInput && c.genre && !genreInput.value.trim()) genreInput.value = c.genre;
          if (yearInput && c.year && !yearInput.value.trim()) yearInput.value = String(c.year);
          setCoverPreview(cover);
          setMetaBadge(`Meta: picked (${c.source || 'cover'})`, 'primary');
          if (coverModalEl && window.bootstrap) {
            const modal = window.bootstrap.Modal.getInstance(coverModalEl);
            modal?.hide();
          }
        });
        coverCandidatesEl.appendChild(btn);
      });
    } catch {
      setCoverStatus('Error loading candidates.');
    }
  };

  const scheduleLookup = () => {
    if (lookupTimer) clearTimeout(lookupTimer);
    lookupTimer = setTimeout(doLookup, 450);
  };

  const scheduleDupes = () => {
    if (dupTimer) clearTimeout(dupTimer);
    dupTimer = setTimeout(checkDupes, 350);
  };

  const scheduleArtistSuggest = () => {
    if (artistTimer) clearTimeout(artistTimer);
    artistTimer = setTimeout(suggestArtists, 180);
  };

  const scheduleTitleSuggest = () => {
    if (titleTimer) clearTimeout(titleTimer);
    titleTimer = setTimeout(suggestSongs, 180);
  };

  [titleInput, artistInput].forEach((el) => {
    if (!el) return;
    el.addEventListener('input', () => {
      scheduleLookup();
      scheduleDupes();
      if (el === artistInput) scheduleArtistSuggest();
      if (el === titleInput) scheduleTitleSuggest();
    });
    el.addEventListener('blur', () => {
      doLookup();
      checkDupes();
      setTimeout(() => {
        hideSuggest(artistSuggest);
        hideSuggest(titleSuggest);
      }, 150);
    });
    el.addEventListener('focus', () => {
      if (el === artistInput) suggestArtists();
      if (el === titleInput) suggestSongs();
    });
  });

  // If artist suggestions are open, accept the top (most recent) one on Enter.
  if (artistInput && artistSuggest) {
    artistInput.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      const open = !artistSuggest.classList.contains('d-none');
      const first = artistSuggest.querySelector('button.list-group-item');
      if (!open || !first) return;
      e.preventDefault();
      first.click();
    });
  }
  if (driveInput) {
    driveInput.addEventListener('input', scheduleDupes);
    driveInput.addEventListener('blur', checkDupes);
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      hideSuggest(artistSuggest);
      hideSuggest(titleSuggest);
    }
  });

  if (pickCoverBtn && coverModalEl && window.bootstrap) {
    const modal = new window.bootstrap.Modal(coverModalEl);
    pickCoverBtn.addEventListener('click', () => {
      modal.show();
      loadCoverCandidates();
    });
    refreshCoverBtn?.addEventListener('click', loadCoverCandidates);
    coverModalEl.addEventListener('shown.bs.modal', loadCoverCandidates);
  }

  if (coverInput) {
    coverInput.addEventListener('input', () => setCoverPreview(coverInput.value));
    coverInput.addEventListener('blur', () => setCoverPreview(coverInput.value));
  }

  // Initialize preview on load.
  if (coverInput?.value) {
    setCoverPreview(coverInput.value);
  } else {
    setCoverPreview('');
  }
})();
