/**
 * Rent a Dog — Main JavaScript
 * Handles all site interactivity except Bark Bot (see barkbot.js).
 *
 * Features:
 *   1. Scroll Reveal (IntersectionObserver)
 *   2. Navigation Scroll Effect
 *   3. Parallax on Experience Sections
 *   4. Tier Filter (Homepage + Breeds Page)
 *   5. Cart Slide-Out Panel
 *   6. Cart AJAX Handlers
 *   7. Cart Count Badge
 *   8. Toast Notifications
 *   9. Hamburger Menu Toggle
 *  10. Smooth Scroll for Anchor Links
 *  11. Event Delegation for Dynamic Elements
 */

/* ============================================================
   CART ENDPOINT
   BASE_PATH is injected by footer.php:
   <script>const BASE_PATH = '<?= $base_path ?>';</script>
   ============================================================ */
const CART_URL = (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '') + '/includes/cart-actions.php';


/* ============================================================
   1. SCROLL REVEAL SYSTEM
   Uses IntersectionObserver to animate .reveal elements into
   view once they cross the viewport threshold.
   ============================================================ */
function initScrollReveal() {
  const revealElements = document.querySelectorAll('.reveal');
  if (!revealElements.length) return;

  const observer = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;

      const el = entry.target;
      el.classList.add('visible');

      // Stagger children that declare a data-reveal-delay
      const delayed = el.querySelectorAll('[data-reveal-delay]');
      delayed.forEach(child => {
        const delay = child.getAttribute('data-reveal-delay');
        child.style.transitionDelay = delay + 'ms';
      });

      // One-time animation — stop observing after reveal
      obs.unobserve(el);
    });
  }, {
    threshold: 0.15,
    rootMargin: '0px 0px -40px 0px'
  });

  revealElements.forEach(el => observer.observe(el));
}


/* ============================================================
   2. NAVIGATION SCROLL EFFECT
   Adds .nav-scrolled when the page is scrolled past 50px so
   the nav can switch to a compact / opaque style via CSS.
   ============================================================ */
function initNavScroll() {
  const nav = document.getElementById('mainNav');
  if (!nav) return;

  let ticking = false;

  const onScroll = () => {
    if (ticking) return;
    ticking = true;

    requestAnimationFrame(() => {
      if (window.scrollY > 50) {
        nav.classList.add('nav-scrolled');
      } else {
        nav.classList.remove('nav-scrolled');
      }
      ticking = false;
    });
  };

  window.addEventListener('scroll', onScroll, { passive: true });

  // Set initial state in case the page loads already scrolled
  onScroll();
}


/* ============================================================
   3. PARALLAX EFFECT ON EXPERIENCE SECTIONS
   Translates [data-parallax] background elements on scroll.
   Disabled on mobile for performance.
   ============================================================ */
function initParallax() {
  const parallaxEls = document.querySelectorAll('[data-parallax]');
  if (!parallaxEls.length) return;

  let ticking = false;

  const updateParallax = () => {
    // Disable on narrow screens
    if (window.innerWidth < 768) return;

    parallaxEls.forEach(el => {
      const section = el.closest('section') || el.parentElement;
      const rect = section.getBoundingClientRect();
      const windowH = window.innerHeight;

      // Only calculate when the section is in or near the viewport
      if (rect.bottom < -100 || rect.top > windowH + 100) return;

      const scrollOffset = rect.top / windowH;
      el.style.transform = `translateY(${scrollOffset * 0.3 * 100}px)`;
    });
  };

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      updateParallax();
      ticking = false;
    });
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', () => {
    // Reset transforms if we cross the mobile breakpoint
    if (window.innerWidth < 768) {
      parallaxEls.forEach(el => { el.style.transform = ''; });
    }
  }, { passive: true });

  // Initial calculation
  updateParallax();
}


/* ============================================================
   4. TIER FILTER (Homepage + Breeds Page)
   Homepage:  clicking a .tier-card reveals #dogReveal and
              filters cards by the selected tier.
   Breeds:    clicking a .filter-btn filters .dog-card elements.
   ============================================================ */
