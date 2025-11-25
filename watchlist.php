<?php include_once('header.php'); ?>
<?php require 'utilities.php'; ?>
<?php
// Watchlist page - displays auctions the buyer is watching
date_default_timezone_set('Europe/London');

if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure DB connection
$connection = $GLOBALS['connection'] ?? ($connection ?? null);
if (!($connection instanceof mysqli)) {
  require_once __DIR__ . '/database.php';
  $connection = $GLOBALS['connection'] ?? ($connection ?? null);
}

$isLoggedIn = $_SESSION['logged_in'] ?? false;
$accountType = $_SESSION['account_type'] ?? '';
$buyerId = (int)($_SESSION['user_id'] ?? 0);

// Filters
$allowedFilters = ['all', 'active', 'ended'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if (!in_array($filter, $allowedFilters, true)) {
  $filter = 'all';
}

// Sorting
$allowedSorts = ['recent', 'ending_soon', 'price_low', 'price_high'];
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
if (!in_array($sort, $allowedSorts, true)) {
  $sort = 'recent';
}

// Helpers
function getAuctionStateLabel($state) {
  return match($state) {
    'ongoing' => ['text' => 'Active', 'class' => 'bg-green-50 text-green-700 border-green-200'],
    'not-started' => ['text' => 'Upcoming', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
    'finished' => ['text' => 'Ended', 'class' => 'bg-gray-50 text-gray-700 border-gray-200'],
    'expired' => ['text' => 'Expired', 'class' => 'bg-gray-50 text-gray-700 border-gray-200'],
    'cancelled' => ['text' => 'Cancelled', 'class' => 'bg-red-50 text-red-700 border-red-200'],
    default => ['text' => 'Unknown', 'class' => 'bg-gray-50 text-gray-700 border-gray-200']
  };
}

function filterButtonClass($current, $filter) {
  return $current === $filter ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50';
}

function timeRemaining($endDate) {
  $now = time();
  $end = strtotime($endDate);
  if (!$end || $end <= $now) return 'Ended';
  
  $diff = $end - $now;
  $days = floor($diff / 86400);
  $hours = floor(($diff % 86400) / 3600);
  $mins = floor(($diff % 3600) / 60);
  
  if ($days > 0) return $days . 'd ' . $hours . 'h';
  if ($hours > 0) return $hours . 'h ' . $mins . 'm';
  return $mins . 'm';
}

$stats = ['total' => 0, 'active' => 0, 'ended' => 0];
$watchlist = [];

if ($isLoggedIn && $accountType === 'buyer' && $buyerId > 0 && ($connection instanceof mysqli)) {
  // Stats: total watched auctions
  if ($stmt = $connection->prepare('SELECT COUNT(*) FROM AuctionWatch WHERE buyerId = ?')) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stats['total'] = $cnt;
    $stmt->close();
  }

  // Stats: active watched auctions
  if ($stmt = $connection->prepare("SELECT COUNT(*) FROM AuctionWatch aw JOIN Auction a ON aw.auctionId = a.auctionId WHERE aw.buyerId = ? AND a.state = 'ongoing'")) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stats['active'] = $cnt;
    $stmt->close();
  }

  // Stats: ended watched auctions
  if ($stmt = $connection->prepare("SELECT COUNT(*) FROM AuctionWatch aw JOIN Auction a ON aw.auctionId = a.auctionId WHERE aw.buyerId = ? AND a.state IN ('finished', 'expired')")) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stats['ended'] = $cnt;
    $stmt->close();
  }

  // Build main query
  $whereClause = match($filter) {
    'active' => "AND a.state = 'ongoing'",
    'ended' => "AND a.state IN ('finished', 'expired')",
    default => ""
  };

  $orderClause = match($sort) {
    'ending_soon' => 'ORDER BY a.endDate ASC',
    'price_low' => 'ORDER BY COALESCE(maxBid, a.startingPrice) ASC',
    'price_high' => 'ORDER BY COALESCE(maxBid, a.startingPrice) DESC',
    default => 'ORDER BY aw.watchTime DESC'
  };

  $sql = "
    SELECT 
      a.auctionId,
      i.name AS itemName,
      i.photo,
      i.description,
      a.startingPrice,
      a.reservePrice,
      a.endDate,
      a.state,
      COALESCE(maxBid, a.startingPrice) AS currentPrice,
      COALESCE(bidCount, 0) AS bidCount,
      aw.watchTime
    FROM AuctionWatch aw
    JOIN Auction a ON aw.auctionId = a.auctionId
    JOIN Item i ON a.itemId = i.itemId
    LEFT JOIN (
      SELECT auctionId, MAX(bidAmount) AS maxBid, COUNT(*) AS bidCount
      FROM Bid
      GROUP BY auctionId
    ) b ON a.auctionId = b.auctionId
    WHERE aw.buyerId = ?
    $whereClause
    $orderClause
  ";

  if ($stmt = $connection->prepare($sql)) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $watchlist[] = [
        'auctionId' => $row['auctionId'],
        'itemName' => $row['itemName'],
        'photo' => $row['photo'],
        'description' => $row['description'],
        'currentPrice' => (float)$row['currentPrice'],
        'bidCount' => (int)$row['bidCount'],
        'endDate' => $row['endDate'],
        'state' => $row['state'],
        'watchTime' => $row['watchTime']
      ];
    }
    $stmt->close();
  }
}

