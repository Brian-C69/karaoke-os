<?php /** @var string $message */ ?>
<div class="alert alert-danger"><?= e($message ?? 'Something went wrong.') ?></div>
<a href="<?= e(APP_BASE) ?>/?r=/" class="btn btn-outline-secondary btn-sm">Back home</a>
