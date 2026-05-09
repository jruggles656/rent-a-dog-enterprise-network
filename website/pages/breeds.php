<?php $page_title = 'Meet Our Dogs'; ?>
<?php require_once '../includes/header.php'; ?>

  <!-- ============================================================
       BREEDS PAGE — Dog Catalog with Tier Filters
       ============================================================ -->
  <main class="breeds-page">
    <div class="container">

      <!-- Page Header -->
      <header class="page-header reveal">
        <h1 class="page-title">Meet Our Dogs</h1>
        <p class="handwritten page-subtitle">Every tail has a story</p>
      </header>

      <!-- Tier Filter Bar -->
      <div class="filter-bar reveal">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="Basic">Basic</button>
        <button class="filter-btn" data-filter="Premium">Premium</button>
        <button class="filter-btn" data-filter="VIP">VIP</button>
      </div>

      <!-- Dog Card Grid -->
      <div class="dog-grid">
        <?php foreach ($dogs as $dog): ?>
        <div class="card dog-card reveal" data-tier="<?= $dog['tier'] ?>">
          <div class="card-image">
            <span class="tier-badge badge-<?= strtolower($dog['tier']) ?>"><?= $dog['tier'] ?></span>
            <?php if ($dog['status'] === 'available'): ?>
              <span class="status-dot available" title="Available"></span>
            <?php else: ?>
              <span class="status-dot rented" title="Currently Rented"></span>
            <?php endif; ?>
            <img src="<?= $dog['photo'] ?>" alt="<?= htmlspecialchars($dog['name']) ?>" loading="lazy">
          </div>
          <div class="card-body">
            <h3><?= htmlspecialchars($dog['name']) ?></h3>
            <p class="text-muted"><?= htmlspecialchars($dog['breed']) ?> &middot; <?= $dog['age'] ?> yrs &middot; <?= $dog['weight'] ?> lbs</p>
            <p class="dog-description"><?= htmlspecialchars($dog['description']) ?></p>
            <div class="card-footer">
              <span class="dog-rate">$<?= $dog['rate'] ?>/hr</span>
              <?php if ($dog['status'] === 'available'): ?>
                <button class="btn-primary btn-sm add-to-cart-btn" data-type="dog" data-id="<?= $dog['id'] ?>">Rent Me</button>
              <?php else: ?>
                <button class="btn-primary btn-sm" disabled>Currently Rented</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div><!-- /.dog-grid -->

    </div><!-- /.container -->
  </main>

<?php require_once '../includes/footer.php'; ?>
