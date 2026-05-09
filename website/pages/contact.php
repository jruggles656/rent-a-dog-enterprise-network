<?php
/**
 * Rent a Dog — Contact Page
 * Contact form + business info two-column layout
 */
$page_title = 'Contact Us';
require_once '../includes/header.php';
?>

  <!-- ============================================================
       CONTACT PAGE
       ============================================================ -->
  <main class="contact-page">
    <div class="container">

      <!-- Page Header -->
      <header class="page-header reveal">
        <h1 class="page-title">Contact Us</h1>
        <p class="handwritten page-subtitle">We'd love to hear from you</p>
      </header>

      <!-- Success Message -->
      <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
      <div class="alert alert-success reveal">
        <strong>Message sent!</strong> Thanks for reaching out &mdash; we'll get back to you within 24 hours.
      </div>
      <?php endif; ?>

      <!-- Two-Column Layout -->
      <div class="contact-grid reveal">

        <!-- Left Column — Contact Form -->
        <div class="contact-form-col">
          <form method="POST" action="<?= $base_path ?>/api/contact_handler.php" class="contact-form">
            <div class="form-group">
              <label class="form-label" for="contact_name">Your Name</label>
              <input type="text" id="contact_name" name="name" class="form-input" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="contact_email">Email Address</label>
              <input type="email" id="contact_email" name="email" class="form-input" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="contact_subject">Subject</label>
              <select id="contact_subject" name="subject" class="form-input" required>
                <option value="">Select a topic...</option>
                <option value="booking">Booking Question</option>
                <option value="availability">Availability Check</option>
                <option value="complaint">Complaint</option>
                <option value="general">General Inquiry</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="contact_message">Message</label>
              <textarea id="contact_message" name="message" class="form-input form-textarea" rows="6" required placeholder="Tell us how we can help..."></textarea>
            </div>

            <button type="submit" class="btn-primary">Send Message</button>
          </form>
        </div>

        <!-- Right Column — Business Info -->
        <div class="contact-info-col">
          <div class="contact-info-card">
            <h3>Get in Touch</h3>

            <div class="contact-detail">
              <span class="contact-detail-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B5E3C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
              </span>
              <div>
                <strong>Address</strong>
                <p>123 Paw Street<br>San Bernardino, CA 92407</p>
              </div>
            </div>

            <div class="contact-detail">
              <span class="contact-detail-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B5E3C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                </svg>
              </span>
              <div>
                <strong>Phone</strong>
                <p><a href="tel:+19095559663">(909) 555-WOOF</a></p>
              </div>
            </div>

            <div class="contact-detail">
              <span class="contact-detail-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B5E3C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                  <polyline points="22,6 12,13 2,6"/>
                </svg>
              </span>
              <div>
                <strong>Email</strong>
                <p><a href="mailto:hello@rentadog.local">hello@rentadog.local</a></p>
              </div>
            </div>

            <div class="contact-detail">
              <span class="contact-detail-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B5E3C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12 6 12 12 16 14"/>
                </svg>
              </span>
              <div>
                <strong>Hours</strong>
                <p>Mon &ndash; Sat: 9 am &ndash; 7 pm<br>Sun: 10 am &ndash; 5 pm</p>
              </div>
            </div>

          </div><!-- /.contact-info-card -->
        </div>

      </div><!-- /.contact-grid -->

    </div><!-- /.container -->
  </main>

<?php require_once '../includes/footer.php'; ?>
