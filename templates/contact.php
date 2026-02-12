<?php /** @var array|null $user */ ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="bi bi-chat-dots me-2" aria-hidden="true"></i>Contact</h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(APP_BASE) ?>/?r=/"><i class="bi bi-house me-1" aria-hidden="true"></i>Home</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="text-muted mb-3">Report an issue or request a song.</div>

    <form method="post" action="<?= e(APP_BASE) ?>/?r=/contact">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Type</label>
          <select class="form-select" name="type" required>
            <option value="Issue">Issue</option>
            <option value="Song request">Song request</option>
            <option value="Feedback">Feedback</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Your name (optional)</label>
          <input class="form-control" name="from_name" placeholder="<?= $user ? e((string)($user['username'] ?? '')) : 'Name' ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Your email (optional)</label>
          <input class="form-control" name="from_email" placeholder="<?= $user ? e((string)($user['email'] ?? 'you@example.com')) : 'you@example.com' ?>">
          <div class="text-muted small mt-1">If provided, admin can reply to you.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Song title (optional)</label>
          <input class="form-control" name="song_title" placeholder="Title">
        </div>
        <div class="col-md-4">
          <label class="form-label">Artist (optional)</label>
          <input class="form-control" name="song_artist" placeholder="Artist">
        </div>
        <div class="col-md-4">
          <label class="form-label">Link (optional)</label>
          <input class="form-control" name="song_link" placeholder="YouTube / Drive / Spotify link">
        </div>

        <div class="col-12">
          <label class="form-label">Message</label>
          <textarea class="form-control" name="message" rows="6" required placeholder="Describe the issue or request..."></textarea>
        </div>

        <div class="col-12 d-none">
          <label class="form-label">Website</label>
          <input class="form-control" name="website" autocomplete="off" tabindex="-1">
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i>Send</button>
        <a class="btn btn-outline-secondary" href="<?= e(APP_BASE) ?>/?r=/">Cancel</a>
      </div>
    </form>
  </div>
</div>

