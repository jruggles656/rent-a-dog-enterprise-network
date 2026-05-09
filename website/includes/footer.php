    <!-- ============================================================
         SITE FOOTER
         ============================================================ -->
    <footer class="site-footer">
        <div class="footer-grid">
            <!-- Column 1 — Brand -->
            <div class="footer-col footer-brand">
                <a href="<?= $base_path ?>/" class="footer-logo">Rent a Dog</a>
                <p class="footer-tagline">Your perfect day, one paw at a time</p>
                <p class="footer-description">
                    We connect dog lovers with friendly, vetted pups for
                    unforgettable adventures. Whether it&rsquo;s a hike, a
                    beach day, or simply a cozy afternoon, the perfect
                    companion is just a click away.
                </p>
            </div>

            <!-- Column 2 — Quick Links -->
            <div class="footer-col footer-links">
                <h4 class="footer-heading">Explore</h4>
                <ul>
                    <li><a href="<?= $base_path ?>/">Home</a></li>
                    <li><a href="<?= $base_path ?>/pages/breeds.php">Breeds</a></li>
                    <li><a href="<?= $base_path ?>/pages/experience.php">Experiences</a></li>
                    <li><a href="<?= $base_path ?>/pages/about.php">About</a></li>
                    <li><a href="<?= $base_path ?>/pages/contact.php">Contact</a></li>
                    <li><a href="<?= $base_path ?>/pages/helpdesk.php">Help Desk</a></li>
                </ul>
            </div>

            <!-- Column 3 — Contact Info -->
            <div class="footer-col footer-contact">
                <h4 class="footer-heading">Get in Touch</h4>
                <address>
                    <p>123 Paw Street, San Bernardino, CA</p>
                    <p><a href="tel:+19095559663">(909) 555-WOOF</a></p>
                    <p><a href="mailto:hello@rentadog.local">hello@rentadog.local</a></p>
                    <p>Mon &ndash; Sat &middot; 9 am &ndash; 7 pm</p>
                </address>
            </div>
        </div>

        <hr class="footer-rule">

        <p class="footer-copy">
            &copy; 2026 Rent a Dog. All rights reserved. Made with
            <span class="paw-icon" aria-label="love">&nbsp;&#128062;&nbsp;</span>
            by Team 6
        </p>
    </footer>

    <!-- ============================================================
         CART SLIDE-OUT PANEL
         ============================================================ -->
    <div class="cart-overlay" id="cartOverlay"></div>

    <div class="cart-panel" id="cartPanel">
        <div class="cart-header">
            <h2 class="cart-title">Your Cart</h2>
            <button class="cart-close" id="cartClose" aria-label="Close cart">&times;</button>
        </div>

        <div class="cart-items" id="cartItems">
            <!-- Populated dynamically by main.js -->
        </div>

        <div class="cart-empty" id="cartEmpty">
            <span class="cart-empty-icon" aria-hidden="true">&#128062;</span>
            <p class="cart-empty-text">Your cart is lonely</p>
        </div>

        <div class="cart-footer" id="cartFooter">
            <div class="cart-subtotal">
                <span>Subtotal</span>
                <span id="cartSubtotal">$0.00</span>
            </div>
            <a href="<?= $base_path ?>/pages/checkout.php" class="btn btn-primary cart-checkout-btn">Checkout</a>
            <button class="cart-continue" id="cartContinue">Continue Browsing</button>
        </div>
    </div>

    <!-- ============================================================
         BARK BOT
         ============================================================ -->
    <div class="barkbot" id="barkbot">
        <!-- Floating avatar -->
        <div class="barkbot-avatar" id="barkbotAvatar">
            <img src="<?= $base_path ?>/images/barkbot-avatar.png" alt="Bark Bot" width="64" height="64">
        </div>

        <!-- Speech bubble (appears after delay, auto-dismisses) -->
        <div class="barkbot-bubble" id="barkbotBubble">
            Woof! Need help? &#128062;
        </div>

        <!-- Chat panel (opens on avatar click) -->
        <div class="barkbot-panel" id="barkbotPanel">
            <div class="barkbot-panel-header">
                <img src="<?= $base_path ?>/images/barkbot-avatar.png" alt="Bark Bot" class="barkbot-panel-avatar" width="40" height="40">
                <div>
                    <div class="barkbot-panel-name">Bark Bot</div>
                    <div class="barkbot-panel-subtitle">Your personal pup advisor</div>
                </div>
                <button class="barkbot-close" id="barkbotClose" aria-label="Close chat">&times;</button>
            </div>

            <div class="barkbot-messages" id="barkbotMessages">
                <!-- Messages populated by barkbot.js -->
            </div>

            <div class="barkbot-quick-replies" id="barkbotQuickReplies">
                <!-- Quick reply buttons populated by barkbot.js -->
            </div>

            <div class="barkbot-input">
                <input type="text" id="barkbotInput" placeholder="Ask me anything..." autocomplete="off">
                <button class="barkbot-send" id="barkbotSend" aria-label="Send message">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>const BASE_PATH = '<?= $base_path ?>';</script>
    <script src="<?= $base_path ?>/js/main.js"></script>
    <script src="<?= $base_path ?>/js/barkbot.js"></script>
</body>
</html>
