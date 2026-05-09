<?php
require_once __DIR__ . '/data.php';
$page_title = $page_title ?? 'Rent a Dog';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> &mdash; Rent a Dog</title>
  <!-- Prevent flash: apply saved theme before paint -->
  <script>
    (function() {
      var t = localStorage.getItem('rad-theme') || 'dark';
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300..900&family=DM+Sans:wght@400;500;600;700&family=Caveat:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base_path ?>/css/style.css">
</head>
<body>

  <!-- ========== STICKY NAVIGATION ========== -->
  <nav class="nav" id="mainNav">
    <div class="container nav-container">

      <!-- Logo -->
      <a href="<?= $base_path ?>/" class="nav-logo">
        <span class="nav-logo-text">Rent a Dog</span>
        <!-- Inline SVG paw print -->
        <svg class="nav-logo-paw" width="28" height="28" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <!-- Main pad -->
          <ellipse cx="50" cy="65" rx="22" ry="18" fill="#8B5E3C"/>
          <!-- Top-left toe -->
          <ellipse cx="28" cy="35" rx="10" ry="13" transform="rotate(-15 28 35)" fill="#8B5E3C"/>
          <!-- Top-right toe -->
          <ellipse cx="72" cy="35" rx="10" ry="13" transform="rotate(15 72 35)" fill="#8B5E3C"/>
          <!-- Inner-left toe -->
          <ellipse cx="38" cy="28" rx="9" ry="12" transform="rotate(5 38 28)" fill="#8B5E3C"/>
          <!-- Inner-right toe -->
          <ellipse cx="62" cy="28" rx="9" ry="12" transform="rotate(-5 62 28)" fill="#8B5E3C"/>
        </svg>
        <span class="nav-tagline">Your perfect day, one paw at a time</span>
      </a>

      <!-- Desktop nav links -->
      <ul class="nav-links" id="navLinks">
        <li><a href="<?= $base_path ?>/pages/breeds.php" class="nav-link">Breeds</a></li>
        <li><a href="<?= $base_path ?>/#experiences" class="nav-link">Experiences</a></li>
        <li><a href="<?= $base_path ?>/pages/about.php" class="nav-link">About</a></li>
        <li><a href="<?= $base_path ?>/pages/contact.php" class="nav-link">Contact</a></li>
        <li><a href="<?= $base_path ?>/pages/helpdesk.php" class="nav-link">Help Desk</a></li>
      </ul>

      <!-- Theme Toggle -->
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <!-- Sun icon (shown in dark mode — click to go light) -->
        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1" x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1" y1="12" x2="3" y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
        <!-- Moon icon (shown in light mode — click to go dark) -->
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
      </button>

      <!-- Cart icon -->
      <a href="<?= $base_path ?>/pages/cart.php" class="nav-cart" aria-label="Shopping cart">
        <!-- Inline SVG shopping bag -->
        <svg class="nav-cart-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#8B5E3C" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 01-8 0"/>
        </svg>
        <?php if (getCartCount() > 0): ?>
          <span class="cart-count"><?= getCartCount() ?></span>
        <?php else: ?>
          <span class="cart-count" style="display:none;">0</span>
        <?php endif; ?>
      </a>

      <!-- Hamburger menu button (mobile only) -->
      <button class="nav-hamburger" id="navHamburger" aria-label="Open menu" aria-expanded="false">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#8B5E3C" stroke-width="2" stroke-linecap="round" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <line x1="3" y1="6" x2="21" y2="6"/>
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>

    </div><!-- /.nav-container -->

    <!-- Mobile menu dropdown (hidden by default) -->
    <div class="nav-mobile-menu" id="navMobileMenu">
      <ul class="nav-mobile-links">
        <li><a href="<?= $base_path ?>/pages/breeds.php" class="nav-link">Breeds</a></li>
        <li><a href="<?= $base_path ?>/#experiences" class="nav-link">Experiences</a></li>
        <li><a href="<?= $base_path ?>/pages/about.php" class="nav-link">About</a></li>
        <li><a href="<?= $base_path ?>/pages/contact.php" class="nav-link">Contact</a></li>
        <li><a href="<?= $base_path ?>/pages/helpdesk.php" class="nav-link">Help Desk</a></li>
      </ul>
    </div>
  </nav>
