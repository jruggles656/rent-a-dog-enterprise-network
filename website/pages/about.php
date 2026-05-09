<?php
/**
 * Rent a Dog — About Page
 * Story-driven narrative with value propositions and team section
 */
$page_title = 'About Us';
require_once '../includes/header.php';
?>

  <!-- ============================================================
       ABOUT PAGE
       ============================================================ -->
  <main class="about-page">

    <!-- ── Hero Section ───────────────────────────────────────── -->
    <section class="about-hero reveal">
      <div class="container">
        <h1 class="about-hero-heading">Our Story</h1>
        <p class="handwritten about-hero-subtitle">How a simple idea became every dog lover's dream</p>
      </div>
    </section>


    <!-- ── The Beginning ──────────────────────────────────────── -->
    <section class="about-section section-padding reveal">
      <div class="container about-narrow">
        <h2>The Beginning</h2>
        <p>
          Not everyone can have a dog of their own &mdash; but everyone deserves
          to experience the joy of canine companionship. That&rsquo;s the idea
          behind Rent a Dog.
        </p>
        <p>
          Whether you&rsquo;re a student in a no-pets apartment, a traveler
          missing home, or simply someone who believes that a bad day can be
          fixed with a wagging tail, we&rsquo;re here for you. We started in
          2024 with three dogs and a tiny backyard in San Bernardino. Today,
          we&rsquo;ve helped hundreds of people discover &mdash; or rediscover
          &mdash; the pure happiness that comes from spending time with a dog.
        </p>
      </div>
    </section>


    <!-- ── Why Rent a Dog? ────────────────────────────────────── -->
    <section class="about-section about-values section-padding reveal">
      <div class="container">
        <h2 class="section-heading">Why Rent a Dog?</h2>

        <div class="grid-3 about-value-grid">

          <!-- Value 1 -->
          <div class="card about-value-card reveal">
            <div class="about-value-icon" aria-hidden="true">
              <svg width="48" height="48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M50 88 C25 65, 5 50, 5 32 C5 18, 17 8, 30 8 C38 8, 45 13, 50 20 C55 13, 62 8, 70 8 C83 8, 95 18, 95 32 C95 50, 75 65, 50 88Z" fill="#8B5E3C"/>
              </svg>
            </div>
            <h3>Companionship Without Commitment</h3>
            <p>
              All the love, none of the long-term responsibility. Perfect for
              those who want dog time on their own schedule.
            </p>
          </div>

          <!-- Value 2 -->
          <div class="card about-value-card reveal">
            <div class="about-value-icon" aria-hidden="true">
              <svg width="48" height="48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="40" r="22" stroke="#8B5E3C" stroke-width="5" fill="none"/>
                <path d="M50 62v12" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round"/>
                <path d="M38 80h24" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round"/>
                <path d="M42 34l6 6 12-14" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
              </svg>
            </div>
            <h3>Happy, Healthy Dogs</h3>
            <p>
              Every dog in our program is professionally trained, regularly
              vet-checked, and genuinely loves meeting new people.
            </p>
          </div>

          <!-- Value 3 -->
          <div class="card about-value-card reveal">
            <div class="about-value-icon" aria-hidden="true">
              <svg width="48" height="48" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="12" y="22" width="76" height="66" rx="8" stroke="#8B5E3C" stroke-width="5" fill="none"/>
                <line x1="12" y1="42" x2="88" y2="42" stroke="#8B5E3C" stroke-width="5"/>
                <line x1="32" y1="12" x2="32" y2="30" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round"/>
                <line x1="68" y1="12" x2="68" y2="30" stroke="#8B5E3C" stroke-width="5" stroke-linecap="round"/>
                <circle cx="36" cy="58" r="5" fill="#8B5E3C"/>
                <circle cx="50" cy="58" r="5" fill="#8B5E3C"/>
                <circle cx="64" cy="58" r="5" fill="#8B5E3C"/>
              </svg>
            </div>
            <h3>Curated Experiences</h3>
            <p>
              From cozy cafe sessions to outdoor yoga and garden play,
              we&rsquo;ve designed experiences that bring out the best in
              human-dog bonding.
            </p>
          </div>

        </div><!-- /.grid-3 -->
      </div>
    </section>


    <!-- ── Our Promise ────────────────────────────────────────── -->
    <section class="about-section about-promise section-padding reveal">
      <div class="container about-narrow">
        <h2>Our Promise</h2>
        <ul class="about-promise-list">
          <li>
            <span class="promise-check" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6B8F71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
            We never compromise on animal welfare
          </li>
          <li>
            <span class="promise-check" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6B8F71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
            Every dog is treated as family, not inventory
          </li>
          <li>
            <span class="promise-check" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6B8F71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
            Professional handlers ensure safety for both dogs and guests
          </li>
          <li>
            <span class="promise-check" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6B8F71" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
            Regular rest periods and health monitoring for all dogs
          </li>
        </ul>
      </div>
    </section>


    <!-- ── Meet the Team ──────────────────────────────────────── -->
    <section class="about-section about-team section-padding reveal">
      <div class="container">
        <h2 class="section-heading">Meet the Team</h2>
        <p class="section-subtitle text-muted">The people behind the paws</p>

        <div class="grid-4 about-team-grid">

          <div class="card about-team-card reveal">
            <div class="team-avatar" aria-hidden="true">
              <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="32" cy="32" r="32" fill="#F2EAE1"/>
                <circle cx="32" cy="26" r="10" fill="#8B5E3C"/>
                <path d="M16 54c0-9 7-16 16-16s16 7 16 16" fill="#8B5E3C"/>
              </svg>
            </div>
            <h4 class="team-name">James</h4>
            <p class="team-role text-muted">Web &amp; Application Lead</p>
          </div>

          <div class="card about-team-card reveal">
            <div class="team-avatar" aria-hidden="true">
              <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="32" cy="32" r="32" fill="#F2EAE1"/>
                <circle cx="32" cy="26" r="10" fill="#8B5E3C"/>
                <path d="M16 54c0-9 7-16 16-16s16 7 16 16" fill="#8B5E3C"/>
              </svg>
            </div>
            <h4 class="team-name">Jason</h4>
            <p class="team-role text-muted">Network &amp; Infrastructure Lead</p>
          </div>

          <div class="card about-team-card reveal">
            <div class="team-avatar" aria-hidden="true">
              <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="32" cy="32" r="32" fill="#F2EAE1"/>
                <circle cx="32" cy="26" r="10" fill="#8B5E3C"/>
                <path d="M16 54c0-9 7-16 16-16s16 7 16 16" fill="#8B5E3C"/>
              </svg>
            </div>
            <h4 class="team-name">Oscar</h4>
            <p class="team-role text-muted">Database &amp; Scripting Lead</p>
          </div>

          <div class="card about-team-card reveal">
            <div class="team-avatar" aria-hidden="true">
              <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="32" cy="32" r="32" fill="#F2EAE1"/>
                <circle cx="32" cy="26" r="10" fill="#8B5E3C"/>
                <path d="M16 54c0-9 7-16 16-16s16 7 16 16" fill="#8B5E3C"/>
              </svg>
            </div>
            <h4 class="team-name">Allegra</h4>
            <p class="team-role text-muted">Security &amp; Monitoring Lead</p>
          </div>

        </div><!-- /.grid-4 -->
      </div>
    </section>


    <!-- ── CTA Banner ─────────────────────────────────────────── -->
    <section class="cta-banner reveal">
      <div class="container">
        <h2>Ready to meet your new best friend?</h2>
        <a href="<?= $base_path ?>/pages/breeds.php" class="btn-primary btn-lg">Browse Our Dogs</a>
      </div>
    </section>

  </main>

<?php require_once '../includes/footer.php'; ?>
