<?php 
include_once("header.php");
require("utilities.php");

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-warning">Please <a href="login.php">sign in</a> to browse listings.</div>';
    include_once("footer.php");
    exit();
}

require_once 'database.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Listings</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-50">

<?php
// Get filter parameters
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$category = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'ending-soon';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$minPrice = isset($_GET['minPrice']) ? intval($_GET['minPrice']) : 0;
$maxPrice = isset($_GET['maxPrice']) ? intval($_GET['maxPrice']) : 999999;

// Fetch statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN state = 'ongoing' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN state = 'finished' THEN 1 ELSE 0 END) as ended,
    SUM(CASE WHEN state = 'not-started' THEN 1 ELSE 0 END) as upcoming,
    SUM(CASE WHEN state = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM Auction";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Fetch all categories
$categoriesQuery = "SELECT * FROM Category ORDER BY name ASC";
$categoriesResult = mysqli_query($connection, $categoriesQuery);
$allCategories = [];
if ($categoriesResult) {
    while ($catRow = mysqli_fetch_assoc($categoriesResult)) {
        $allCategories[] = $catRow['name'];
    }
}
?>

<!-- Header with Scroll Effect -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-20 transition-all duration-300" id="stickyHeader">
  <div class="max-w-7xl mx-auto px-6 lg:px-8 py-6">
    
    <!-- Title -->
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900 mb-1">Auction Browse</h1>
      <p class="text-gray-600">Discover amazing items up for auction</p>
    </div>

    <!-- Search Bar with Better Spacing -->
    <form method="get" action="browse.php" id="searchForm" class="bg-white rounded-2xl p-6 mb-6 shadow-lg border border-gray-100">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        
        <!-- Search Input -->
        <div class="md:col-span-5 relative">
          <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
          <input
            type="text"
            name="keyword"
            id="keywordInput"
            placeholder="Search for anything"
            value="<?php echo htmlspecialchars($keyword); ?>"
            class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 placeholder-gray-500"
          />
        </div>

        <!-- Status Filter - Auto-submit on change -->
        <div class="md:col-span-2 relative">
          <select
            name="status"
            id="statusFilter"
            onchange="this.form.submit()"
            class="appearance-none w-full pl-4 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer text-gray-700"
          >
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Active</option>
            <option value="not-started" <?php echo $status_filter === 'not-started' ? 'selected' : ''; ?>>Upcoming</option>

          </select>
          <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </div>

        <!-- Sort Filter - Auto-submit on change -->
        <div class="md:col-span-3 relative">
          <select
            name="order_by"
            id="orderByFilter"
            onchange="this.form.submit()"
            class="appearance-none w-full pl-4 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer text-gray-700"
          >
            <option value="ending-soon" <?php echo $order_by === 'ending-soon' ? 'selected' : ''; ?>>Soonest Expiry</option>
            <option value="newly-listed" <?php echo $order_by === 'newly-listed' ? 'selected' : ''; ?>>Newly Listed</option>
            <option value="pricelow" <?php echo $order_by === 'pricelow' ? 'selected' : ''; ?>>Price: Low to High</option>
            <option value="pricehigh" <?php echo $order_by === 'pricehigh' ? 'selected' : ''; ?>>Price: High to Low</option>
            <option value="bidcount" <?php echo $order_by === 'bidcount' ? 'selected' : ''; ?>>Most Bids</option>
          </select>
          <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </div>

        <!-- Hidden inputs for preserving filters -->
        <input type="hidden" name="cat" id="catInput" value="<?php echo htmlspecialchars($category); ?>">
        <input type="hidden" name="minPrice" id="minPriceInput" value="<?php echo $minPrice; ?>">
        <input type="hidden" name="maxPrice" id="maxPriceInput" value="<?php echo $maxPrice; ?>">

        <!-- Search Button - Only for keyword search -->
        <div class="md:col-span-2">
          <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl hover:shadow-lg hover:shadow-purple-500/30 transition-all whitespace-nowrap font-medium flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            Search
          </button>
        </div>
      </div>
    </form>

    <!-- Category Pills - All in One Row -->
    <div class="mb-6">
      <div class="flex items-center gap-2 mb-3">
        <span class="text-sm font-medium text-gray-600">Categories:</span>
      </div>
      
      <!-- Category pills container - no scroll -->
      <div class="flex flex-wrap gap-2">
        <a href="?cat=all&keyword=<?php echo urlencode($keyword); ?>&status=<?php echo urlencode($status_filter); ?>&order_by=<?php echo urlencode($order_by); ?>" 
          class="px-7 py-3 text-sm font-medium rounded-full transition-all whitespace-nowrap <?php echo $category === 'all' ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:border-purple-400'; ?>">
          All Categories
        </a>
        <?php foreach ($allCategories as $cat): ?>
        <a href="?cat=<?php echo urlencode($cat); ?>&keyword=<?php echo urlencode($keyword); ?>&status=<?php echo urlencode($status_filter); ?>&order_by=<?php echo urlencode($order_by); ?>" 
          class="px-7 py-3 text-sm font-medium rounded-full transition-all whitespace-nowrap <?php echo $category === $cat ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white shadow-md' : 'bg-white text-gray-700 border border-gray-300 hover:border-purple-400'; ?>">
          <?php echo htmlspecialchars($cat); ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Advanced Filters Toggle -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          type="button"
          onclick="document.getElementById('advancedFilters').classList.toggle('hidden')"
          class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg hover:border-purple-400 transition-all text-sm text-gray-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
          </svg>
          <span>Price Range</span>
        </button>
        
        <?php if ($keyword || $category !== 'all' || $status_filter !== 'all' || $minPrice > 0 || $maxPrice < 999999): ?>
        <a href="browse.php" class="text-sm text-purple-600 hover:text-purple-700 font-medium">
          Clear filters
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Advanced Filters Panel -->
    <div id="advancedFilters" class="hidden bg-white border border-gray-200 rounded-xl p-6 mt-4 shadow-sm">
      <form method="get" action="browse.php" class="flex gap-4 items-end">
        <input type="hidden" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>">
        <input type="hidden" name="cat" value="<?php echo htmlspecialchars($category); ?>">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
        <input type="hidden" name="order_by" value="<?php echo htmlspecialchars($order_by); ?>">
        
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-2">Min Price</label>
          <div class="relative">
            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
            <input
              type="number"
              name="minPrice"
              placeholder="0"
              value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>"
              class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-gray-900"
            />
          </div>
        </div>
        <div class="flex items-center pb-3">
          <div class="h-px w-8 bg-gray-300"></div>
        </div>
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-2">Max Price</label>
          <div class="relative">
            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
            <input
              type="number"
              name="maxPrice"
              placeholder="999999"
              value="<?php echo $maxPrice < 999999 ? $maxPrice : ''; ?>"
              class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-gray-900"
            />
          </div>
        </div>
        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg hover:shadow-lg transition-all font-medium">
          Apply
        </button>
      </form>
    </div>
  </div>
</header>

<!-- Add CSS for hiding scrollbar and smooth transitions -->
<style>
/* Hide scrollbar for category pills */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

/* Smooth scroll for categories */
.scrollbar-hide {
  scroll-behavior: smooth;
}

/* Header transition styles - Smoother and Slower */
#stickyHeader {
  transition: transform 1.2s cubic-bezier(0.4, 0, 0.2, 1), 
              opacity 1.2s cubic-bezier(0.4, 0, 0.2, 1), 
              box-shadow 1.2s cubic-bezier(0.4, 0, 0.2, 1);
}

