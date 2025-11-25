<?php include_once('header.php'); ?>
<?php require 'utilities.php'; ?>
<?php
// Database-backed My Bids page (replaces mock data)
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
$allowedSorts = ['bid_time_desc', 'bid_time_asc', 'end_time_desc', 'end_time_asc'];
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'bid_time_desc';
if (!in_array($sort, $allowedSorts, true)) {
  $sort = 'bid_time_desc';
}

// Helpers
function getStatusClasses($status) {
  return match($status) {
    'winning' => 'bg-green-50 text-green-700 border-green-200',
    'outbid'  => 'bg-red-50 text-red-700 border-red-200',
    'won'     => 'bg-blue-50 text-blue-700 border-blue-200',
    'lost'    => 'bg-gray-50 text-gray-700 border-gray-200',
    default   => 'bg-gray-50 text-gray-700 border-gray-200'
  };
}
function getStatusText($s) {
  return match($s) {
    'winning' => 'Winning',
    'outbid'  => 'Outbid',
    'won'     => 'Won',
    'lost'    => 'Lost',
    default   => 'Unknown'
  };
}
function getStatusIcon($s) {
  // Use inline SVG for 'winning' (TrendingUp icon). Others keep simple emojis.
  if ($s === 'winning') {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">'
         . '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>'
         . '<polyline points="16 7 22 7 22 13"></polyline>'
         . '</svg>';
  }
  // Inline SVG for 'won' (CheckCircle icon)
  if ($s === 'won') {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">'
         . '<circle cx="12" cy="12" r="10"></circle>'
         . '<path d="M9 12l2 2 4-4"></path>'
         . '</svg>';
  }
  // Inline SVG for 'outbid' and 'lost' (XCircle icon)
  if ($s === 'outbid' || $s === 'lost') {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">'
         . '<circle cx="12" cy="12" r="10"></circle>'
         . '<path d="M15 9l-6 6"></path>'
         . '<path d="M9 9l6 6"></path>'
         . '</svg>';
  }
  return match($s) {
    default   => '⏰'
  };
}
function filterButtonClass($current, $filter) {
  return $current === $filter ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50';
}
function timeAgo($datetimeStr) {
  $ts = strtotime($datetimeStr);
  if (!$ts) return '';
  $diff = time() - $ts;
  if ($diff < 60) return 'Just now';
  $m = floor($diff/60); if ($m < 60) return $m . ' minutes ago';
  $h = floor($diff/3600); if ($h < 24) return $h . ' hours ago';
  $d = floor($diff/86400); return $d . ' days ago';
}

$stats = [ 'total' => 0, 'unreviewedSellers' => 0, 'won' => 0, 'outbid' => 0, 'totalAmountSpent' => 0 ];
$records = [];