function initTierFilter() {
  initHomepageTierFilter();
  initBreedsTierFilter();
}

/** Homepage — tier card click reveals the dog grid */
function initHomepageTierFilter() {
  const tierCards = document.querySelectorAll('.tier-cards .tier-card');
  const dogReveal = document.getElementById('dogReveal');
  if (!tierCards.length || !dogReveal) return;

  tierCards.forEach(card => {
    card.addEventListener('click', () => {
      const tier = card.getAttribute('data-tier');

      // Active state: highlight the clicked card
      tierCards.forEach(c => c.classList.remove('active'));
      card.classList.add('active');

      // Show the dog reveal section (CSS .dog-reveal.visible sets display:grid)
      dogReveal.classList.add('visible');

      // Filter dog cards within the reveal section
      const dogCards = dogReveal.querySelectorAll('.dog-card');
      dogCards.forEach(dc => {
        const match = dc.getAttribute('data-tier') === tier;
        dc.style.opacity = '0';
        dc.style.transform = 'translateY(12px)';

        setTimeout(() => {
          dc.style.display = match ? '' : 'none';
          if (match) {
            // Force reflow then animate in
            void dc.offsetWidth;
            dc.style.opacity = '1';
            dc.style.transform = 'translateY(0)';
          }
        }, 250);
      });

      // Smooth scroll so tier cards + dog grid are both visible
      setTimeout(() => {
        document.querySelector('.tier-cards').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 300);
    });
  });
}

/** Breeds page — filter bar buttons */
function initBreedsTierFilter() {
  const filterBtns = document.querySelectorAll('.filter-bar .filter-btn');
  if (!filterBtns.length) return;

  const dogCards = document.querySelectorAll('.dog-grid .dog-card');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const filter = btn.getAttribute('data-filter');

      // Active state
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      // Filter cards with a fade transition
      dogCards.forEach(card => {
        const tier = card.getAttribute('data-tier');
        const match = filter === 'all' || tier === filter;

        // Fade out first
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';

        setTimeout(() => {
          card.style.display = match ? '' : 'none';
          if (match) {
            void card.offsetWidth; // force reflow
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
          }
        }, 250);
      });
    });
  });
}


/* ============================================================
   5. CART SLIDE-OUT SYSTEM
   Opens / closes the cart panel and overlay.
   ============================================================ */
function initCart() {
  const overlay  = document.getElementById('cartOverlay');
  const panel    = document.getElementById('cartPanel');
  const closeBtn = document.getElementById('cartClose');
  const contBtn  = document.getElementById('cartContinue');

  // Cart icon in the nav — open the slide-out instead of navigating
  const cartLink = document.querySelector('.nav-cart');
  if (cartLink) {
    cartLink.addEventListener('click', (e) => {
      e.preventDefault();
      openCart();
    });
  }

  // Close triggers
  if (overlay)  overlay.addEventListener('click', closeCart);
  if (closeBtn) closeBtn.addEventListener('click', closeCart);
  if (contBtn)  contBtn.addEventListener('click', closeCart);

  // Escape key closes cart
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeCart();
  });

  // Event delegation for cart item buttons and add-to-cart buttons
  initCartDelegation();
}

/** Open the cart panel, fetch latest data, render items */
function openCart() {
  const overlay = document.getElementById('cartOverlay');
  const panel   = document.getElementById('cartPanel');
  if (overlay) overlay.classList.add('active');
  if (panel)   panel.classList.add('active');
  document.body.style.overflow = 'hidden';

  // Fetch and render current cart
  fetchCart();
}

/** Close the cart panel */
function closeCart() {
  const overlay = document.getElementById('cartOverlay');
  const panel   = document.getElementById('cartPanel');
  if (overlay) overlay.classList.remove('active');
  if (panel)   panel.classList.remove('active');
  document.body.style.overflow = '';
}


/* ============================================================
   6. CART AJAX HANDLERS
   Communicates with cart-actions.php via fetch().
   ============================================================ */

/** Fetch current cart contents and re-render */
function fetchCart() {
  fetch(CART_URL + '?action=get', {
    credentials: 'same-origin'
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        renderCartItems(data.cart);
        updateCartBadge(data.count);
      }
    })
    .catch(err => console.error('Cart fetch error:', err));
}