#stickyHeader.header-hidden {
  transform: translateY(-100%);
  opacity: 0;
  pointer-events: none;
}

#stickyHeader.header-visible {
  transform: translateY(0);
  opacity: 1;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  pointer-events: auto;
}
</style>

<!-- Scroll Behavior Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const header = document.getElementById('stickyHeader');
    let lastScrollTop = 0;
    let scrollThreshold = 150; // Start hiding after 150px scroll
    let ticking = false;
    
    function updateHeader(scrollTop) {
        if (scrollTop > scrollThreshold) {
            if (scrollTop > lastScrollTop) {
                // Scrolling down - hide header
                header.classList.add('header-hidden');
                header.classList.remove('header-visible');
            } else {
                // Scrolling up - show header
                header.classList.remove('header-hidden');
                header.classList.add('header-visible');
            }
        } else {
            // At top of page - always show header without classes
            header.classList.remove('header-hidden', 'header-visible');
        }
        
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    }
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (!ticking) {
            window.requestAnimationFrame(function() {
                updateHeader(scrollTop);
                ticking = false;
            });
            
            ticking = true;
        }
    }, false);
});
</script>

<!-- Main Content -->
<main class="max-w-7xl mx-auto px-6 lg:px-8 py-8">
  
  <?php
  // Build query with filters
  $sql = "SELECT 
      a.auctionId, 
      i.name AS itemName, 
      i.description, 
      i.photo,
      a.startDate, 
      a.endDate, 
      a.startingPrice,
      a.state,
      c.name AS categoryName,
      COALESCE((SELECT MAX(bidAmount) FROM Bid WHERE auctionId = a.auctionId), a.startingPrice) as currentBid
      FROM Auction a
      JOIN Item i ON a.itemId = i.itemId
      JOIN Category c ON i.categoryId = c.categoryId
      WHERE a.endDate > NOW()";

  $params = [];
  $types = '';

  if (!empty($keyword)) {
      $sql .= " AND i.name LIKE ?";
      $keywordParam = '%' . $keyword . '%';
      $params[] = $keywordParam;
      $params[] = $keywordParam;
      $types .= 'ss';
  }

  if ($category !== 'all') {
      $sql .= " AND c.name = ?";
      $params[] = $category;
      $types .= 's';
  }

  if ($status_filter !== 'all') {
      $sql .= " AND a.state = ?";
      $params[] = $status_filter;
      $types .= 's';
  }

  // Price range filter - Using currentBid (MUST come after WHERE clause, before ORDER BY)
  // We need to use HAVING clause since currentBid is calculated
  $havingClauses = [];

  if ($minPrice > 0) {
      $havingClauses[] = "currentBid >= ?";
      $params[] = $minPrice;
      $types .= 'i';
  }

  if ($maxPrice < 999999) {
      $havingClauses[] = "currentBid <= ?";
      $params[] = $maxPrice;
      $types .= 'i';
  }

  // Sorting - Using currentBid
  switch ($order_by) {
      case 'pricelow':
          $orderClause = " ORDER BY currentBid ASC";
          break;
      case 'pricehigh':
          $orderClause = " ORDER BY currentBid DESC";
          break;
      case 'newly-listed':
          $orderClause = " ORDER BY a.startDate DESC";
          break;
      case 'ending-soon':
      default:
          $orderClause = " ORDER BY a.endDate ASC";
          break;
  }

  // Add HAVING clause if price filters exist
  if (!empty($havingClauses)) {
      $sql .= " HAVING " . implode(" AND ", $havingClauses);
  }

  // Add ORDER BY
  $sql .= $orderClause;

  $stmt = mysqli_prepare($connection, $sql);

  if (!$stmt) {
    echo '<div class="p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">
            Database error: ' . htmlspecialchars(mysqli_error($connection)) . '
          </div>';
} else {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rowCount = $result ? mysqli_num_rows($result) : 0;

    // Display result count
    echo '<p class="text-gray-600 mb-6">
            <span class="text-gray-900 font-semibold">' . $rowCount . '</span> ' . ($rowCount === 1 ? 'item' : 'items') . ' found
          </p>';

    if ($result && $rowCount > 0) {
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
        
        while ($row = mysqli_fetch_assoc($result)) {
            $description = strlen($row['description']) > 100 ? substr($row['description'], 0, 100) . '...' : $row['description'];
            
            // ✅ Use currentBid from query result (no need to fetch separately)
            $currentBid = $row['currentBid'];
            
            // Get bid count
            $bidCountQuery = "SELECT COUNT(*) as count FROM Bid WHERE auctionId = ?";
            $bidStmt = mysqli_prepare($connection, $bidCountQuery);
            mysqli_stmt_bind_param($bidStmt, 'i', $row['auctionId']);
            mysqli_stmt_execute($bidStmt);
            $bidResult = mysqli_stmt_get_result($bidStmt);
            $bidRow = mysqli_fetch_assoc($bidResult);
            $bidCount = $bidRow['count'] ?? 0;
            mysqli_stmt_close($bidStmt);
            
            // Get watchlist count
            $watchQuery = "SELECT COUNT(*) as count FROM AuctionWatch WHERE auctionId = ?";
            $wStmt = mysqli_prepare($connection, $watchQuery);
            mysqli_stmt_bind_param($wStmt, 'i', $row['auctionId']);
            mysqli_stmt_execute($wStmt);
            $wResult = mysqli_stmt_get_result($wStmt);
            $wRow = mysqli_fetch_assoc($wResult);
            $watchlistCount = $wRow['count'] ?? 0;
            mysqli_stmt_close($wStmt);
            
            // Check if current user is watching
            $isWatching = false;
            if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'buyer' && isset($_SESSION['user_id'])) {
                $checkWatchQuery = "SELECT * FROM AuctionWatch WHERE buyerId = ? AND auctionId = ?";
                $checkWatchStmt = mysqli_prepare($connection, $checkWatchQuery);
                mysqli_stmt_bind_param($checkWatchStmt, 'ii', $_SESSION['user_id'], $row['auctionId']);
                mysqli_stmt_execute($checkWatchStmt);
                $checkWatchResult = mysqli_stmt_get_result($checkWatchStmt);
                $isWatching = mysqli_num_rows($checkWatchResult) > 0;
                mysqli_stmt_close($checkWatchStmt);
            }
      
      // Image handling
      $imageUrl = 'https://via.placeholder.com/400x300?text=No+Image';
      if (!empty($row['photo'])) {
          $imageUrl = htmlspecialchars($row['photo']);
      }
      
      // Status badge
      $statusBadge = '';
      $statusClass = '';
      switch ($row['state']) {
          case 'ongoing':
              $statusBadge = 'Active';
              $statusClass = 'bg-green-500';
              break;
          case 'not-started':
              $statusBadge = 'Upcoming';
              $statusClass = 'bg-blue-500';
              break;
          case 'finished':
              $statusBadge = 'Ended';
              $statusClass = 'bg-gray-500';
              break;
      }
      
      // Check if ending soon (within 24 hours)
      $timeRemaining = strtotime($row['endDate']) - time();
      $isEndingSoon = $timeRemaining > 0 && $timeRemaining < 86400 && $row['state'] === 'ongoing';
      ?>
      <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 hover:shadow-xl hover:border-purple-300 transition-all cursor-pointer group">
        
        <!-- Image -->
        <div class="relative aspect-[16/9] bg-gray-100 overflow-hidden">
          <img
            src="<?php echo $imageUrl; ?>"
            alt="<?php echo htmlspecialchars($row['itemName']); ?>"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          
          <!-- Status Badge -->
          <?php if ($isEndingSoon): ?>
          <div class="absolute top-4 right-4 px-3 py-1.5 bg-red-500 text-white rounded-full text-xs shadow-lg font-medium">
            Ending Soon
          </div>
          <?php elseif ($statusBadge): ?>
          <div class="absolute top-4 right-4 px-3 py-1.5 <?php echo $statusClass; ?> text-white rounded-full text-xs shadow-lg font-medium">
            <?php echo $statusBadge; ?>
          </div>
          <?php endif; ?>
          
          <!-- Watchlist Heart Icon (Only for Buyers) -->
          <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'buyer'): ?>
          <button 
            type="button"
            class="watchlist-btn absolute top-4 left-4 w-10 h-10 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center hover:bg-white transition-all shadow-md z-10"
            data-auction-id="<?php echo $row['auctionId']; ?>"
            data-watching="<?php echo $isWatching ? 'true' : 'false'; ?>"
            title="<?php echo $isWatching ? 'Remove from watchlist' : 'Add to watchlist'; ?>">
            <svg 
              class="w-5 h-5 transition-all <?php echo $isWatching ? 'fill-red-500 text-red-500' : 'fill-none text-gray-400'; ?>" 
              stroke="currentColor" 
              stroke-width="2"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg">
              <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
              </path>
            </svg>
          </button>
          <?php endif; ?>
        </div>

        <!-- Content -->
        <div class="p-6">
          <h3 class="text-xl font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-purple-600 transition-colors">
            <?php echo htmlspecialchars($row['itemName']); ?>
          </h3>
          <p class="text-sm text-gray-500 mb-4"><?php echo htmlspecialchars($row['categoryName']); ?></p>

          <div class="mb-4">
            <p class="text-xs text-gray-500 mb-1">Current Bid:</p>
            <p class="text-2xl font-bold text-green-600">$<?php echo number_format($currentBid, 2); ?></p>
          </div>

          <div class="flex items-center justify-between text-sm text-gray-600 mb-4 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span>Ends: <?php echo date('M d, Y', strtotime($row['endDate'])); ?></span>
            </div>
          </div>

          <div class="flex items-center justify-between text-sm mb-4">
            <div class="flex items-center gap-1 text-gray-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"></path>
              </svg>
              <span><?php echo $bidCount; ?> bids</span>
            </div>
            <div class="flex items-center gap-1 text-gray-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
              </svg>
              <span class="watchlist-count"><?php echo $watchlistCount; ?></span> watching
            </div>
          </div>

          <a href="listing.php?auction_id=<?php echo intval($row['auctionId']); ?>" 
             class="block w-full text-center py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl hover:shadow-lg hover:shadow-purple-500/30 transition-all font-medium">
            View Details
          </a>
        </div>
      </div>
      <?php
  }
  
  echo '</div>';
} else {
          ?>
          <div class="text-center py-20">
            <div class="max-w-md mx-auto">
              <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">No auctions found</h3>
              <p class="text-gray-500">Try adjusting your search or filters</p>
            </div>
          </div>
          <?php
      }

      mysqli_stmt_close($stmt);
  }
  ?>

