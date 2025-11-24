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

<body class="min-h-screen bg-gradient-to-br from-purple-50 via-white to-blue-50 p-6">

<div class="max-w-7xl mx-auto">
  
  <!-- Header -->
  <div class="text-center mb-8">
    <h1 class="text-gray-900 mb-3 text-3xl font-semibold">Browse Auctions</h1>
    <p class="text-gray-600">Discover amazing items up for auction</p>
  </div>

  <?php
  // Show flash messages
  if (!empty($_SESSION['browse_error'])) {
      echo '<div class="mb-6 p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">' . htmlspecialchars($_SESSION['browse_error']) . '</div>';
      unset($_SESSION['browse_error']);
  }
  if (!empty($_SESSION['browse_success'])) {
      echo '<div class="mb-6 p-4 rounded-xl border border-green-200 bg-green-50 text-green-700">' . htmlspecialchars($_SESSION['browse_success']) . '</div>';
      unset($_SESSION['browse_success']);
  }

  // Get filter parameters
  $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
  $category = isset($_GET['cat']) ? $_GET['cat'] : 'all';
  $order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'date';
  $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

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
  ?>

  <!-- Stats Overview -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
      <div class="text-gray-500 text-sm mb-1">Total Auctions</div>
      <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
      <div class="text-gray-500 text-sm mb-1">Active</div>
      <div class="text-2xl font-bold text-green-600"><?php echo $stats['active']; ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
      <div class="text-gray-500 text-sm mb-1">Ended</div>
      <div class="text-2xl font-bold text-gray-600"><?php echo $stats['ended']; ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
      <div class="text-gray-500 text-sm mb-1">Upcoming</div>
      <div class="text-2xl font-bold text-blue-600"><?php echo $stats['upcoming']; ?></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
      <div class="text-gray-500 text-sm mb-1">Cancelled</div>
      <div class="text-2xl font-bold text-red-600"><?php echo $stats['cancelled']; ?></div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 mb-8">
    <form method="get" action="browse.php" class="space-y-4">
      
      <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        
        <!-- Search Keyword -->
        <div class="md:col-span-4">
          <label for="keyword" class="sr-only">Search keyword:</label>
          <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </span>
            <input 
              type="text" 
              id="keyword"
              name="keyword" 
              placeholder="Search for anything" 
              value="<?php echo htmlspecialchars($keyword); ?>"
              class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
          </div>
        </div>

        <!-- Status Filter -->
        <div class="md:col-span-2">
          <label for="status" class="sr-only">Status:</label>
          <select 
            id="status" 
            name="status"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Active</option>
            <option value="not-started" <?php echo $status_filter === 'not-started' ? 'selected' : ''; ?>>Upcoming</option>
            <option value="finished" <?php echo $status_filter === 'finished' ? 'selected' : ''; ?>>Ended</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
          </select>
        </div>

        <!-- Category Dropdown -->
        <div class="md:col-span-2">
          <label for="cat" class="sr-only">Category:</label>
          <select 
            id="cat" 
            name="cat"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
            <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
            <?php
            $categoryQuery = "SELECT * FROM Category ORDER BY name ASC";
            $categoryResult = mysqli_query($connection, $categoryQuery);
            if ($categoryResult) {
                while ($categoryRow = mysqli_fetch_assoc($categoryResult)) {
                    $selected = ($category === $categoryRow['name']) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($categoryRow['name']) . '" ' . $selected . '>' . htmlspecialchars($categoryRow['name']) . '</option>';
                }
            }
            ?>
          </select>
        </div>

        <!-- Sort Dropdown -->
        <div class="md:col-span-3">
          <label for="order_by" class="sr-only">Sort by:</label>
          <select 
            id="order_by" 
            name="order_by"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
            <option value="date" <?php echo $order_by === 'date' ? 'selected' : ''; ?>>Soonest Expiry</option>
            <option value="pricelow" <?php echo $order_by === 'pricelow' ? 'selected' : ''; ?>>Price: Low to High</option>
            <option value="pricehigh" <?php echo $order_by === 'pricehigh' ? 'selected' : ''; ?>>Price: High to Low</option>
            <option value="bidcount" <?php echo $order_by === 'bidcount' ? 'selected' : ''; ?>>Most Bids</option>
          </select>
        </div>

        <!-- Search Button -->
        <div class="md:col-span-1">
        <button 
          type="submit" 
          class="w-full h-full px-6 py-3 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl hover:shadow-lg transition-all flex items-center justify-center">
          Search
        </button>
      </div>
        
      </div>
    </form>
  </div>

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
  a.state
  FROM Auction a
  JOIN Item i ON a.itemId = i.itemId
  JOIN Category c ON i.categoryId = c.categoryId
  WHERE 1=1";

$params = [];
$types = '';

