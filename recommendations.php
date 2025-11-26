<?php 
include_once("header.php");
require("utilities.php");

if (session_status() === PHP_SESSION_NONE) session_start();

// Check if user is logged in and is a buyer
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-warning">Please <a href="login.php">sign in</a> to view recommendations.</div>';
    include_once("footer.php");
    exit();
}

if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'buyer') {
    echo '<div class="alert alert-info">Recommendations are only available for buyers.</div>';
    include_once("footer.php");
    exit();
}

require_once 'database.php';

$buyerId = $_SESSION['user_id'];

// Get filter parameters (same as browse)
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$category = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'recommended';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

//-----------------------------------------------------------
// RECOMMENDATION ALGORITHM
//-----------------------------------------------------------

// Function to get bid history score
function getBidHistoryScore($connection, $auctionId, $buyerId) {
    $query = "
        SELECT COUNT(DISTINCT b.auctionId) as score
        FROM Bid b
        JOIN Auction a ON b.auctionId = a.auctionId
        JOIN Item i ON a.itemId = i.itemId
        WHERE b.buyerId = ?
        AND i.categoryId = (
            SELECT categoryId FROM Item WHERE itemId = (
                SELECT itemId FROM Auction WHERE auctionId = ?
            )
        )
    ";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $buyerId, $auctionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['score'] ?? 0;
}

// Function to get watchlist score
function getWatchlistScore($connection, $auctionId, $buyerId) {
    $query = "
        SELECT COUNT(DISTINCT aw2.buyerId) as score
        FROM AuctionWatch aw1
        JOIN AuctionWatch aw2 ON aw1.auctionId = aw2.auctionId
        WHERE aw1.buyerId = ?
        AND aw2.auctionId = ?
    ";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $buyerId, $auctionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['score'] ?? 0;
}

// Function to get collaborative filtering score
function getCollaborativeScore($connection, $auctionId, $buyerId) {
    $query = "
        SELECT COUNT(DISTINCT b2.buyerId) as score
        FROM Bid b1
        JOIN Bid b2 ON b1.auctionId = b2.auctionId
        WHERE b1.buyerId = ?
        AND b2.buyerId != ?
        AND b2.auctionId = ?
    ";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'iii', $buyerId, $buyerId, $auctionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['score'] ?? 0;
}

// Function to get trending score
function getTrendingScore($connection, $auctionId) {
    $query = "
        SELECT COUNT(*) as score
        FROM Bid
        WHERE auctionId = ?
        AND bidTime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'i', $auctionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row['score'] ?? 0;
}

// Function to get ending soon score
function getEndingSoonScore($connection, $auctionId) {
    $query = "
        SELECT TIMESTAMPDIFF(HOUR, NOW(), endDate) as hoursRemaining
        FROM Auction
        WHERE auctionId = ?
        AND state = 'ongoing'
        AND endDate > NOW()
    ";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'i', $auctionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $hours = $row['hoursRemaining'] ?? 999;
    if ($hours <= 6) return 10;
    if ($hours <= 12) return 7;
    if ($hours <= 24) return 5;
    return 0;
}

// Main recommendation query
$recommendationQuery = "
    SELECT 
        a.auctionId,
        i.name AS itemName,
        i.description,
        i.photo,
        a.startDate,
        a.endDate,
        a.startingPrice,
        a.state,
        c.name AS categoryName,
        COALESCE((SELECT MAX(bidAmount) FROM Bid WHERE auctionId = a.auctionId), a.startingPrice) as currentBid,
        (SELECT COUNT(*) FROM Bid WHERE auctionId = a.auctionId) as bidCount,
        (SELECT COUNT(*) FROM AuctionWatch WHERE auctionId = a.auctionId) as watchlistCount,
        TIMESTAMPDIFF(HOUR, NOW(), a.endDate) as hoursRemaining
    FROM Auction a
    JOIN Item i ON a.itemId = i.itemId
    JOIN Category c ON i.categoryId = c.categoryId
    WHERE a.state = 'ongoing'
    AND a.auctionId NOT IN (
        SELECT auctionId FROM Bid WHERE buyerId = ?
    )
    AND a.endDate > NOW()
