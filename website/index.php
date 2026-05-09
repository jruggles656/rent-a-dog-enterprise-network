<?php $page_title = 'Home'; ?>
<?php require_once 'includes/header.php'; ?>

  <!-- ============================================================
       HERO / INTERACTIVE BREED PICKER
       ============================================================ -->
  <section class="hero reveal" id="hero">
    <div class="container">
      <h1>Find Your Perfect Companion</h1>
      <p class="handwritten hero-subtitle">Choose your tier, meet your match</p>

      <!-- Tier cards -->
      <div class="tier-cards">

        <!-- Basic -->
        <div class="tier-card" data-tier="Basic">
          <span class="tier-icon" aria-hidden="true">&#129353;</span>
          <h2>Basic</h2>
          <p class="tier-price">From $15/hr</p>
          <p class="tier-breeds">Labrador, Beagle, Poodle</p>
          <button class="btn-primary tier-btn">Browse Basic</button>
        </div>

        <!-- Premium -->
        <div class="tier-card" data-tier="Premium">
          <span class="tier-icon" aria-hidden="true">&#129352;</span>
          <h2>Premium</h2>
          <p class="tier-price">From $25/hr</p>
          <p class="tier-breeds">Golden Retriever, Husky, Corgi</p>
          <button class="btn-primary tier-btn">Browse Premium</button>
        </div>

        <!-- VIP -->
        <div class="tier-card" data-tier="VIP">
          <span class="tier-icon" aria-hidden="true">&#129351;</span>
          <h2>VIP</h2>
          <p class="tier-price">From $40/hr</p>
          <p class="tier-breeds">French Bulldog, Samoyed</p>
          <button class="btn-primary tier-btn">Browse VIP</button>
        </div>

      </div><!-- /.tier-cards -->

      <!-- Dog reveal grid (hidden by default, toggled by JS) -->
      <div class="dog-reveal" id="dogReveal">
        <?php foreach ($dogs as $dog): ?>
        <div class="card dog-card" data-tier="<?= $dog['tier'] ?>">
          <div class="card-image">
            <?php if ($dog['tier'] === 'VIP'): ?>
              <span class="tier-badge badge-vip">VIP</span>
            <?php elseif ($dog['tier'] === 'Premium'): ?>
              <span class="tier-badge badge-premium">Premium</span>
            <?php else: ?>
              <span class="tier-badge badge-basic">Basic</span>
            <?php endif; ?>
            <img src="<?= $dog['photo'] ?>" alt="<?= htmlspecialchars($dog['name']) ?>" loading="lazy">
          </div>
          <div class="card-body">
            <h3><?= htmlspecialchars($dog['name']) ?></h3>
            <p class="text-muted"><?= htmlspecialchars($dog['breed']) ?> &middot; <?= $dog['age'] ?> yrs &middot; <?= $dog['weight'] ?> lbs</p>
            <div class="card-footer">
              <span class="dog-rate">$<?= $dog['rate'] ?>/hr</span>
              <button class="btn-primary btn-sm add-to-cart-btn" data-type="dog" data-id="<?= $dog['id'] ?>">Rent Me</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div><!-- /.dog-reveal -->

    </div><!-- /.container -->
  </section>


  <!-- ============================================================
       EXPERIENCES — Full-width parallax sections
       ============================================================ -->
  <div id="experiences">
    <?php foreach ($experiences as $i => $exp): ?>
    <section class="experience-section reveal" id="experience-<?= $exp['id'] ?>">
      <div class="experience-bg" style="background-image: url('<?= $exp['photo'] ?>')" data-parallax></div>
      <div class="experience-overlay"></div>
      <div class="container">
        <div class="experience-content <?= $i % 2 === 1 ? 'experience-right' : '' ?>">
          <span class="handwritten experience-label">Experience</span>
          <h2><?= htmlspecialchars($exp['name']) ?></h2>
          <p><?= htmlspecialchars($exp['description']) ?></p>
          <div class="experience-meta">
            <span>$<?= $exp['price'] ?> per session</span>
            <span><?= $exp['duration'] ?> minutes</span>
          </div>
          <a href="<?= $base_path ?>/pages/experience.php?id=<?= $exp['id'] ?>" class="btn-primary">Book This Experience</a>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
  </div>


  <!-- ============================================================
       HOW IT WORKS
       ============================================================ -->
  <section class="how-it-works reveal" id="how-it-works">
    <div class="container">
      <h2 class="section-heading">How It Works</h2>

      <div class="steps">

        <!-- Step 1 -->
        <div class="step">
          <div class="step-number">1</div>
          <div class="step-icon" aria-hidden="true">
            <!-- Paw print SVG -->
            <svg width="48" height="48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
              <ellipse cx="50" cy="65" rx="22" ry="18" fill="#8B5E3C"/>
              <ellipse cx="28" cy="35" rx="10" ry="13" transform="rotate(-15 28 35)" fill="#8B5E3C"/>
              <ellipse cx="72" cy="35" rx="10" ry="13" transform="rotate(15 72 35)" fill="#8B5E3C"/>
              <ellipse cx="38" cy="28" rx="9" ry="12" transform="rotate(5 38 28)" fill="#8B5E3C"/>
              <ellipse cx="62" cy="28" rx="9" ry="12" transform="rotate(-5 62 28)" fill="#8B5E3C"/>
            </svg>
          </div>
          <h3>Pick a Dog</h3>
          <p>Browse our collection of lovable dogs across three tiers</p>
        </div>

        <!-- Step 2 -->
        <div class="step">
          <div class="step-number">2</div>
          <div class="step-icon" aria-hidden="true">
            <!-- Calendar SVG -->
            <svg width="48" height="48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="12" y="22" width="76" height="66" rx="8" stroke="#8B5E3C" stroke-width="5" fill="none"/>
              <line x1="12" y1="42" x2="88" y2="42" stroke="#8B5E3C" stroke-width="5"/>
              <line x1="32" y1="12" x2="32" y2="30" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round"/>
              <line x1="68" y1="12" x2="68" y2="30" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round"/>
              <circle cx="36" cy="58" r="5" fill="#8B5E3C"/>
              <circle cx="50" cy="58" r="5" fill="#8B5E3C"/>
              <circle cx="64" cy="58" r="5" fill="#8B5E3C"/>
              <circle cx="36" cy="74" r="5" fill="#8B5E3C"/>
              <circle cx="50" cy="74" r="5" fill="#8B5E3C"/>
            </svg>
          </div>
          <h3>Choose an Experience</h3>
          <p>Select from Dog Cafe, Dog Yoga, or Dog Garden</p>
        </div>

        <!-- Step 3 -->
        <div class="step">
          <div class="step-number">3</div>
          <div class="step-icon" aria-hidden="true">
            <!-- Heart SVG -->
            <svg width="48" height="48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M50 88 C25 65, 5 50, 5 32 C5 18, 17 8, 30 8 C38 8, 45 13, 50 20 C55 13, 62 8, 70 8 C83 8, 95 18, 95 32 C95 50, 75 65, 50 88Z" fill="#8B5E3C"/>
            </svg>
          </div>
          <h3>Enjoy Your Day</h3>
          <p>Create unforgettable memories with your furry companion</p>
        </div>

      </div><!-- /.steps -->
    </div><!-- /.container -->
  </section>


  <!-- ============================================================
       CTA BANNER
       ============================================================ -->
  <section class="cta-banner reveal" id="cta">
    <div class="container">
      <h2>Ready to meet your new best friend?</h2>
      <a href="<?= $base_path ?>/pages/breeds.php" class="btn-primary btn-lg">Browse Our Dogs</a>
    </div>
  </section>

<?php require_once 'includes/footer.php'; ?>