if (!empty($keyword)) {
  $sql .= " AND (i.name LIKE ? OR i.description LIKE ?)";
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

// Sorting
switch ($order_by) {
  case 'pricelow':
      $sql .= " ORDER BY a.startingPrice ASC";
      break;
  case 'pricehigh':
      $sql .= " ORDER BY a.startingPrice DESC";
      break;
  case 'date':
  default:
      $sql .= " ORDER BY a.endDate ASC";
      break;
}

echo "<!-- DEBUG: Simplified SQL: " . htmlspecialchars($sql) . " -->";

$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {
  echo "<!-- PREPARE FAILED: " . htmlspecialchars(mysqli_error($connection)) . " -->";
  echo '<div class="p-4 rounded-xl border border-red-200 bg-red-50 text-red-700">
          Database error: ' . htmlspecialchars(mysqli_error($connection)) . '
        </div>';
} else {
  echo "<!-- Statement prepared -->";
  
  if (!empty($params)) {
      mysqli_stmt_bind_param($stmt, $types, ...$params);
  }

  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  
  if (!$result) {
      echo "<!-- Get result FAILED -->";
      $rowCount = 0;
  } else {
      $rowCount = mysqli_num_rows($result);
      echo "<!-- Rows returned: " . $rowCount . " -->";
  }

  if ($result && $rowCount > 0) {
      echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
      
      while ($row = mysqli_fetch_assoc($result)) {
          $description = strlen($row['description']) > 100 ? substr($row['description'], 0, 100) . '...' : $row['description'];
          
          // Get bid count separately
          $bidCountQuery = "SELECT COUNT(*) as count FROM Bid WHERE auctionId = ?";
          $bidStmt = mysqli_prepare($connection, $bidCountQuery);
          mysqli_stmt_bind_param($bidStmt, 'i', $row['auctionId']);
          mysqli_stmt_execute($bidStmt);
          $bidResult = mysqli_stmt_get_result($bidStmt);
          $bidRow = mysqli_fetch_assoc($bidResult);
          $bidCount = $bidRow['count'] ?? 0;
          mysqli_stmt_close($bidStmt);
          
          // Get current bid separately
          $currentBidQuery = "SELECT MAX(bidAmount) as maxBid FROM Bid WHERE auctionId = ?";
          $cbStmt = mysqli_prepare($connection, $currentBidQuery);
          mysqli_stmt_bind_param($cbStmt, 'i', $row['auctionId']);
          mysqli_stmt_execute($cbStmt);
          $cbResult = mysqli_stmt_get_result($cbStmt);
          $cbRow = mysqli_fetch_assoc($cbResult);
          $currentBid = $cbRow['maxBid'] ?? $row['startingPrice'];
          mysqli_stmt_close($cbStmt);
          
          // Get watchlist count separately
          $watchQuery = "SELECT COUNT(*) as count FROM AuctionWatch WHERE auctionId = ?";
          $wStmt = mysqli_prepare($connection, $watchQuery);
          mysqli_stmt_bind_param($wStmt, 'i', $row['auctionId']);
          mysqli_stmt_execute($wStmt);
          $wResult = mysqli_stmt_get_result($wStmt);
          $wRow = mysqli_fetch_assoc($wResult);
          $watchlistCount = $wRow['count'] ?? 0;
          mysqli_stmt_close($wStmt);
          
          // Image handling
          $imageUrl = 'https://via.placeholder.com/400x300?text=No+Image';
          if (!empty($row['photo'])) {
              $imageUrl = htmlspecialchars($row['photo']);
          }
          
          // Status badge styling
          $statusClass = '';
          $statusLabel = '';
          switch ($row['state']) {
              case 'ongoing':
                  $statusClass = 'bg-green-100 text-green-700';
                  $statusLabel = 'Active';
                  break;
              case 'not-started':
                  $statusClass = 'bg-blue-100 text-blue-700';
                  $statusLabel = 'Upcoming';
                  break;
              case 'finished':
                  $statusClass = 'bg-gray-100 text-gray-700';
                  $statusLabel = 'Ended';
                  break;
              case 'cancelled':
                  $statusClass = 'bg-red-100 text-red-700';
                  $statusLabel = 'Cancelled';
                  break;
              default:
                  $statusClass = 'bg-gray-100 text-gray-700';
                  $statusLabel = ucfirst($row['state']);
          }
          ?>
          <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-all">
            <!-- Image -->
            <div class="relative h-48 bg-gray-100">
              <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($row['itemName']); ?>" class="w-full h-full object-cover">
              <span class="absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                <?php echo $statusLabel; ?>
              </span>
            </div>
            
            <!-- Content -->
            <div class="p-6">
              <h3 class="text-xl font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($row['itemName']); ?></h3>
              <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($description); ?></p>
              
              <!-- Bid Info -->
              <div class="space-y-3 mb-4">
                <div class="flex justify-between items-center">
                  <span class="text-sm text-gray-500">Starting Bid:</span>
                  <span class="text-xl font-bold text-green-600">$<?php echo number_format($currentBid, 2); ?></span>
                </div>
                
                <div class="flex justify-between items-center text-sm">
                  <div class="flex items-center gap-1 text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Ends: <?php echo date('M d, Y', strtotime($row['endDate'])); ?>
                  </div>
                </div>
                
                <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                  <div class="flex items-center gap-1 text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"></path>
                    </svg>
                    <?php echo $bidCount; ?> bids
                  </div>
                  <div class="flex items-center gap-1 text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    <?php echo $watchlistCount; ?> watching
                  </div>
                </div>
              </div>
              
              <a href="listing.php?auction_id=<?php echo intval($row['auctionId']); ?>" 
                 class="block w-full text-center px-4 py-2.5 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl hover:shadow-lg transition-all font-medium">
                View Details
              </a>
            </div>
          </div>
          <?php
      }
      
      echo '</div>';
  } else {
      echo '<div class="text-center py-16 bg-white rounded-2xl shadow-lg border border-gray-100">
              <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p class="text-gray-600 text-lg font-medium mb-2">No auctions found</p>
              <p class="text-gray-500 text-sm">Try creating your first auction!</p>
              <a href="create_auction.php" class="mt-4 inline-block px-6 py-2.5 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl hover:shadow-lg transition-all font-medium">
                Create Auction
              </a>
            </div>';
  }

  mysqli_stmt_close($stmt);
}
  ?>

</div>

</body>
</html>

<?php include_once("footer.php"); ?>