/** Add an item to the cart */
function addToCart(type, id) {
  const body = new URLSearchParams({ action: 'add', type, id });

  fetch(CART_URL, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString()
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast(data.message || 'Added to cart!');
        updateCartBadge(data.count);
        openCart();
      } else {
        showToast(data.message || 'Could not add item.', 'error');
      }
    })
    .catch(() => showToast('Something went wrong.', 'error'));
}

/** Update hours for a cart item */
function updateCartHours(index, hours) {
  const body = new URLSearchParams({ action: 'update', index, hours });

  fetch(CART_URL, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString()
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        renderCartItems(data.cart);
        updateCartBadge(data.count);
      }
    })
    .catch(err => console.error('Cart update error:', err));
}

/** Remove an item from the cart */
function removeCartItem(index) {
  const body = new URLSearchParams({ action: 'remove', index });

  fetch(CART_URL, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString()
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        renderCartItems(data.cart);
        updateCartBadge(data.count);
        showToast('Item removed.');
      }
    })
    .catch(err => console.error('Cart remove error:', err));
}

/**
 * Render cart items into the slide-out panel.
 * Handles empty state, item list, and subtotal calculation.
 */
function renderCartItems(items) {
  const container  = document.querySelector('.cart-items');
  const emptyState = document.querySelector('.cart-empty');
  const cartFooter = document.querySelector('.cart-footer');

  if (!container) return;

  // Empty cart state
  if (!items || items.length === 0) {
    container.innerHTML = '';
    if (emptyState)  emptyState.style.display = 'block';
    if (cartFooter)  cartFooter.style.display  = 'none';
    return;
  }

  if (emptyState)  emptyState.style.display = 'none';
  if (cartFooter)  cartFooter.style.display  = 'block';

  // Build item HTML
  container.innerHTML = items.map((item, index) => `
    <div class="cart-item">
      <img src="${item.photo}" alt="${item.name}" class="cart-item-image">
      <div class="cart-item-details">
        <div class="cart-item-name">${item.name}</div>
        <div class="cart-item-meta">${item.type === 'dog'
          ? item.breed + ' &middot; ' + item.tier
          : item.duration + ' min session'
        }</div>
        ${item.type === 'dog' ? `
        <div class="hours-control">
          <button class="hours-btn" data-action="decrease" data-index="${index}">&minus;</button>
          <span>${item.hours} hr${item.hours > 1 ? 's' : ''}${item.hours >= 8 ? ' (max)' : ''}</span>
          <button class="hours-btn${item.hours >= 8 ? ' disabled' : ''}" data-action="increase" data-index="${index}" ${item.hours >= 8 ? 'disabled' : ''}>+</button>
        </div>` : ''}
        <div class="cart-item-price">$${item.type === 'dog'
          ? item.rate * item.hours
          : item.rate
        }</div>
      </div>
      <button class="cart-item-remove" data-index="${index}" aria-label="Remove item">&times;</button>
    </div>
  `).join('');

  // Update subtotal
  const total = items.reduce((sum, item) => sum + (item.rate * item.hours), 0);
  const subtotalEl = document.getElementById('cartSubtotal');
  if (subtotalEl) subtotalEl.textContent = '$' + total.toFixed(2);
}


/* ============================================================
   7. CART COUNT BADGE
   Updates all .cart-count badges (nav icon).
   ============================================================ */
function updateCartBadge(count) {
  const badges = document.querySelectorAll('.cart-count');
  badges.forEach(badge => {
    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';
  });
}