";

$params = [$buyerId];
$types = 'i';

// Apply filters
if (!empty($keyword)) {
    $recommendationQuery .= " AND (i.name LIKE ? OR i.description LIKE ?)";
    $keywordParam = '%' . $keyword . '%';
    $params[] = $keywordParam;
    $params[] = $keywordParam;
    $types .= 'ss';
}

if ($category !== 'all') {
    $recommendationQuery .= " AND c.name = ?";
    $params[] = $category;
    $types .= 's';
}

if ($status_filter === 'ending-soon') {
    $recommendationQuery .= " AND a.endDate <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";
}

$recommendationQuery .= " LIMIT 50";

$stmt = mysqli_prepare($connection, $recommendationQuery);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Calculate scores for each auction
$auctions = [];
$weights = [
    'bid_history' => 0.35,
    'watchlist' => 0.25,
    'collaborative' => 0.20,
    'trending' => 0.10,
    'ending_soon' => 0.10,
];

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate hybrid score
    $bidHistoryScore = getBidHistoryScore($connection, $row['auctionId'], $buyerId);
    $watchlistScore = getWatchlistScore($connection, $row['auctionId'], $buyerId);
    $collaborativeScore = getCollaborativeScore($connection, $row['auctionId'], $buyerId);
    $trendingScore = getTrendingScore($connection, $row['auctionId']);
    $endingSoonScore = getEndingSoonScore($connection, $row['auctionId']);
    
    // Normalize scores (0-10 scale)
    $bidHistoryScore = min($bidHistoryScore * 2, 10);
    $watchlistScore = min($watchlistScore * 2, 10);
    $collaborativeScore = min($collaborativeScore, 10);
    $trendingScore = min($trendingScore * 0.5, 10);
    
    $totalScore = 
        ($bidHistoryScore * $weights['bid_history']) +
        ($watchlistScore * $weights['watchlist']) +
        ($collaborativeScore * $weights['collaborative']) +
        ($trendingScore * $weights['trending']) +
        ($endingSoonScore * $weights['ending_soon']);
    
    // Determine recommendation reason
    $reason = '';
    $badge = '';
    if ($bidHistoryScore > 7) {
        $reason = 'Based on your bidding history';
        $badge = '📊 For You';
    } elseif ($watchlistScore > 7) {
        $reason = 'Similar to items you\'re watching';
        $badge = '👀 Recommended';
    } elseif ($collaborativeScore > 5) {
        $reason = 'Users like you are bidding';
        $badge = '👥 Popular';
    } elseif ($trendingScore > 5) {
        $reason = 'Trending now';
        $badge = '🔥 Hot';
    } elseif ($endingSoonScore > 5) {
        $reason = 'Ending soon';
        $badge = '⏰ Last Chance';
    } else {
        $reason = 'Popular in ' . $row['categoryName'];
        $badge = '✨ New';
    }
    
    $row['recommendationScore'] = $totalScore;
    $row['recommendationReason'] = $reason;
    $row['recommendationBadge'] = $badge;
    
    $auctions[] = $row;
}

mysqli_stmt_close($stmt);

// Sort by recommendation score
if ($order_by === 'recommended') {
    usort($auctions, function($a, $b) {
        return $b['recommendationScore'] <=> $a['recommendationScore'];
    });
} elseif ($order_by === 'pricelow') {
    usort($auctions, function($a, $b) {
        return $a['currentBid'] <=> $b['currentBid'];
    });
} elseif ($order_by === 'pricehigh') {
    usort($auctions, function($a, $b) {
        return $b['currentBid'] <=> $a['currentBid'];
    });
} elseif ($order_by === 'ending-soon') {
    usort($auctions, function($a, $b) {
        return $a['hoursRemaining'] <=> $b['hoursRemaining'];
    });
}

