# Rent a Dog

PHP e-commerce web app for renting dogs by tier and booking dog experiences.

## Dev Server
- PHP built-in server at `localhost:8000`
- Start: `php -S localhost:8000` from the project root

## Tech Stack
- **Backend**: PHP (no framework), session-based cart, no database yet (planned PostgreSQL)
- **Frontend**: Vanilla JS, CSS with CSS custom properties
- **No build tools** — plain files served directly

## Project Structure
```
index.php              — Homepage (hero, tier cards, dog grid, experiences, CTA)
pages/                 — breeds.php, checkout.php, confirmation.php, about.php, contact.php, experience.php
pages/helpdesk.php     — Customer help desk: ticket submission + lookup with status timeline
pages/admin_helpdesk.php — Staff help desk dashboard: metrics, ticket management, notes
includes/header.php    — Nav, theme toggle, cart icon, mobile menu, Bark Bot avatar
includes/footer.php    — Footer, cart slide-out panel, Bark Bot chat widget
includes/data.php      — Sample dog/experience arrays, cart helper functions, base path
css/style.css          — All styles, dark/light mode via CSS variables
js/main.js             — Scroll reveal, parallax, tier filtering, cart (AJAX), theme toggle, toast notifications
js/barkbot.js          — Chat widget (sends to /api/chat.php) + help desk ticket escalation
api/chat.php           — Bark Bot backend
api/contact_handler.php— Contact form handler
api/helpdesk_handler.php — Help desk ticket submission (form + AJAX from Bark Bot)
api/paypal_handler.php — PayPal Sandbox create/capture order (+ demo mode fallback)
includes/paypal_config.php — PayPal sandbox credentials + demo mode flag
includes/cart-actions.php — AJAX cart operations
```

## Key Patterns
- **Theme**: `data-theme="dark"|"light"` on `<html>`, persisted in `localStorage` key `rad-theme`
- **Cart**: Session-based (`$_SESSION['cart']`), AJAX via `includes/cart-actions.php`
- **Base path**: `$base` variable from `data.php` handles URL paths
- **Animations**: `.reveal` class + IntersectionObserver for scroll animations

## Data
- 8 dogs across 3 tiers: Basic ($15-18/hr), Premium ($25-28/hr), VIP ($40-45/hr)
- 3 experiences: Dog Cafe ($35), Dog Yoga ($45), Dog Garden ($30)

## Completed Features
- Full dark/light mode with CSS variables and toggle
- Responsive navigation with hamburger menu
- Session-based shopping cart with slide-out panel
- Tier-based filtering on homepage and breeds page
- Bark Bot chatbot widget
- Scroll reveal animations and parallax
- Toast notification system
- Scroll fix: tier buttons + dog cards both visible after clicking Basic/Premium/VIP
- Fixed broken Unsplash images for Maple (Poodle) and Mochi (French Bulldog) — old photo IDs were 404ing
- Help desk ticketing system (Task 4.5): customer submission + lookup, admin dashboard with metrics
- Bark Bot → Help Desk escalation: auto-detects issues and offers to create tickets from chat
- Help desk metrics: total tickets, open/resolved counts, avg resolution time, breakdowns by category/priority
- PayPal Sandbox checkout integration — live with Jason's sandbox credentials, full create/capture flow via PayPal JS SDK + server-side REST API v2
- Confirmation page shows PayPal transaction reference and payment badge

## Known Issues (Security — not yet fixed)
- **HIGH**: XSS risk in `renderCartItems()` (main.js ~line 398-420) and `showToast()` (~line 450-453) — uses innerHTML with unsanitized data
- **MEDIUM**: No CSRF tokens on forms (contact, cart actions)
- **MEDIUM**: `cart-actions.php` accepts GET for state-changing operations (should be POST-only)
- **LOW**: No rate limiting on contact form or chat API
- **LOW**: Session cookies missing `httponly`/`secure`/`samesite` flags

## Dev Notes
- Preview server config: `Second Brain/.claude/launch.json` (uses `-t` flag to point at rentadog dir)
- Nav height is ~66px — keep in mind for scroll offset calculations
- `scroll-margin-top` on `.tier-cards` (5rem) handles nav clearance for scrollIntoView
- Scroll target for tier click is `.tier-cards` (not `.dog-reveal`) so tier buttons stay visible
