<?php
/**
 * Rent a Dog — Data Layer
 * IST 4910 Team 6
 *
 * Queries PostgreSQL (10.0.1.200) for dogs, breeds, and experience packages.
 * Falls back to hardcoded sample data if the database is unreachable.
 *
 * All page templates reference: $dogs, $experiences, and cart functions.
 */

session_start();

// ── Database connection ─────────────────────────────────────────
require_once __DIR__ . '/db.php';

// ── Base path resolution ────────────────────────────────────────
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
if (str_contains($script_dir, '/pages')) {
    $base_path = dirname($script_dir);
} else {
    $base_path = $script_dir;
}
$base_path = ($base_path === '/' || $base_path === '\\') ? '' : rtrim($base_path, '/');


// ============================================================
// DOGS — from database (dogs JOIN breeds)
// ============================================================

$dogs = [];

if ($db_available) {
    try {
        $stmt = $pdo->query("
            SELECT
                d.dog_id    AS id,
                d.name,
                b.name      AS breed,
                b.tier,
                b.hourly_rate AS rate,
                d.age,
                d.weight,
                d.status,
                d.photo_url AS photo,
                b.description
            FROM dogs d
            JOIN breeds b ON d.breed_id = b.breed_id
            ORDER BY
                CASE b.tier WHEN 'Basic' THEN 1 WHEN 'Premium' THEN 2 WHEN 'VIP' THEN 3 END,
                d.dog_id
        ");
        $dogs = $stmt->fetchAll();

        // Cast numeric fields for template compatibility
        foreach ($dogs as &$dog) {
            $dog['id']     = (int) $dog['id'];
            $dog['rate']   = (float) $dog['rate'];
            $dog['age']    = (int) $dog['age'];
            $dog['weight'] = (float) $dog['weight'];
        }
        unset($dog);
    } catch (PDOException $e) {
        error_log('[RentaDog] Dogs query failed: ' . $e->getMessage());
        $dogs = [];
    }
}

// Fallback: hardcoded sample data if DB is down or returned empty
if (empty($dogs)) {
    $dogs = [
        ['id' => 1, 'name' => 'Biscuit',  'breed' => 'Labrador Retriever', 'tier' => 'Basic',   'rate' => 15, 'age' => 3, 'weight' => 65, 'status' => 'available', 'photo' => '/images/dog1.jpg', 'description' => 'A gentle soul who loves belly rubs and long walks. Biscuit is the perfect companion for a relaxing afternoon.'],
        ['id' => 2, 'name' => 'Pepper',   'breed' => 'Beagle',            'tier' => 'Basic',   'rate' => 15, 'age' => 2, 'weight' => 25, 'status' => 'available', 'photo' => '/images/dog2.jpg', 'description' => 'Curious and playful, Pepper will sniff out every adventure. Great for energetic outings and park days.'],
        ['id' => 3, 'name' => 'Maple',    'breed' => 'Poodle',            'tier' => 'Basic',   'rate' => 18, 'age' => 4, 'weight' => 45, 'status' => 'available', 'photo' => '/images/dog3.jpg', 'description' => 'Elegant and smart, Maple loves to show off tricks. A wonderful companion for cafe visits and quiet afternoons.'],
        ['id' => 4, 'name' => 'Sunny',    'breed' => 'Golden Retriever',  'tier' => 'Premium', 'rate' => 25, 'age' => 2, 'weight' => 70, 'status' => 'available', 'photo' => '/images/dog4.jpg', 'description' => 'Pure sunshine in dog form. Sunny greets everyone with a wagging tail and is amazing with kids.'],
        ['id' => 5, 'name' => 'Luna',     'breed' => 'Husky',             'tier' => 'Premium', 'rate' => 28, 'age' => 3, 'weight' => 50, 'status' => 'rented',    'photo' => '/images/dog5.jpg', 'description' => 'Striking blue eyes and endless energy. Luna loves outdoor adventures and will keep up with any pace.'],
        ['id' => 6, 'name' => 'Chester',  'breed' => 'Corgi',             'tier' => 'Premium', 'rate' => 25, 'age' => 1, 'weight' => 28, 'status' => 'available', 'photo' => '/images/dog6.jpg', 'description' => 'Short legs, big personality. Chester waddles into your heart and never leaves. Instagram-famous material.'],
        ['id' => 7, 'name' => 'Mochi',    'breed' => 'French Bulldog',    'tier' => 'VIP',     'rate' => 40, 'age' => 2, 'weight' => 24, 'status' => 'available', 'photo' => '/images/dog7.jpg', 'description' => 'The ultimate lap dog with a face that melts hearts. Mochi loves snuggles, snacks, and being the center of attention.'],
        ['id' => 8, 'name' => 'Cloud',    'breed' => 'Samoyed',           'tier' => 'VIP',     'rate' => 45, 'age' => 3, 'weight' => 55, 'status' => 'available', 'photo' => '/images/dog8.jpg', 'description' => 'A fluffy cloud that walks. Cloud\'s signature smile and soft white fur make every moment feel magical.'],
    ];
}


// ============================================================
// EXPERIENCES — from database (experience_packages)
// ============================================================

// Supplementary data not stored in the DB (marketing copy, photos, includes lists).
// Keyed by package name prefix so we can enrich DB rows.
$experience_extras = [
    'Dog Cafe' => [
        'long_description' => 'Step into our warm, dog-friendly cafe where the aroma of fresh coffee meets the joy of canine companionship. Choose your favorite brew from our artisan menu while a friendly pup settles in beside you. Our cafe is designed for comfort — plush seating, warm lighting, and plenty of room for your four-legged date. Whether you\'re catching up on a book, working on your laptop, or simply soaking in the moment, a Dog Cafe session is pure bliss.',
        'photo' => '/images/exp1.jpg',
        'includes' => ['Artisan coffee or tea', 'Fresh-baked pastry', 'Time with your chosen dog', 'Cozy indoor seating', 'Dog treats provided'],
        'group_size' => '1-4 people',
        'location' => 'Rent a Dog Cafe — Downtown',
    ],
    'Dog Yoga' => [
        'long_description' => 'Discover a new dimension of mindfulness with Dog Yoga — or as we like to call it, "Doga." Our certified instructor guides you through a gentle flow in our beautiful outdoor space, while a calm, trained dog companion adds warmth and grounding energy to your practice. Dogs have a natural ability to help us stay present. Feel the stress melt away as you stretch, breathe, and bond with your furry yoga partner under the open sky.',
        'photo' => '/images/exp2.jpg',
        'includes' => ['Certified yoga instructor', 'Yoga mat provided', 'Guided session', 'Calm, trained dog partner', 'Herbal tea afterward'],
        'group_size' => '1-8 people',
        'location' => 'Rent a Dog Garden — Outdoor Pavilion',
    ],
    'Dog Garden' => [
        'long_description' => 'Our private Dog Garden is your personal paradise — a lush, fenced outdoor space where you and your chosen pup can run wild, play fetch, roll in the grass, or simply lounge in the sunshine. With plenty of time, there\'s no rush. We provide toys, water stations, and shaded areas so both you and your dog stay comfortable. It\'s the closest thing to having your own backyard — complete with the world\'s best company.',
        'photo' => '/images/exp3.jpg',
        'includes' => ['Private fenced garden', 'Playtime with your dog', 'Toys and fetch equipment', 'Water station and shade areas', 'Dog treats included'],
        'group_size' => '1-6 people',
        'location' => 'Rent a Dog Garden — East Side',
    ],
];

$experiences = [];

if ($db_available) {
    try {
        // Only pull individual packages for the customer-facing site
        $stmt = $pdo->query("
            SELECT
                package_id  AS id,
                name,
                price,
                duration_minutes AS duration,
                description
            FROM experience_packages
            WHERE package_type = 'individual'
            ORDER BY package_id
        ");
        $db_experiences = $stmt->fetchAll();

        foreach ($db_experiences as $exp) {
            $exp['id']       = (int) $exp['id'];
            $exp['price']    = (float) $exp['price'];
            $exp['duration'] = (int) $exp['duration'];

            // Match extras by name prefix (e.g. "Dog Cafe Single" matches "Dog Cafe")
            foreach ($experience_extras as $prefix => $extras) {
                if (str_starts_with($exp['name'], $prefix)) {
                    // Use the short display name for the site
                    $exp['name'] = $prefix;
                    $exp = array_merge($exp, $extras);
                    break;
                }
            }

            // Ensure photo exists even if no extras matched
            if (!isset($exp['photo'])) {
                $exp['photo'] = '/images/exp' . $exp['id'] . '.jpg';
            }

            $experiences[] = $exp;
        }
    } catch (PDOException $e) {
        error_log('[RentaDog] Experiences query failed: ' . $e->getMessage());
        $experiences = [];
    }
}

// Fallback: hardcoded sample data if DB is down or returned empty
if (empty($experiences)) {
    $experiences = [
        ['id' => 1, 'name' => 'Dog Cafe',   'price' => 35, 'duration' => 90,  'description' => 'Enjoy artisan coffee and pastries in our cozy cafe with a furry companion by your side. Perfect for a relaxing afternoon.',   'long_description' => $experience_extras['Dog Cafe']['long_description'],   'photo' => '/images/exp1.jpg', 'includes' => $experience_extras['Dog Cafe']['includes'],   'group_size' => '1-4 people', 'location' => 'Rent a Dog Cafe — Downtown'],
        ['id' => 2, 'name' => 'Dog Yoga',   'price' => 45, 'duration' => 60,  'description' => 'Find your zen with a guided outdoor yoga session alongside a calm, trained dog partner. Great for stress relief and mindfulness.', 'long_description' => $experience_extras['Dog Yoga']['long_description'],   'photo' => '/images/exp2.jpg', 'includes' => $experience_extras['Dog Yoga']['includes'],   'group_size' => '1-8 people', 'location' => 'Rent a Dog Garden — Outdoor Pavilion'],
        ['id' => 3, 'name' => 'Dog Garden', 'price' => 30, 'duration' => 120, 'description' => 'Spend time in our private garden with your chosen dog. Run, play fetch, or just relax in the sunshine together.',              'long_description' => $experience_extras['Dog Garden']['long_description'], 'photo' => '/images/exp3.jpg', 'includes' => $experience_extras['Dog Garden']['includes'], 'group_size' => '1-6 people', 'location' => 'Rent a Dog Garden — East Side'],
    ];
}


// ============================================================
// CART HELPER FUNCTIONS
// ============================================================

function getCart() {
    return $_SESSION['cart'] ?? [];
}

function addToCart($type, $id, $hours = 1) {
    global $dogs, $experiences;

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($type === 'dog') {
        $item = null;
        foreach ($dogs as $dog) {
            if ($dog['id'] == $id) { $item = $dog; break; }
        }
        if (!$item) return false;

        $_SESSION['cart'][] = [
            'type' => 'dog',
            'id' => $item['id'],
            'name' => $item['name'],
            'breed' => $item['breed'],
            'tier' => $item['tier'],
            'rate' => $item['rate'],
            'hours' => min(8, max(1, (int)$hours)),
            'photo' => $item['photo'],
        ];
    } elseif ($type === 'experience') {
        $item = null;
        foreach ($experiences as $exp) {
            if ($exp['id'] == $id) { $item = $exp; break; }
        }
        if (!$item) return false;

        $_SESSION['cart'][] = [
            'type' => 'experience',
            'id' => $item['id'],
            'name' => $item['name'],
            'rate' => $item['price'],
            'hours' => 1,
            'duration' => $item['duration'],
            'photo' => $item['photo'],
        ];
    }

    return true;
}

function removeFromCart($index) {
    if (isset($_SESSION['cart'][$index])) {
        array_splice($_SESSION['cart'], $index, 1);
        return true;
    }
    return false;
}

function updateCartItem($index, $hours) {
    if (isset($_SESSION['cart'][$index])) {
        $_SESSION['cart'][$index]['hours'] = min(8, max(1, (int)$hours));
        return true;
    }
    return false;
}

function getCartTotal() {
    $total = 0;
    foreach (getCart() as $item) {
        $total += $item['rate'] * $item['hours'];
    }
    return $total;
}

function getCartCount() {
    return count(getCart());
}

function clearCart() {
    $_SESSION['cart'] = [];
}
