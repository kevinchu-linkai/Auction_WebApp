<?php
date_default_timezone_set('Europe/London');

// Database-backed listing
if (session_status() === PHP_SESSION_NONE) session_start();

// include DB connection (expects $connection = mysqli_connect(...))
require_once __DIR__ . '/database.php';

// read auction id (accept camelCase `auctionId`, snake_case `auction_id`, or `id`)
$auctionId = 0;
if (isset($_GET['auctionId'])) {
    $auctionId = (int) $_GET['auctionId'];
} elseif (isset($_GET['auction_id'])) {
    $auctionId = (int) $_GET['auction_id'];
} elseif (isset($_GET['id'])) {
    $auctionId = (int) $_GET['id'];
}
if ($auctionId <= 0) {
    http_response_code(400);
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Missing auction id</title></head><body>
    <div style="max-width:600px;margin:48px auto;font-family:system-ui,Segoe UI,Roboto,Arial;background:#fff;padding:20px;border-radius:8px;border:1px solid #eee;">
      <h2 style="margin:0 0 12px">Missing auction id</h2>
      <p>Please open this page with an auction id, for example:</p>
      <pre style="background:#f7f7f7;padding:8px;border-radius:4px">/Auction_WebApp/listing.php?auctionId=1</pre>
      <p><a href="/Auction_WebApp/" style="color:#0366d6">Return to listings</a></p>
    </div>
    </body></html>
    <?php
    exit;
}

