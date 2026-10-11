<section class="card narrow center">
  <h1><?= $status === 404 ? 'Page not found' : ($status === 429 ? 'Please wait a moment' : ($status === 419 ? 'Session expired' : 'Sorry')) ?></h1>
  <p class="lead pre"><?= e($message) ?></p>
  <p><a class="btn btn-primary" href="<?= e(url('/')) ?>">Go to booking home</a></p>
</section>
