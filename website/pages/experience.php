<?php
/**
 * Experience Detail Page
 * Usage: experience.php?id=1  |  experience.php?id=2  |  experience.php?id=3
 */

// Load data first so we can look up the experience for the page title
require_once '../includes/data.php';

$exp_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Find the requested experience
$experience = null;
foreach ($experiences as $exp) {
    if ($exp['id'] === $exp_id) {
        $experience = $exp;
        break;
    }
}

// Redirect to homepage if experience not found
if (!$experience) {
    header('Location: ' . $base_path . '/');
    exit;
}

$page_title = $experience['name'];
require_once '../includes/header.php';
?>

  <!-- ============================================================
       EXPERIENCE DETAIL PAGE
       ============================================================ -->
  <main class="experience-page">

    <!-- Hero Section — Full-viewport immersive -->
    <section class="experience-hero" style="background-image: url('<?= $experience['photo'] ?>');">
      <div class="experience-hero-overlay"></div>
      <div class="container experience-hero-content">
        <p class="handwritten experience-hero-price">From $<?= $experience['price'] ?> per session</p>
        <h1 class="experience-hero-title"><?= htmlspecialchars($experience['name']) ?></h1>
      </div>
    </section>

    <!-- Quick Stats Bar -->
    <div class="experience-stats-bar">
      <div class="container">
        <div class="stat-item">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span><strong><?= $experience['duration'] ?> min</strong> session</span>
        </div>
        <div class="stat-item">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span><strong><?= htmlspecialchars($experience['group_size']) ?></strong></span>
        </div>
        <div class="stat-item">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <span><strong><?= htmlspecialchars($experience['location']) ?></strong></span>
        </div>
        <div class="stat-item">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span><strong>$<?= $experience['price'] ?></strong> per session</span>
        </div>
      </div>
    </div>

    <!-- Content Area — 2 Column Layout -->
    <section class="experience-content">
      <div class="container experience-grid">

        <!-- Left Column (Details) -->
        <div class="experience-details reveal">

          <div class="experience-long-description">
            <p><?= htmlspecialchars($experience['long_description']) ?></p>
          </div>

          <div class="experience-includes">
            <h2>What's Included</h2>
            <ul class="includes-list">
              <?php foreach ($experience['includes'] as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="experience-meta">
            <div class="meta-item">
              <strong>Duration</strong>
              <span><?= $experience['duration'] ?> minutes</span>
            </div>
            <div class="meta-item">
              <strong>Group Size</strong>
              <span><?= htmlspecialchars($experience['group_size']) ?></span>
            </div>
            <div class="meta-item">
              <strong>Location</strong>
              <span><?= htmlspecialchars($experience['location']) ?></span>
            </div>
          </div>

        </div><!-- /.experience-details -->

        <!-- Right Column (Booking Card) -->
        <aside class="experience-booking reveal">
          <div class="booking-card">
            <h3 class="booking-card-title"><?= htmlspecialchars($experience['name']) ?></h3>
            <p class="booking-card-subtitle">Book your session below</p>

            <div class="booking-field">
              <label for="booking-date">Date</label>
              <input type="date" id="booking-date" class="booking-input" min="<?= date('Y-m-d') ?>">
            </div>

            <div class="booking-field">
              <label for="booking-time">Time Slot</label>
              <select id="booking-time" class="booking-input">
                <option value="10:00">10:00 AM</option>
                <option value="12:00">12:00 PM</option>
                <option value="14:00">2:00 PM</option>
                <option value="16:00">4:00 PM</option>
              </select>
            </div>

            <div class="booking-field">
              <label for="booking-dog">Add a Dog <span class="text-muted" style="text-transform:none;letter-spacing:0;">(optional)</span></label>
              <select id="booking-dog" class="booking-input">
                <option value="">No dog selected</option>
                <?php foreach ($dogs as $dog): ?>
                  <?php if ($dog['status'] === 'available'): ?>
                    <option value="<?= $dog['id'] ?>" data-rate="<?= $dog['rate'] ?>">
                      <?= htmlspecialchars($dog['name']) ?> (<?= htmlspecialchars($dog['breed']) ?>) — $<?= $dog['rate'] ?>/hr
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="booking-summary">
              <div class="summary-line">
                <span>Experience</span>
                <span id="summary-experience-price">$<?= $experience['price'] ?></span>
              </div>
              <div class="summary-line" id="summary-dog-line" style="display: none;">
                <span>Dog Rental (1 hr)</span>
                <span id="summary-dog-price">$0</span>
              </div>
              <hr class="summary-divider">
              <div class="summary-line summary-total">
                <strong>Total</strong>
                <strong id="summary-total">$<?= $experience['price'] ?></strong>
              </div>
            </div>

            <button class="btn-primary btn-block add-to-cart-btn"
                    data-type="experience"
                    data-id="<?= $experience['id'] ?>">
              Add to Cart
            </button>
          </div>
        </aside><!-- /.experience-booking -->

      </div><!-- /.experience-grid -->
    </section>

    <!-- Related Experiences -->
    <section class="related-experiences">
      <div class="container">
        <h2 class="section-title reveal">You might also like</h2>
        <p class="section-subtitle reveal">Explore more ways to spend time with our pups</p>
        <div class="related-grid">
          <?php foreach ($experiences as $related): ?>
            <?php if ($related['id'] !== $experience['id']): ?>
            <a href="<?= $base_path ?>/pages/experience.php?id=<?= $related['id'] ?>" class="card related-card reveal">
              <div class="card-image">
                <img src="<?= $related['photo'] ?>" alt="<?= htmlspecialchars($related['name']) ?>" loading="lazy">
              </div>
              <div class="card-body">
                <h3><?= htmlspecialchars($related['name']) ?></h3>
                <p class="text-muted"><?= htmlspecialchars($related['description']) ?></p>
                <div class="card-footer">
                  <span class="experience-price">$<?= $related['price'] ?></span>
                  <span class="experience-duration">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= $related['duration'] ?> min
                  </span>
                </div>
              </div>
            </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div><!-- /.related-grid -->
      </div><!-- /.container -->
    </section>

  </main>

  <!-- Inline booking price calculator -->
  <script>
  (function() {
    const experiencePrice = <?= $experience['price'] ?>;
    const dogSelect = document.getElementById('booking-dog');
    const dogLine = document.getElementById('summary-dog-line');
    const dogPriceEl = document.getElementById('summary-dog-price');
    const totalEl = document.getElementById('summary-total');

    if (dogSelect) {
      dogSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const dogRate = selected.value ? parseInt(selected.dataset.rate, 10) : 0;

        if (dogRate > 0) {
          dogLine.style.display = '';
          dogPriceEl.textContent = '$' + dogRate;
        } else {
          dogLine.style.display = 'none';
          dogPriceEl.textContent = '$0';
        }

        totalEl.textContent = '$' + (experiencePrice + dogRate);
      });
    }
  })();
  </script>

<?php require_once '../includes/footer.php'; ?>