// convenience
$connection = $GLOBALS['connection'] ?? ($connection ?? null);
if (!($connection instanceof mysqli)) {
    // try variable name 'conn' fallback
    if (isset($conn) && $conn instanceof mysqli) $connection = $conn;
}
if (!($connection instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection not available. Check database.php';
    exit;
}

// Load auction + item + category
$stmt = $connection->prepare(
    'SELECT a.auctionId,a.sellerId,a.startingPrice,a.reservePrice,a.startDate,a.endDate,a.state,a.finalPrice, '
    . 'i.itemId,i.name AS item_name,i.description,i.photo, i.`condition` AS item_condition, c.name AS category_name '
    . 'FROM Auction a '
    . 'JOIN Item i ON a.itemId = i.itemId '
    . 'JOIN Category c ON i.categoryId = c.categoryId '
    . 'WHERE a.auctionId = ? LIMIT 1'
);
$stmt->bind_param('i', $auctionId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    http_response_code(404);
    echo 'Auction not found';
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

// parse times and state
// `dbState` is the authoritative state stored in the Auction table.
$dbState = $row['state'];
$sellerId = isset($row['sellerId']) ? (int)$row['sellerId'] : 0;
$auctionStartTime = strtotime($row['startDate']);
$auctionEndTime = strtotime($row['endDate']);

// derive a display status from timestamps to handle small clock drifts / DB state lag
$now_ts = time();

// Prefer a timestamp-derived display status so the UI reflects the real-time schedule
// even if the DB `state` field hasn't been synchronized yet.
if ($dbState === 'cancelled') {
    // Always surface cancellation explicitly
    $displayStatus = 'cancelled';
} else {
    if ($now_ts < $auctionStartTime) {
        $displayStatus = 'not-started';
    } elseif ($now_ts >= $auctionStartTime && $now_ts < $auctionEndTime) {
        $displayStatus = 'ongoing';
    } elseif ($now_ts >= $auctionEndTime) {
        $displayStatus = 'expired';
    } else {
        // fallback to DB state if timestamps are missing/unparseable
        $displayStatus = $dbState;
    }
}

// get current highest bid
$stmt = $connection->prepare('SELECT MAX(bidAmount) AS maxBid FROM Bid WHERE auctionId = ?');
$stmt->bind_param('i', $auctionId);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
$maxBid = $r['maxBid'] !== null ? (float)$r['maxBid'] : null;
$currentBid = $maxBid !== null ? $maxBid : (float)$row['startingPrice'];

// handle POST actions (relist or place bid)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Relist action
    if (isset($_POST['relist'])) {
        // Basic authorization: must be logged in as seller and auction currently cancelled
        $isSeller = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'seller';
        // If your session stores seller id separately, adjust mapping here
        if ($dbState === 'cancelled' && $isSeller) {
            $up = $connection->prepare('UPDATE Auction SET state = ? WHERE auctionId = ? AND state = ?');
            $newState = 'ongoing';
            $oldState = 'cancelled';
            $up->bind_param('sis', $newState, $auctionId, $oldState);
            if ($up->execute()) {
                $up->close();
                $base = strtok($_SERVER['REQUEST_URI'], '?');
                header('Location: ' . $base . '?auctionId=' . $auctionId);
                exit;
            }
            $error = 'Failed to relist auction';
            $up->close();
        } else {
            $error = 'Unauthorized relist attempt';
        }
    } elseif (isset($_POST['bid_amount'])) {
        // (Bid logic retained but deactivated in UI; kept for completeness)
        $amountRaw = $_POST['bid_amount'] ?? '';
        $amount = floatval($amountRaw);
        $minBid = $currentBid + 50;
        if ($displayStatus !== 'ongoing') {
            $error = 'Bidding is not open for this auction';
        } elseif (empty($_SESSION['user_id'])) {
            $error = 'Please log in to place a bid';
        } elseif ($amount <= $currentBid) {
            $error = "Bid must be higher than current bid of $$currentBid";
        } elseif ($amount < $minBid) {
            $error = 'Minimum bid increment is $50';
        } else {
            $buyerId = (int)$_SESSION['user_id'];
            $ins = $connection->prepare('INSERT INTO Bid (auctionId,buyerId,bidAmount) VALUES (?,?,?)');
            $ins->bind_param('iid', $auctionId, $buyerId, $amount);
            if ($ins->execute()) {
                $base = strtok($_SERVER['REQUEST_URI'], '?');
                header('Location: ' . $base . '?auctionId=' . $auctionId);
                exit;
            } else {
                $error = 'Failed to record bid';
            }
            $ins->close();
        }
    }
}

// Keep the official auction status from the database for display/records,
// but use `$displayStatus` (derived from timestamps) for countdowns and bidding availability.
$auctionStatus = $dbState;

// Load bid history (most recent first)
$bids = [];
$stmt = $connection->prepare(
    'SELECT b.bidAmount, b.bidTime, COALESCE(u.username, "Bidder") AS bidder '
    . 'FROM Bid b LEFT JOIN Buyer u ON b.buyerId = u.buyerId '
    . 'WHERE b.auctionId = ? ORDER BY b.bidTime DESC'
);
$stmt->bind_param('i', $auctionId);
$stmt->execute();
$res = $stmt->get_result();
while ($br = $res->fetch_assoc()) {
    $bids[] = [
        'bidder' => $br['bidder'],
        'amount' => (float)$br['bidAmount'],
        'timestamp' => strtotime($br['bidTime']),
    ];
}
$stmt->close();

// template variables
$title = $row['item_name'];
$description = $row['description'];
$item_condition = $row['item_condition'] ?? '';
$current_price = $currentBid;
$num_bids = count($bids);
$bidHistory = $bids;
$minBid = $currentBid + 50;
$startingPrice = (float)($row['startingPrice'] ?? 0);
$reservePrice = array_key_exists('reservePrice', $row) ? (float)$row['reservePrice'] : null;

// Helper: human-readable time ago
function timeAgoSimple($ts) {
    $now = time(); $diff = $now - $ts;
    if ($diff < 60) return 'Just now';
    $m = floor($diff/60); if ($m<60) return $m.'m ago';
    $h = floor($diff/3600); if ($h<24) return $h.'h ago';
    return floor($diff/86400).'d ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Premium Auctions</title>
    <!-- Tailwind via prebuilt CSS (no <script>) -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
    >
</head>
<body class="bg-gray-50">

<header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <h1 class="text-xl font-semibold">Premium Auctions</h1>
    </div>
    </header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="mylistings.php" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
            &larr;&nbsp;Back to My listings
        </a>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column - Image Gallery -->
        <div>
            <div class="bg-white rounded-lg overflow-hidden aspect-square border border-gray-200">
                <?php
                $photo = $row['photo'] ?? '';
                $photoUrl = $photo ? $photo : 'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?w=800&q=80';
                ?>
                <img
                    src="<?= htmlspecialchars($photoUrl) ?>"
                    alt="<?= htmlspecialchars($title) ?>"
                    class="w-full h-full object-cover"
                >
            </div>
        </div>

        <!-- Right Column - Auction Details -->
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold"><?= htmlspecialchars($title) ?></h1>
            </div>

            <!-- AuctionTimer (PHP-only countdown) -->
            <?php if ($displayStatus === 'finished'): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center gap-2">
                        <span class="text-green-900 font-semibold">Auction Ended</span>
                    </div>
                    <p class="text-green-700 mt-2">This auction has successfully concluded.</p>
                </div>
            <?php elseif ($displayStatus === 'cancelled'): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center gap-2">
                        <span class="text-red-900 font-semibold">Auction Cancelled</span>
                    </div>
                    <p class="text-red-700 mt-2">This auction has been cancelled by the seller.</p>
                    <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'seller'): ?>
                        <form method="post" class="mt-4 inline-block">
                            <input type="hidden" name="relist" value="1">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded shadow">
                                Relist Auction
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <p class="text-sm text-red-600 mt-3"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($displayStatus === 'expired'): ?>
                <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-900 font-semibold">Auction Expired</span>
                    </div>
                    <p class="text-gray-700 mt-2">This auction has expired without meeting the reserve price.</p>
                </div>
            <?php elseif ($displayStatus === 'not-started'): ?>
                <div id="auction-countdown" data-ts="<?= $auctionStartTime ?>" data-end="<?= $auctionEndTime ?>" data-phase="start" class="bg-blue-50 border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="cd-label text-blue-900 font-semibold">Auction Starting In</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-days"><?= (int) floor(max(0, ($auctionStartTime - time()))/86400) ?></div>
                            <div class="text-blue-700">Days</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-hours"><?= (int) floor((max(0, ($auctionStartTime - time()))%86400)/3600) ?></div>
                            <div class="text-blue-700">Hours</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-mins"><?= (int) floor((max(0, ($auctionStartTime - time()))%3600)/60) ?></div>
                            <div class="text-blue-700">Mins</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-secs"><?= (int) (max(0, ($auctionStartTime - time()))%60) ?></div>
                            <div class="text-blue-700">Secs</div>
                        </div>
                    </div>
                </div>
            <?php elseif ($displayStatus === 'ongoing'): ?>
                <div id="auction-countdown" data-ts="<?= $auctionEndTime ?>" data-end="<?= $auctionEndTime ?>" data-phase="end" class="bg-amber-50 border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-amber-900 font-semibold">Auction Ending In</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-days"><?= (int) floor(max(0, ($auctionEndTime - time()))/86400) ?></div>
                            <div class="text-amber-700">Days</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-hours"><?= (int) floor((max(0, ($auctionEndTime - time()))%86400)/3600) ?></div>
                            <div class="text-amber-700">Hours</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-mins"><?= (int) floor((max(0, ($auctionEndTime - time()))%3600)/60) ?></div>
                            <div class="text-amber-700">Mins</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-secs"><?= (int) (max(0, ($auctionEndTime - time()))%60) ?></div>
                            <div class="text-amber-700">Secs</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Current Bid summary (all states) -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">

                <div class="flex items-baseline justify-between">
                    <span class="text-gray-600">Starting Price</span>

                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl text-gray-900">
                            $<?= number_format($startingPrice) ?>
                        </span>
                        <span class="text-gray-500">USD</span>
                    </div>
                </div>

                <?php if (count($bidHistory) > 0): ?>
                    <div class="flex items-baseline justify-between pt-4 border-t border-gray-200">
                        <span class="text-gray-600">Current Bid</span>

                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl">
                                $<?= number_format($currentBid) ?>
                            </span>
                            <span class="text-gray-500">USD</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($reservePrice) && $reservePrice > 0): ?>
                    <div class="flex items-baseline justify-between">
                        <span class="text-gray-600">Reserve Price</span>

                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl text-gray-900">$<?= number_format($reservePrice) ?></span>
                            <span class="text-gray-500">USD</span>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="text-gray-500">
                    <?php if (count($bidHistory) === 0): ?>
                        No bids yet
                    <?php else: ?>
                        <?= count($bidHistory) ?> bids
                    <?php endif; ?>
                </p>

            </div>

            <!-- ItemDetails -->
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center gap-2 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Item Details</h3>
                </div>
                <div class="space-y-6">
                    <div>
                        <h4 class="text-gray-700 mb-2">Condition</h4>
                        <div class="inline-block bg-green-50 text-green-700 px-4 py-2 rounded-lg border border-green-200">
                            <?= htmlspecialchars($item_condition ?: 'Unknown') ?>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-gray-700 mb-3">Description</h4>
                        <p class="text-gray-600 leading-relaxed">
                            <?= nl2br(htmlspecialchars($description)) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bid History -->
    <?php if (in_array($displayStatus, ['ongoing', 'finished', 'expired'], true)): ?>
        <div class="mt-12">
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold">Bid History</h2>
                    </div>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($bidHistory as $index => $bid): ?>
                        <div class="p-4 flex items-center justify-between <?= $index === 0 ? 'bg-blue-50' : '' ?>">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center
                                    <?= $index === 0 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?>">
                                    <?= strtoupper(substr($bid['bidder'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="<?= $index === 0 ? 'text-blue-900 font-semibold' : 'text-gray-900' ?>">
                                            <?= htmlspecialchars($bid['bidder']) ?>
                                        </span>
                                        <?php if ($index === 0): ?>
                                            <span class="bg-blue-600 text-white px-2 py-0.5 rounded text-xs">
                                                Leading
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-500 text-sm"><?= timeAgoSimple($bid['timestamp']) ?></p>
                                </div>
                            </div>
                            <div class="<?= $index === 0 ? 'text-blue-900 font-semibold' : 'text-gray-900' ?>">
                                $<?= number_format($bid['amount']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
<script>
// Live countdown updater — run after DOM ready and allow client-side switch from start -> end
document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('auction-countdown');
    if (!el) return;

    var phase = el.getAttribute('data-phase') || 'start';

    var startTs = parseInt(el.getAttribute('data-ts') || el.getAttribute('data-start') || '', 10);
    var endTs   = parseInt(el.getAttribute('data-end') || '', 10);
    if (isNaN(startTs)) startTs = 0; else startTs *= 1000;
    if (isNaN(endTs)) endTs = startTs; else endTs *= 1000;

    var targetTs = (phase === 'start') ? startTs : endTs;

    // Initialize label and classes according to the current phase so text is correct on load
    var label = el.querySelector('.cd-label') || el.querySelector('span.font-semibold');
    if (label) {
        if (phase === 'start') {
            label.textContent = 'Auction Starting In';
            el.classList.add('bg-blue-50');
            el.classList.remove('bg-amber-50');
        } else {
            label.textContent = 'Auction Ending In';
            el.classList.add('bg-amber-50');
            el.classList.remove('bg-blue-50');
        }
    }

    function switchToEnd() {
        phase = 'end';
        targetTs = endTs;
        el.setAttribute('data-phase', 'end');

        var label = el.querySelector('.cd-label') || el.querySelector('span.font-semibold');
        if (label) label.textContent = 'Auction Ending In';

        el.classList.remove('bg-blue-50');
        el.classList.add('bg-amber-50');
    }

    function zeroOut() {
        var nodes = ['.cd-days','.cd-hours','.cd-mins','.cd-secs'];
        nodes.forEach(function(sel){ var n = el.querySelector(sel); if (n) n.textContent = 0; });
    }

    function update(){
        var now = Date.now();

        // If we're counting down to start and start time passed, switch to end countdown
        if (phase === 'start' && startTs > 0 && now >= startTs) {
            if (endTs > now) {
                switchToEnd();
            } else {
                // auction already ended
                zeroOut();
                return;
            }
        }

        var diff = Math.max(0, Math.floor((targetTs - now)/1000));
        var days = Math.floor(diff/86400); diff %= 86400;
        var hours = Math.floor(diff/3600); diff %= 3600;
        var mins = Math.floor(diff/60); var secs = diff % 60;

        var d = el.querySelector('.cd-days'); if (d) d.textContent = days;
        var h = el.querySelector('.cd-hours'); if (h) h.textContent = hours;
        var m = el.querySelector('.cd-mins'); if (m) m.textContent = mins;
        var s = el.querySelector('.cd-secs'); if (s) s.textContent = secs;
    }

    update();
    setInterval(update, 1000);
});

</script>