// If not buyer or not logged in, redirect
if (!$isLoggedIn || $accountType !== 'buyer') {
  echo '<div class="container my-5"><div class="alert alert-warning">Please log in as a buyer to view your watchlist.</div></div>';
  include_once('footer.php');
  exit;
}
?>

<!-- Load Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>

<div class="container mx-auto px-4 py-6 max-w-7xl">
  
  <!-- Header -->
  <div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">My Watchlist</h1>
    <p class="text-gray-600">Track auctions you're interested in</p>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">Total Watched</p>
          <p class="text-2xl font-bold text-gray-900"><?= $stats['total'] ?></p>
        </div>
        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
          <span class="text-2xl">👁️</span>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">Active</p>
          <p class="text-2xl font-bold text-green-600"><?= $stats['active'] ?></p>
        </div>
        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
          <span class="text-2xl">🔴</span>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-gray-600 text-sm mb-1">Ended</p>
          <p class="text-2xl font-bold text-gray-600"><?= $stats['ended'] ?></p>
        </div>
        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
          <span class="text-2xl">⏸️</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters and Sort -->
  <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      
      <!-- Filter Buttons -->
      <div class="flex gap-2">
        <a href="?filter=all&sort=<?= urlencode($sort) ?>" 
           class="px-4 py-2 rounded-lg transition-colors <?= filterButtonClass($filter, 'all') ?>">
          All
        </a>
        <a href="?filter=active&sort=<?= urlencode($sort) ?>" 
           class="px-4 py-2 rounded-lg transition-colors <?= filterButtonClass($filter, 'active') ?>">
          Active
        </a>
        <a href="?filter=ended&sort=<?= urlencode($sort) ?>" 
           class="px-4 py-2 rounded-lg transition-colors <?= filterButtonClass($filter, 'ended') ?>">
          Ended
        </a>
      </div>

      <!-- Sort Dropdown -->
      <div class="flex items-center gap-2">
        <label class="text-gray-700 text-sm">Sort by:</label>
        <select 
          onchange="window.location.href='?filter=<?= urlencode($filter) ?>&sort='+this.value"
          class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-700 focus:ring-2 focus:ring-blue-500 outline-none"
        >
          <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recently Added</option>
          <option value="ending_soon" <?= $sort === 'ending_soon' ? 'selected' : '' ?>>Ending Soon</option>
          <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
          <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Watchlist Items -->
  <?php if (empty($watchlist)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
      <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <span class="text-4xl">👁️</span>
      </div>
      <h3 class="text-xl font-semibold text-gray-900 mb-2">No items in your watchlist</h3>
      <p class="text-gray-600 mb-4">Start watching auctions to track items you're interested in</p>
      <a href="browse.php" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
        Browse Auctions
      </a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 gap-4">
      <?php foreach ($watchlist as $item): ?>
        <?php 
          $stateLabel = getAuctionStateLabel($item['state']);
          $timeLeft = timeRemaining($item['endDate']);
        ?>
        <div class="bg-white rounded-xl border border-gray-200 hover:shadow-md transition-shadow overflow-hidden">
          <div class="flex flex-col md:flex-row">
            
            <!-- Image -->
            <div class="md:w-48 h-48 bg-gray-100 flex-shrink-0">
              <?php if (!empty($item['photo'])): ?>
                <img src="<?= htmlspecialchars($item['photo']) ?>" 
                     alt="<?= htmlspecialchars($item['itemName']) ?>"
                     class="w-full h-full object-cover">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-gray-400">
                  <span class="text-5xl">📦</span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="flex-1 p-5">
              <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                
                <!-- Left: Item Info -->
                <div class="flex-1">
                  <div class="flex items-start gap-3 mb-3">
                    <h3 class="text-xl font-semibold text-gray-900 flex-1">
                      <a href="listing.php?auction_id=<?= $item['auctionId'] ?>" 
                         class="hover:text-blue-600 transition-colors">
                        <?= htmlspecialchars($item['itemName']) ?>
                      </a>
                    </h3>
                    <span class="px-3 py-1 rounded-full text-sm border <?= $stateLabel['class'] ?>">
                      <?= $stateLabel['text'] ?>
                    </span>
                  </div>
                  
                  <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                    <?= htmlspecialchars(substr($item['description'], 0, 150)) ?><?= strlen($item['description']) > 150 ? '...' : '' ?>
                  </p>

                  <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                    <span>💰 £<?= number_format($item['currentPrice'], 2) ?></span>
                    <span>📊 <?= $item['bidCount'] ?> bid<?= $item['bidCount'] !== 1 ? 's' : '' ?></span>
                    <?php if ($item['state'] === 'ongoing'): ?>
                      <span>⏰ <?= $timeLeft ?> left</span>
                    <?php else: ?>
                      <span>📅 Ended <?= date('M j, Y', strtotime($item['endDate'])) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Right: Actions -->
                <div class="flex md:flex-col gap-2">
                  <a href="listing.php?auction_id=<?= $item['auctionId'] ?>&from=watchlist" 
                     class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-center whitespace-nowrap">
                    View Details
                  </a>
                  <button 
                    onclick="removeFromWatchlist(<?= $item['auctionId'] ?>)"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    Remove
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
function removeFromWatchlist(auctionId) {
  if (!confirm('Remove this item from your watchlist?')) return;
  
  fetch('toggle_watchlist.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'auctionId=' + auctionId
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      window.location.reload();
    } else {
      alert(data.message || 'Failed to remove from watchlist');
    }
  })
  .catch(err => {
    console.error('Error:', err);
    alert('An error occurred');
  });
}
</script>

<?php include_once('footer.php'); ?>