if ($isLoggedIn && $accountType === 'buyer' && $buyerId > 0 && ($connection instanceof mysqli)) {
  // Stats: total auctions bid on (distinct auctionId)
  if ($stmt = $connection->prepare('SELECT COUNT(DISTINCT auctionId) FROM Bid WHERE buyerId = ?')) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    if ($stmt->fetch()) {
      $stats['total'] = (int)$cnt;
    }
    $stmt->close();
  }

  // Stats: total amount spent = sum of final winning bid amounts for auctions this buyer won (finished state)
  if ($stmt = $connection->prepare('SELECT COALESCE(SUM(bw.bidAmount),0) FROM Auction a JOIN Bid bw ON bw.auctionId=a.auctionId WHERE a.state="finished" AND bw.buyerId=? AND bw.bidAmount = (SELECT MAX(b2.bidAmount) FROM Bid b2 WHERE b2.auctionId=a.auctionId)')) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $stmt->bind_result($sumSpent);
    if ($stmt->fetch()) {
      $stats['totalAmountSpent'] = (int)$sumSpent;
    }
    $stmt->close();
  }

  // Stats: unreviewed sellers (won auctions where no review exists for that auctionId + sellerId)
  $unreviewedSql = "
    SELECT COUNT(*) FROM (
      SELECT a.auctionId
      FROM Auction a
      WHERE a.state = 'finished'
      AND EXISTS (
        SELECT 1 FROM Bid hb
        WHERE hb.auctionId = a.auctionId
          AND hb.buyerId = ?
          AND hb.bidAmount = (SELECT MAX(b2.bidAmount) FROM Bid b2 WHERE b2.auctionId = a.auctionId)
      )
      AND NOT EXISTS (
        SELECT 1 FROM Review r WHERE r.auctionId = a.auctionId AND r.sellerId = a.sellerId
      )
      GROUP BY a.auctionId
    ) x
  ";
  if ($stmt = $connection->prepare($unreviewedSql)) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $stmt->bind_result($unreviewedCnt);
    if ($stmt->fetch()) $stats['unreviewedSellers'] = (int)$unreviewedCnt;
    $stmt->close();
  }

  // Stats: outbid auctions (ongoing where buyer has bid but is not current top bidder)
  $outbidSql = "SELECT COUNT(*) FROM (\n    SELECT a.auctionId, (SELECT b3.buyerId FROM Bid b3 WHERE b3.auctionId=a.auctionId ORDER BY b3.bidAmount DESC, b3.bidTime DESC, b3.bidId DESC LIMIT 1) AS topBuyer\n    FROM Auction a\n    WHERE a.auctionId IN (SELECT DISTINCT auctionId FROM Bid WHERE buyerId = ?)\n      AND a.state='ongoing'\n  ) x WHERE x.topBuyer <> ?";
  if ($stmt = $connection->prepare($outbidSql)) {
    $stmt->bind_param('ii', $buyerId, $buyerId);
    $stmt->execute();
    $stmt->bind_result($outbidCnt);
    if ($stmt->fetch()) $stats['outbid'] = (int)$outbidCnt;
    $stmt->close();
  }

  // Stats: won auctions (finished where top bidder is this buyer)
  $wonSql = "
    SELECT COUNT(*) FROM (
      SELECT a.auctionId
      FROM Auction a
      WHERE a.state = 'finished'
      AND EXISTS (
        SELECT 1 FROM Bid hb
        WHERE hb.auctionId = a.auctionId
          AND hb.buyerId = ?
          AND hb.bidAmount = (SELECT MAX(b2.bidAmount) FROM Bid b2 WHERE b2.auctionId = a.auctionId)
      )
      GROUP BY a.auctionId
    ) x
  ";
  if ($stmt = $connection->prepare($wonSql)) {
    $stmt->bind_param('i', $buyerId);
    $stmt->execute();
    $stmt->bind_result($wonCnt);
    if ($stmt->fetch()) $stats['won'] = (int)$wonCnt;
    $stmt->close();
  }

  // Records: per auction the buyer has bid on
  $orderByClause = match($sort) {
    'bid_time_asc' => 'ORDER BY userBidTime ASC',
    'bid_time_desc' => 'ORDER BY userBidTime DESC',
    'end_time_asc' => 'ORDER BY a.endDate ASC',
    'end_time_desc' => 'ORDER BY a.endDate DESC',
    default => 'ORDER BY a.endDate DESC'
  };

  $sql = "
    SELECT 
      a.auctionId,
      a.state,
      a.endDate,
      i.name AS itemName,
      i.photo AS itemImage,
      (SELECT b1.bidAmount FROM Bid b1 WHERE b1.auctionId=a.auctionId AND b1.buyerId=? ORDER BY b1.bidTime DESC, b1.bidId DESC LIMIT 1) AS userBidAmount,
      (SELECT b1.bidTime FROM Bid b1 WHERE b1.auctionId=a.auctionId AND b1.buyerId=? ORDER BY b1.bidTime DESC, b1.bidId DESC LIMIT 1) AS userBidTime,
      (SELECT MAX(b2.bidAmount) FROM Bid b2 WHERE b2.auctionId=a.auctionId) AS currentPrice,
      (SELECT b3.buyerId FROM Bid b3 WHERE b3.auctionId=a.auctionId ORDER BY b3.bidAmount DESC, b3.bidTime DESC, b3.bidId DESC LIMIT 1) AS topBuyerId,
      (SELECT COUNT(*) FROM Review r WHERE r.auctionId = a.auctionId AND r.sellerId = a.sellerId) AS hasReview
    FROM Auction a
    JOIN Item i ON i.itemId = a.itemId
    WHERE a.auctionId IN (SELECT DISTINCT auctionId FROM Bid WHERE buyerId = ?)
    $orderByClause
  ";

  if ($stmt = $connection->prepare($sql)) {
    $stmt->bind_param('iii', $buyerId, $buyerId, $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $state = $row['state'];
      $topBuyerId = isset($row['topBuyerId']) ? (int)$row['topBuyerId'] : 0;
      // Determine card status by auction state and top bidder
      if ($state === 'ongoing') {
        $status = ($topBuyerId === $buyerId) ? 'winning' : 'outbid';
      } elseif ($state === 'finished') {
        $status = ($topBuyerId === $buyerId) ? 'won' : 'lost';
      } else {
        $status = 'lost';
      }

      // Apply active/ended filter using Auction.state
      $isActive = ($state === 'ongoing');
      $isEnded = ($state === 'finished' || $state === 'expired');
      if ($filter === 'active' && !$isActive) continue;
      if ($filter === 'ended' && !$isEnded) continue;

      $records[] = [
        'id' => (string)$row['auctionId'],
        'itemName' => $row['itemName'] ?? 'Item',
        'itemImage' => ($row['itemImage'] && trim($row['itemImage']) !== '') ? $row['itemImage'] : 'https://images.unsplash.com/photo-1611930022073-b7a4ba5fcccd?w=800&q=80',
        'bidAmount' => (int)($row['userBidAmount'] ?? 0),
        'currentPrice' => (int)($row['currentPrice'] ?? 0),
        'bidTime' => $row['userBidTime'] ? timeAgo($row['userBidTime']) : '',
        'status' => $status,
        'auctionEndTime' => date('M j, Y g:i A', strtotime($row['endDate'])),
        'hasReview' => (int)($row['hasReview'] ?? 0) > 0,
      ];
    }
    $stmt->close();
  }
}
?>
<!-- Tailwind (added locally since header.php controls <head>) -->
<script src="https://cdn.tailwindcss.com"></script>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <?php if (!($isLoggedIn && $accountType === 'buyer' && $buyerId > 0)): ?>
    <div class="bg-white p-6 rounded-lg border border-gray-200 mb-8">
      <p class="text-gray-700">Please log in as a buyer to view your bids.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-8">
      <div class="bg-white p-4 rounded-lg border border-gray-200 flex flex-col"><p class="text-gray-600 mb-1 text-xs uppercase tracking-wide">Total Auctions Bid On</p><p class="text-blue-600 text-lg font-semibold truncate"><?= (int)$stats['total'] ?></p></div>
      <div class="bg-white p-4 rounded-lg border border-gray-200 flex flex-col"><p class="text-gray-600 mb-1 text-xs uppercase tracking-wide">Won</p><p class="text-purple-600 text-lg font-semibold truncate"><?= (int)$stats['won'] ?></p></div>
      <div class="bg-white p-4 rounded-lg border border-gray-200 flex flex-col"><p class="text-gray-600 mb-1 text-xs uppercase tracking-wide">Outbid</p><p class="text-red-600 text-lg font-semibold truncate"><?= (int)$stats['outbid'] ?></p></div>
      <div class="bg-white p-4 rounded-lg border border-gray-200 flex flex-col"><p class="text-gray-600 mb-1 text-xs uppercase tracking-wide">Unreviewed Sellers</p><p class="text-orange-600 text-lg font-semibold truncate"><?= (int)$stats['unreviewedSellers'] ?></p></div>
      <div class="bg-white p-4 rounded-lg border border-gray-200 flex flex-col"><p class="text-gray-600 mb-1 text-xs uppercase tracking-wide">Total Amount Spent</p><p class="text-gray-900 text-lg font-semibold truncate">$<?= number_format((int)$stats['totalAmountSpent']) ?></p></div>
    </div>
  <?php endif; ?>

  <div class="flex items-center gap-4 mb-6">
    <div class="flex items-center gap-2 text-gray-700"><span class="w-5 h-5 text-gray-600 flex items-center justify-center"><i class="fa fa-filter"></i></span><span>Filter:</span></div>
    <div class="flex gap-2">
      <a href="?filter=all&sort=<?= urlencode($sort) ?>" class="px-4 py-2 rounded-lg transition-colors <?= filterButtonClass('all',$filter) ?>">All Bids</a>
      <a href="?filter=active&sort=<?= urlencode($sort) ?>" class="px-4 py-2 rounded-lg transition-colors <?= filterButtonClass('active',$filter) ?>">Active</a>
      <a href="?filter=ended&sort=<?= urlencode($sort) ?>" class="px-4 py-2 rounded-lg transition-colors <?= filterButtonClass('ended',$filter) ?>">Ended</a>
    </div>
    <div class="ml-auto flex items-center gap-2 text-gray-700">
      <span class="w-5 h-5 text-gray-600 flex items-center justify-center"><i class="fa fa-sort"></i></span>
      <span>Sort:</span>
      <select onchange="window.location.href='?filter=<?= urlencode($filter) ?>&sort='+this.value" class="px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="bid_time_desc" <?= $sort === 'bid_time_desc' ? 'selected' : '' ?>>Bid Time (Newest)</option>
        <option value="bid_time_asc" <?= $sort === 'bid_time_asc' ? 'selected' : '' ?>>Bid Time (Oldest)</option>
        <option value="end_time_desc" <?= $sort === 'end_time_desc' ? 'selected' : '' ?>>End Time (Latest)</option>
        <option value="end_time_asc" <?= $sort === 'end_time_asc' ? 'selected' : '' ?>>End Time (Earliest)</option>
      </select>
    </div>
  </div>

  <div class="space-y-4">
    <?php if ($isLoggedIn && $accountType === 'buyer' && count($records) > 0): foreach ($records as $bid): $classes = getStatusClasses($bid['status']); ?>
      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
        <div class="flex flex-col sm:flex-row">
          <div class="w-full sm:w-48 h-48 flex-shrink-0">
            <img src="<?= htmlspecialchars($bid['itemImage']) ?>" alt="<?= htmlspecialchars($bid['itemName']) ?>" class="w-full h-full object-cover">
          </div>
          <div class="flex-1 p-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="mb-2 font-semibold text-gray-900"><?= htmlspecialchars($bid['itemName']) ?></h3>
                <div class="flex items-center gap-2 text-gray-600 text-sm"><span class="w-4 h-4">⏰</span><span>Bid placed: <?= htmlspecialchars($bid['bidTime']) ?></span></div>
              </div>
              <div class="px-3 py-1 rounded-full border flex items-center gap-2 <?= $classes ?>">
                <span class="text-sm"><?= getStatusIcon($bid['status']) ?></span>
                <span class="text-sm"><?= getStatusText($bid['status']) ?></span>
              </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
              <div><p class="text-sm text-gray-600 mb-1">Your Bid</p><p class="text-green-600 font-semibold">$<?= number_format($bid['bidAmount']) ?></p></div>
              <div><?php $cpClass = ($bid['currentPrice'] > $bid['bidAmount']) ? 'text-red-600' : 'text-gray-900'; ?><p class="text-sm text-gray-600 mb-1">Current Price</p><p class="<?= $cpClass ?> font-semibold">$<?= number_format($bid['currentPrice']) ?></p></div>
              <div class="col-span-2 sm:col-span-1"><p class="text-sm text-gray-600 mb-1">Auction Ends</p><p class="text-gray-900"><?= htmlspecialchars($bid['auctionEndTime']) ?></p></div>
            </div>
            <?php if ($bid['status'] === 'outbid'): ?>
              <a href="listing.php?auctionId=<?= urlencode($bid['id']) ?>&from=mybids" class="inline-block w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Place New Bid</a>
            <?php elseif ($bid['status'] === 'won'): ?>
              <?php if ($bid['hasReview']): ?>
                <button disabled class="inline-block w-full sm:w-auto px-6 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed opacity-60">Completed</button>
              <?php else: ?>
                <a href="rate_seller.php?auctionId=<?= urlencode($bid['id']) ?>" class="inline-block w-full sm:w-auto px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">Review Seller</a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; elseif ($isLoggedIn && $accountType === 'buyer'): ?>
      <div class="text-center py-12 bg-white rounded-lg border border-gray-200"><p class="text-gray-600">No bids found matching your filter.</p></div>
    <?php endif; ?>
  </div>
</main>

<?php include_once 'footer.php'; ?>