</main>

<!-- Watchlist Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const watchlistBtns = document.querySelectorAll('.watchlist-btn');
    
    watchlistBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const auctionId = this.dataset.auctionId;
            const isWatching = this.dataset.watching === 'true';
            const svg = this.querySelector('svg');
            const card = this.closest('.bg-white');
            const watchlistCountElem = card.querySelector('.watchlist-count');
            
            // Disable button during request
            this.disabled = true;
            
            // Show loading state
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
                // Reset opacity
                svg.style.opacity = '1';
                
                if (data.success) {
                    // Toggle the heart appearance
                    if (data.watching) {
                        // Added to watchlist
                        svg.classList.add('fill-red-500', 'text-red-500');
                        svg.classList.remove('fill-none', 'text-gray-400');
                        this.dataset.watching = 'true';
                        this.title = 'Remove from watchlist';
                        
                        // Increment count
                        if (watchlistCountElem) {
                            const currentCount = parseInt(watchlistCountElem.textContent) || 0;
                            watchlistCountElem.textContent = currentCount + 1;
                        }
                        
                        // Show success feedback
                        showToast('Added to watchlist', 'success');
                    } else {
                        // Removed from watchlist
                        svg.classList.remove('fill-red-500', 'text-red-500');
                        svg.classList.add('fill-none', 'text-gray-400');
                        this.dataset.watching = 'false';
                        this.title = 'Add to watchlist';
                        
                        // Decrement count
                        if (watchlistCountElem) {
                            const currentCount = parseInt(watchlistCountElem.textContent) || 0;
                            watchlistCountElem.textContent = Math.max(0, currentCount - 1);
                        }
                        
                        // Show success feedback
                        showToast('Removed from watchlist', 'success');
                    }
                    
                    // Add animation
                    svg.style.transform = 'scale(1.3)';
                    setTimeout(() => {
                        svg.style.transform = 'scale(1)';
                    }, 200);
                } else {
                    // Show error message
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
    
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 transition-all transform translate-x-0 ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        }`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Fade in
        setTimeout(() => {
            toast.style.opacity = '1';
        }, 10);
        
        // Remove after 3 seconds
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

<style>
.watchlist-btn {
    transition: all 0.2s ease;
}

.watchlist-btn:hover {
    transform: scale(1.1);
}

.watchlist-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.watchlist-btn svg {
    transition: all 0.3s ease;
}

/* Toast animation styles */
div[class*="fixed top-4 right-4"] {
    opacity: 0;
    transition: opacity 0.3s ease, transform 0.3s ease;
}
</style>

</body>
</html>

<?php include_once("footer.php"); ?>