// Fetch all categories for filter
$categoriesQuery = "SELECT * FROM Category ORDER BY name ASC";
$categoriesResult = mysqli_query($connection, $categoriesQuery);
$allCategories = [];
if ($categoriesResult) {
    while ($catRow = mysqli_fetch_assoc($categoriesResult)) {
        $allCategories[] = $catRow['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recommendations - Your Perfect Matches</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-50">

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-20">
  <div class="max-w-7xl mx-auto px-6 lg:px-8 py-6">
    
    <!-- Title -->
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900 mb-1">✨ Recommended for You</h1>
      <p class="text-gray-600">Personalized auctions based on your interests and activity</p>
    </div>

    <!-- Search Bar -->
    <form method="get" action="recommendations.php" id="searchForm" class="bg-white rounded-2xl p-6 mb-6 shadow-lg border border-gray-100">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        
        <!-- Search Input -->
        <div class="md:col-span-5 relative">
          <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
          <input
            type="text"
            name="keyword"
            placeholder="Search recommendations"
            value="<?php echo htmlspecialchars($keyword); ?>"
            class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 placeholder-gray-500"
          />
        </div>

        <!-- Status Filter -->
        <div class="md:col-span-2 relative">
          <select
            name="status"
            onchange="this.form.submit()"
            class="appearance-none w-full pl-4 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer text-gray-700"
          >
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Active</option>
            <option value="ending-soon" <?php echo $status_filter === 'ending-soon' ? 'selected' : ''; ?>>Ending Soon</option>
          </select>
          <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </div>

        <!-- Sort Filter -->
        <div class="md:col-span-3 relative">
          <select
            name="order_by"
            onchange="this.form.submit()"
            class="appearance-none w-full pl-4 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer text-gray-700"
          >
            <option value="recommended" <?php echo $order_by === 'recommended' ? 'selected' : ''; ?>>✨ Best Match</option>
            <option value="ending-soon" <?php echo $order_by === 'ending-soon' ? 'selected' : ''; ?>>⏰ Ending Soon</option>
            <option value="pricelow" <?php echo $order_by === 'pricelow' ? 'selected' : ''; ?>>Price: Low to High</option>
            <option value="pricehigh" <?php echo $order_by === 'pricehigh' ? 'selected' : ''; ?>>Price: High to Low</option>
          </select>
          <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </div>

        <input type="hidden" name="cat" value="<?php echo htmlspecialchars($category); ?>">

        <!-- Search Button -->
        <div class="md:col-span-2">
          <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl hover:shadow-lg hover:shadow-purple-500/30 transition-all whitespace-nowrap font-medium flex items-center justify-center gap-2">
            Search
          </button>
        </div>
      </div>
    </form>

    <!-- Category Pills -->
    <div class="mb-6">
      <div class="flex items-center gap-2 mb-3">
        <span class="text-sm font-medium text-gray-600">Filter by Category:</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="?cat=all&keyword=<?php echo urlencode($keyword); ?>&status=<?php echo urlencode($status_filter); ?>&order_by=<?php echo urlencode($order_by); ?>" 
           class="px-4 py-2 text-sm font-medium rounded-full transition-all whitespace-nowrap <?php echo $category === 'all' ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:border-purple-400'; ?>">
          All Categories
        </a>
        <?php foreach ($allCategories as $cat): ?>
        <a href="?cat=<?php echo urlencode($cat); ?>&keyword=<?php echo urlencode($keyword); ?>&status=<?php echo urlencode($status_filter); ?>&order_by=<?php echo urlencode($order_by); ?>" 
           class="px-4 py-2 text-sm font-medium rounded-full transition-all whitespace-nowrap <?php echo $category === $cat ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:border-purple-400'; ?>">
          <?php echo htmlspecialchars($cat); ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex items-center justify-between">
      <p class="text-gray-600">
        <span class="text-gray-900 font-semibold"><?php echo count($auctions); ?></span> personalized recommendations
      </p>
      <a href="browse.php" class="text-sm text-purple-600 hover:text-purple-700 font-medium">
        Browse all auctions →
      </a>
    </div>

  </div>
</header>

<!-- Main Content -->
<main class="max-w-7xl mx-auto px-6 lg:px-8 py-8">

<?php if (count($auctions) > 0): ?>
  
  <div class="space-y-12">
    
  <!-- Featured Recommendation - Hero (More Compact) -->
  <?php 
  $featuredAuction = $auctions[0];
  $isWatchingFeatured = false;
  $checkWatchQuery = "SELECT * FROM AuctionWatch WHERE buyerId = ? AND auctionId = ?";
  $checkWatchStmt = mysqli_prepare($connection, $checkWatchQuery);
  mysqli_stmt_bind_param($checkWatchStmt, 'ii', $buyerId, $featuredAuction['auctionId']);
  mysqli_stmt_execute($checkWatchStmt);
  $checkWatchResult = mysqli_stmt_get_result($checkWatchStmt);
  $isWatchingFeatured = mysqli_num_rows($checkWatchResult) > 0;
  mysqli_stmt_close($checkWatchStmt);

  $imageUrl = !empty($featuredAuction['photo']) ? htmlspecialchars($featuredAuction['photo']) : 'https://via.placeholder.com/800x450?text=No+Image';
  ?>

  <section>
    <div class="flex items-center gap-3 mb-4">
      <h2 class="text-xl font-bold text-gray-900">🎯 Perfect Match</h2>
      <span class="px-2.5 py-0.5 bg-gradient-to-r from-yellow-400 to-orange-500 text-white rounded-full text-xs font-medium">TOP PICK</span>
    </div>
    
    <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl overflow-hidden border-2 border-purple-200 hover:border-purple-400 transition-all cursor-pointer group shadow-lg">
      <div class="flex flex-col lg:flex-row gap-6 p-6">
        
        <!-- Compact Image -->
        <div class="relative lg:w-1/2">
          <div class="absolute -inset-2 bg-gradient-to-r from-purple-400 to-blue-400 rounded-xl blur-lg opacity-20 group-hover:opacity-40 transition-opacity"></div>
          <div class="relative aspect-[16/9] bg-white rounded-xl overflow-hidden shadow-lg">
            <img
              src="<?php echo $imageUrl; ?>"
              alt="<?php echo htmlspecialchars($featuredAuction['itemName']); ?>"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            />
            
            <!-- Featured Badge -->
            <div class="absolute top-3 right-3 px-3 py-1 bg-gradient-to-r from-yellow-400 to-orange-500 text-white rounded-full shadow-lg text-xs font-medium">
              ⭐ <?php echo explode(' ', $featuredAuction['recommendationBadge'])[0]; ?>
            </div>
            
            <!-- Ending Soon Badge -->
            <?php if ($featuredAuction['hoursRemaining'] <= 24): ?>
            <div class="absolute top-3 left-3 px-3 py-1 bg-red-500 text-white rounded-full shadow-lg text-xs font-medium">
              🔥 <?php echo $featuredAuction['hoursRemaining']; ?>h left
            </div>
            <?php endif; ?>
            
            <!-- Watchlist Heart -->
            <button 
              type="button"
              class="watchlist-btn absolute bottom-3 right-3 w-10 h-10 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center hover:bg-white transition-all shadow-md z-10"
              data-auction-id="<?php echo $featuredAuction['auctionId']; ?>"
              data-watching="<?php echo $isWatchingFeatured ? 'true' : 'false'; ?>">
              <svg 
                class="w-5 h-5 transition-all <?php echo $isWatchingFeatured ? 'fill-red-500 text-red-500' : 'fill-none text-gray-400'; ?>" 
                stroke="currentColor" 
                stroke-width="2"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
              </svg>
            </button>
          </div>
        </div>

        <!-- Compact Content -->
        <div class="lg:w-1/2 flex flex-col justify-between">
          <div>
            <span class="inline-block px-2.5 py-0.5 bg-purple-100 text-purple-700 rounded-full text-xs mb-2 font-medium">
              <?php echo htmlspecialchars($featuredAuction['categoryName']); ?>
            </span>
            <h3 class="text-2xl font-bold text-gray-900 mb-2 group-hover:text-purple-600 transition-colors line-clamp-2">
              <?php echo htmlspecialchars($featuredAuction['itemName']); ?>
            </h3>
            <p class="text-gray-600 mb-3 text-xs">
              <?php echo $featuredAuction['recommendationReason']; ?>
            </p>
            <p class="text-gray-700 text-sm mb-4 line-clamp-2">
              <?php echo htmlspecialchars(substr($featuredAuction['description'], 0, 120)) . '...'; ?>
            </p>
          </div>

          <div class="space-y-4">
            <div class="bg-white rounded-xl p-4 shadow-md">
              <p class="text-xs text-gray-500 mb-1">Current Bid</p>
              <p class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-1">
                $<?php echo number_format($featuredAuction['currentBid'], 2); ?>
              </p>
              <div class="flex items-center justify-between text-xs text-gray-600 mt-3 pt-3 border-t border-gray-100">
                <span><?php echo $featuredAuction['bidCount']; ?> bids</span>
                <span class="flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                  <?php echo $featuredAuction['watchlistCount']; ?> watching
                </span>
              </div>
            </div>

            <a href="listing.php?auction_id=<?php echo $featuredAuction['auctionId']; ?>" 
              class="block w-full text-center py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl hover:shadow-xl hover:shadow-purple-500/30 transition-all font-medium">
              View Details →
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Recommended for You Grid (More Cards, Smaller Size) -->
  <?php if (count($auctions) > 1): 
  $recommendedAuctions = array_slice($auctions, 1, 11); // Show 11 instead of 6
  ?>
  <section>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-gray-900">💎 More Recommendations</h2>
      <span class="text-xs text-gray-500">Curated just for you</span>
    </div>

    <!-- Compact Grid Layout - 4 columns -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      
      <?php foreach ($recommendedAuctions as $auction): 
          // Check if watching
          $isWatching = false;
          $checkQuery = "SELECT * FROM AuctionWatch WHERE buyerId = ? AND auctionId = ?";
          $checkStmt = mysqli_prepare($connection, $checkQuery);
          mysqli_stmt_bind_param($checkStmt, 'ii', $buyerId, $auction['auctionId']);
          mysqli_stmt_execute($checkStmt);
          $checkResult = mysqli_stmt_get_result($checkStmt);
          $isWatching = mysqli_num_rows($checkResult) > 0;
          mysqli_stmt_close($checkStmt);
          
          $imageUrl = !empty($auction['photo']) ? htmlspecialchars($auction['photo']) : 'https://via.placeholder.com/400x300?text=No+Image';
      ?>
      
      <div class="bg-white rounded-xl overflow-hidden border border-gray-200 hover:shadow-lg hover:border-purple-300 transition-all cursor-pointer group">
        
        <!-- Compact Image -->
        <div class="relative h-32 bg-gray-100 overflow-hidden">
          <img
            src="<?php echo $imageUrl; ?>"
            alt="<?php echo htmlspecialchars($auction['itemName']); ?>"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          
          <!-- Badge -->
          <div class="absolute top-2 right-2 px-2 py-0.5 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-full text-xs shadow-md font-medium">
            <?php echo explode(' ', $auction['recommendationBadge'])[0]; ?>
          </div>
          
          <!-- Watchlist Heart -->
          <button 
            type="button"
            class="watchlist-btn absolute top-2 left-2 w-8 h-8 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center hover:bg-white transition-all shadow-md z-10"
            data-auction-id="<?php echo $auction['auctionId']; ?>"
            data-watching="<?php echo $isWatching ? 'true' : 'false'; ?>">
            <svg 
              class="w-4 h-4 transition-all <?php echo $isWatching ? 'fill-red-500 text-red-500' : 'fill-none text-gray-400'; ?>" 
              stroke="currentColor" 
              stroke-width="2"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
          </button>
        </div>

        <!-- Compact Content -->
        <div class="p-3">
          <span class="inline-block px-2 py-0.5 bg-purple-50 text-purple-700 rounded-full text-xs mb-2 font-medium">
            <?php echo htmlspecialchars($auction['categoryName']); ?>
          </span>
          <h3 class="text-sm font-semibold text-gray-900 mb-1 line-clamp-2 group-hover:text-purple-600 transition-colors leading-tight">
            <?php echo htmlspecialchars($auction['itemName']); ?>
          </h3>
          <p class="text-xs text-gray-500 mb-2 line-clamp-1"><?php echo $auction['recommendationReason']; ?></p>

          <div class="mb-2">
            <p class="text-xs text-gray-500 mb-0.5">Current Bid</p>
            <p class="text-lg font-bold text-green-600">$<?php echo number_format($auction['currentBid'], 2); ?></p>
          </div>

          <div class="flex items-center justify-between text-xs text-gray-600 mb-2 pb-2 border-t border-gray-100 pt-2">
            <span class="flex items-center gap-0.5">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <?php echo $auction['hoursRemaining']; ?>h
            </span>
            <span><?php echo $auction['bidCount']; ?> bids</span>
          </div>

          <a href="listing.php?auction_id=<?php echo $auction['auctionId']; ?>" 
            class="block w-full text-center py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-xs rounded-lg hover:shadow-md hover:shadow-purple-500/30 transition-all font-medium">
            View
          </a>
        </div>
      </div>
      
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- More Recommendations (Even Smaller Cards) -->
  <?php if (count($auctions) > 12):
  $moreAuctions = array_slice($auctions, 12);
  ?>
  <section>
    <h2 class="text-xl font-bold text-gray-900 mb-4">🎁 Explore More</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
      <?php foreach ($moreAuctions as $auction): 
          $isWatching = false;
          $checkQuery = "SELECT * FROM AuctionWatch WHERE buyerId = ? AND auctionId = ?";
          $checkStmt = mysqli_prepare($connection, $checkQuery);
          mysqli_stmt_bind_param($checkStmt, 'ii', $buyerId, $auction['auctionId']);
          mysqli_stmt_execute($checkStmt);
          $checkResult = mysqli_stmt_get_result($checkStmt);
          $isWatching = mysqli_num_rows($checkResult) > 0;
          mysqli_stmt_close($checkStmt);
          
          $imageUrl = !empty($auction['photo']) ? htmlspecialchars($auction['photo']) : 'https://via.placeholder.com/400x300?text=No+Image';
      ?>
      
      <div class="bg-white rounded-lg overflow-hidden border border-gray-200 hover:shadow-md hover:border-purple-300 transition-all cursor-pointer group">
        <div class="relative h-28 bg-gray-100 overflow-hidden">
          <img
            src="<?php echo $imageUrl; ?>"
            alt="<?php echo htmlspecialchars($auction['itemName']); ?>"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          
          <div class="absolute top-2 right-2 px-1.5 py-0.5 bg-purple-600 text-white rounded text-xs shadow-md font-medium">
            <?php echo explode(' ', $auction['recommendationBadge'])[0]; ?>
          </div>
          
          <button 
            type="button"
            class="watchlist-btn absolute top-2 left-2 w-7 h-7 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center hover:bg-white transition-all shadow-md z-10"
            data-auction-id="<?php echo $auction['auctionId']; ?>"
            data-watching="<?php echo $isWatching ? 'true' : 'false'; ?>">
            <svg 
              class="w-3 h-3 transition-all <?php echo $isWatching ? 'fill-red-500 text-red-500' : 'fill-none text-gray-400'; ?>" 
              stroke="currentColor" 
              stroke-width="2"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
          </button>
        </div>

        <div class="p-2">
          <h3 class="text-xs font-semibold text-gray-900 mb-1 line-clamp-2 group-hover:text-purple-600 transition-colors leading-tight">
            <?php echo htmlspecialchars($auction['itemName']); ?>
          </h3>

          <div class="mb-2">
            <p class="text-xs text-gray-500 mb-0">Bid</p>
            <p class="text-sm font-bold text-green-600">$<?php echo number_format($auction['currentBid'], 0); ?></p>
          </div>

          <div class="flex items-center justify-between text-xs text-gray-600 mb-2">
            <span class="text-xs"><?php echo $auction['bidCount']; ?></span>
            <span class="text-xs"><?php echo $auction['hoursRemaining']; ?>h</span>
          </div>

          <a href="listing.php?auction_id=<?php echo $auction['auctionId']; ?>" 
            class="block w-full text-center py-1.5 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-xs rounded-lg hover:shadow-md transition-all font-medium">
            View
          </a>
        </div>
      </div>
      
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  </div>

<?php else: ?>
  
  <!-- Empty State -->
  <div class="text-center py-20">
    <div class="max-w-md mx-auto">
      <div class="w-20 h-20 bg-gradient-to-br from-purple-100 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
        </svg>
      </div>
      <h3 class="text-2xl font-bold text-gray-900 mb-2">No Recommendations Yet</h3>
      <p class="text-gray-500 mb-6">Start bidding and watching auctions to get personalized recommendations!</p>
      <a href="browse.php" class="inline-block px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl hover:shadow-lg transition-all font-medium">
        Browse Auctions
      </a>
    </div>
  </div>

<?php endif; ?>

</main>

<!-- Watchlist Toggle Script -->
<script src="watchlist.js"></script>
<script>
// Copy the watchlist script from browse.php
document.addEventListener('DOMContentLoaded', function() {
    const watchlistBtns = document.querySelectorAll('.watchlist-btn');
    
    watchlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const auctionId = this.dataset.auctionId;
            const isWatching = this.dataset.watching === 'true';
            const svg = this.querySelector('svg');
            const card = this.closest('.bg-white') || this.closest('[class*="bg-gradient"]');
            
            this.disabled = true;
            svg.style.opacity = '0.5';
            
            fetch('toggle_watchlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'auctionId=' + auctionId
            })
            .then(response => response.json())
            .then(data => {
                svg.style.opacity = '1';
                
                if (data.success) {
                    if (data.watching) {
                        svg.classList.add('fill-red-500', 'text-red-500');
                        svg.classList.remove('fill-none', 'text-gray-400');
                        this.dataset.watching = 'true';
                        showToast('Added to watchlist', 'success');
                    } else {
                        svg.classList.remove('fill-red-500', 'text-red-500');
                        svg.classList.add('fill-none', 'text-gray-400');
                        this.dataset.watching = 'false';
                        showToast('Removed from watchlist', 'success');
                    }
                    
                    svg.style.transform = 'scale(1.3)';
                    setTimeout(() => {
                        svg.style.transform = 'scale(1)';
                    }, 200);
                } else {
                    showToast(data.message || 'Failed to update watchlist', 'error');
                }
                
                this.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                svg.style.opacity = '1';
                showToast('An error occurred. Please try again.', 'error');
                this.disabled = false;
            });
        });
    });
    
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 transition-all transform translate-x-0 ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        }`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '1';
        }, 10);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(400px)';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('header');
    const searchBar = document.getElementById('searchBar');
    const toggleSearchBtn = document.getElementById('toggleSearchBtn');
    const closeSearchBtn = document.getElementById('closeSearchBtn');
    let lastScrollTop = 0;
    let scrollTimeout;
    
    // Show/hide header based on scroll direction
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        
        scrollTimeout = setTimeout(function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down - hide header
                if (header) {
                    header.style.transform = 'translateY(-100%)';
                    header.style.transition = 'transform 0.8s ease-in-out';
                }
            } else if (scrollTop < lastScrollTop + 100) {
                // Scrolling up - show header
                if (header) {
                    header.style.transform = 'translateY(0)';
                    header.style.transition = 'transform 1.3s ease-in-out';
                }
            }
            
            lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
        }, 100);
    }, false);
});
</script>

</body>
</html>

<?php include_once("footer.php"); ?>