/* ============================================================
   8. TOAST NOTIFICATION SYSTEM
   Displays a brief notification at the bottom of the screen.
   Auto-removes after the CSS animation completes.
   ============================================================ */
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <span class="toast-icon">${type === 'success' ? '&#128062;' : '&#9888;'}</span>
    <span class="toast-message">${message}</span>
  `;
  document.body.appendChild(toast);

  // Trigger entrance animation on next frame
  requestAnimationFrame(() => toast.classList.add('toast-visible'));

  // Auto-remove after animation completes (~3.6s)
  setTimeout(() => {
    toast.classList.remove('toast-visible');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    // Fallback removal if transitionend doesn't fire
    setTimeout(() => { if (toast.parentNode) toast.remove(); }, 500);
  }, 3100);
}


/* ============================================================
   9. HAMBURGER MENU TOGGLE
   Toggles the mobile navigation menu open/closed.
   ============================================================ */
function initHamburger() {
  const hamburger  = document.getElementById('navHamburger');
  const mobileMenu = document.getElementById('navMobileMenu');
  if (!hamburger || !mobileMenu) return;

  // Toggle on hamburger click
  hamburger.addEventListener('click', () => {
    const isOpen = mobileMenu.classList.toggle('active');
    hamburger.setAttribute('aria-expanded', isOpen);
  });

  // Close when a link inside the mobile menu is clicked
  const mobileLinks = mobileMenu.querySelectorAll('a');
  mobileLinks.forEach(link => {
    link.addEventListener('click', () => {
      mobileMenu.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
    });
  });

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!mobileMenu.contains(e.target) && !hamburger.contains(e.target)) {
      mobileMenu.classList.remove('active');
      hamburger.setAttribute('aria-expanded', 'false');
    }
  });
}


/* ============================================================
   10. SMOOTH SCROLL FOR ANCHOR LINKS
   Intercepts clicks on in-page hash links and scrolls smoothly.
   ============================================================ */
function initSmoothScroll() {
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;

    const hash = link.getAttribute('href');
    if (hash === '#' || hash.length < 2) return;

    const target = document.querySelector(hash);
    if (!target) return;

    e.preventDefault();
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Update URL hash without jumping
    history.pushState(null, '', hash);
  });
}


/* ============================================================
   11. EVENT DELEGATION
   Handles dynamically-rendered buttons (add-to-cart, cart
   item controls) via a single listener on document.body.
   ============================================================ */
function initCartDelegation() {

  document.body.addEventListener('click', (e) => {

    // --- Add to Cart buttons ---
    const addBtn = e.target.closest('.add-to-cart-btn');
    if (addBtn) {
      e.preventDefault();
      const type = addBtn.getAttribute('data-type');
      const id   = addBtn.getAttribute('data-id');
      if (type && id) {
        addToCart(type, id);
      }
      return;
    }

    // --- Hours +/- buttons inside cart ---
    const hoursBtn = e.target.closest('.hours-btn');
    if (hoursBtn) {
      const action = hoursBtn.getAttribute('data-action');
      const index  = parseInt(hoursBtn.getAttribute('data-index'), 10);

      // Read current hours from the sibling <span>
      const hoursSpan = hoursBtn.parentElement.querySelector('span');
      let currentHours = parseInt(hoursSpan.textContent, 10) || 1;

      if (action === 'increase' && currentHours < 8) {
        currentHours += 1;
      } else if (action === 'decrease' && currentHours > 1) {
        currentHours -= 1;
      } else {
        return; // Already at min (1) or max (8)
      }

      updateCartHours(index, currentHours);
      return;
    }

    // --- Remove item button inside cart ---
    const removeBtn = e.target.closest('.cart-item-remove');
    if (removeBtn) {
      const index = parseInt(removeBtn.getAttribute('data-index'), 10);
      removeCartItem(index);
      return;
    }
  });
}


/* ============================================================
   12. THEME TOGGLE (Dark / Light Mode)
   Persists choice in localStorage. Default is dark.
   ============================================================ */
function initThemeToggle() {
  const toggle = document.getElementById('themeToggle');
  if (!toggle) return;

  toggle.addEventListener('click', () => {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', next);
    localStorage.setItem('rad-theme', next);

    // Update aria label
    toggle.setAttribute('aria-label',
      next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'
    );
  });
}


/* ============================================================
   INITIALIZATION
   Kick off all systems once the DOM is ready.
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  initScrollReveal();
  initNavScroll();
  initParallax();
  initTierFilter();
  initCart();
  initHamburger();
  initSmoothScroll();
  initThemeToggle();
});
