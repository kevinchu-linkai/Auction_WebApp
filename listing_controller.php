<?php
/**
 * Listing Controller - Business logic for auction listing page
 * Handles data loading, POST actions (relist, bid), and prepares view variables
 */

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
    'SELECT a.auctionId,a.sellerId,a.startingPrice,a.reservePrice,a.startDate,a.endDate,a.state, '
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
$startingPrice = isset($row['startingPrice']) ? (float)$row['startingPrice'] : 0.0;
$currentBid = $maxBid !== null ? $maxBid : $startingPrice;

// handle POST actions (relist or place bid)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cancel action
    if (isset($_POST['cancel_auction'])) {
        // Must be logged in as seller and owner of this auction
        $isSeller = isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'seller';
        $userId = $_SESSION['user_id'] ?? 0;
        
        if ($isSeller && $userId == $sellerId && $dbState !== 'cancelled' && $dbState !== 'expired' && $dbState !== 'finished') {
            $up = $connection->prepare('UPDATE Auction SET state = ? WHERE auctionId = ? AND sellerId = ?');
            $newState = 'cancelled';
            $up->bind_param('sii', $newState, $auctionId, $sellerId);
            if ($up->execute()) {
                $up->close();
                $base = strtok($_SERVER['REQUEST_URI'], '?');
                header('Location: ' . $base . '?auctionId=' . $auctionId);
                exit;
            }
            $error = 'Failed to cancel auction';
            $up->close();
        } else {
            $error = 'Unauthorized cancel attempt or auction cannot be cancelled';
        }
    }
    // Relist action
    elseif (isset($_POST['relist'])) {
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
$reservePrice = array_key_exists('reservePrice', $row) ? (float)$row['reservePrice'] : null;
$photo = $row['photo'] ?? '';
$photoUrl = $photo ? $photo : 'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?w=800&q=80';

// Helper: human-readable time ago
function timeAgoSimple($ts) {
    $now = time(); $diff = $now - $ts;
    if ($diff < 60) return 'Just now';
    $m = floor($diff/60); if ($m<60) return $m.'m ago';
    $h = floor($diff/3600); if ($h<24) return $h.'h ago';
    return floor($diff/86400).'d ago';
}
?>