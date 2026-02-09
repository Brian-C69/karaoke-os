<?php /** @var string $message */ ?>
<div class="alert alert-danger"><?= e($message ?? 'Something went wrong.') ?></div>
<a href="<?= e(APP_BASE) ?>/?r=/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>
