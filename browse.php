<?php 
include_once("header.php");
require("utilities.php");

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-warning">Please <a href="login.php">sign in</a> to browse listings.</div>';
    include_once("footer.php");
    exit();
}

?>

<div class="container">

<h2 class="my-3">Browse listings</h2>

<?php
// Show flash messages if present
if (!empty($_SESSION['browse_error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['browse_error']) . '</div>';
    unset($_SESSION['browse_error']);
}
if (!empty($_SESSION['browse_success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['browse_success']) . '</div>';
    unset($_SESSION['browse_success']);
}
?>

<div id="searchSpecs">
<!-- Search form -->
<form method="get" action="browse.php">
  <div class="row">
    <!-- Search Keyword -->
    <div class="col-md-5 pr-0">
      <div class="form-group">
        <label for="keyword" class="sr-only">Search keyword:</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text bg-transparent pr-0 text-muted">
              <i class="fa fa-search"></i>
            </span>
          </div>
          <input 
            type="text" 
            class="form-control border-left-0" 
            id="keyword" 
            name="keyword" 
            placeholder="Search for anything" 
            value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
        </div>
      </div>
    </div>

    <!-- Category Dropdown -->
    <div class="col-md-3 pr-0">
      <div class="form-group">
        <label for="cat" class="sr-only">Search within:</label>
        <select class="form-control" id="cat" name="cat">
          <option value="all" <?php echo (isset($_GET['cat']) && $_GET['cat'] === 'all') ? 'selected' : ''; ?>>All categories</option>
          <?php
          // Fetch categories from the database
          require_once 'database.php';
          $categoryQuery = "SELECT * FROM Category ORDER BY name ASC";
          $categoryResult = mysqli_query($connection, $categoryQuery);

          if ($categoryResult) {
              while ($categoryRow = mysqli_fetch_assoc($categoryResult)) {
                  $selected = (isset($_GET['cat']) && $_GET['cat'] === $categoryRow['name']) ? 'selected' : '';
                  echo '<option value="' . htmlspecialchars($categoryRow['name']) . '" ' . $selected . '>' . htmlspecialchars($categoryRow['name']) . '</option>';
              }
          }
          ?>
        </select>
      </div>
    </div>

    <!-- Sort Dropdown -->
    <div class="col-md-3 pr-0">
      <div class="form-inline">
        <label class="mx-2" for="order_by">Sort by:</label>
        <select class="form-control" id="order_by" name="order_by">
          <option value="pricelow" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] === 'pricelow') ? 'selected' : ''; ?>>Price (low to high)</option>
          <option value="pricehigh" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] === 'pricehigh') ? 'selected' : ''; ?>>Price (high to low)</option>
          <option value="date" <?php echo (isset($_GET['order_by']) && $_GET['order_by'] === 'date') ? 'selected' : ''; ?>>Soonest expiry</option>
        </select>
      </div>
    </div>

    <!-- Search Button -->
    <div class="col-md-1">
      <button type="submit" class="btn btn-primary">Search</button>
    </div>
  </div>
</form>
</div>

<?php
// Fetch and display auction listings
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$category = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'date';

$sql = "SELECT a.auctionId, i.name AS itemName, a.startDate, a.endDate, a.startingPrice, a.state
        FROM Auction a
        JOIN Item i ON a.itemId = i.itemId
        JOIN Category c ON i.categoryId = c.categoryId
        WHERE 1=1";

// Add filters
if (!empty($keyword)) {
    $sql .= " AND (i.name LIKE ? OR i.description LIKE ?)";
}
if ($category !== 'all') {
    $sql .= " AND c.name = ?";
}

// Add sorting
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

$stmt = mysqli_prepare($connection, $sql);
if ($stmt) {
    // Bind parameters based on the search criteria
    if (!empty($keyword) && $category !== 'all') {
        $keywordParam = '%' . $keyword . '%';
        mysqli_stmt_bind_param($stmt, 'sss', $keywordParam, $keywordParam, $category);
    } elseif (!empty($keyword)) {
        $keywordParam = '%' . $keyword . '%';
        mysqli_stmt_bind_param($stmt, 'ss', $keywordParam, $keywordParam);
    } elseif ($category !== 'all') {
        mysqli_stmt_bind_param($stmt, 's', $category);
    }

    // Execute the query
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Check if there are results
    if (mysqli_num_rows($result) > 0) {
        echo '<div class="row">';
        while ($row = mysqli_fetch_assoc($result)) {
            // Display each result as a card
            ?>
            <div class="col-md-4 mb-4">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars($row['itemName']); ?></h5>
                  <p class="card-text">Start: <?php echo htmlspecialchars($row['startDate']); ?> — End: <?php echo htmlspecialchars($row['endDate']); ?></p>
                  <p class="card-text">Starting Price: $<?php echo htmlspecialchars($row['startingPrice']); ?></p>
                  <p class="card-text">State: <?php echo htmlspecialchars($row['state']); ?></p>
                  <a href="listing.php?auction_id=<?php echo intval($row['auctionId']); ?>" class="btn btn-primary">View</a>
                </div>
              </div>
            </div>
            <?php
        }
        echo '</div>';
    } else {
        // No results found for the selected search criteria
        echo '<div class="alert alert-info">No listings match your search criteria.</div>';
    }

    // Close the statement
    mysqli_stmt_close($stmt);
} else {
    // Handle database errors
    echo '<div class="alert alert-danger">Database error (prepare failed).</div>';
}
?>

<?php include_once("footer.php") ?>