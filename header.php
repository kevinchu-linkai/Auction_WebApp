<?php
//-----------------------------------------------------------
// Session Initialization
//-----------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = false;
}
if (!isset($_SESSION['account_type'])) {
    $_SESSION['account_type'] = 'buyer';
}

//-----------------------------------------------------------
// 1) Load database connection
//-----------------------------------------------------------
$connection = $GLOBALS['connection'] ?? ($connection ?? null);

if (!($connection instanceof mysqli)) {
    $dbPath = __DIR__ . '/database.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
        $connection = $GLOBALS['connection'] ?? ($connection ?? null);
    }
}

//-----------------------------------------------------------
// 2) Auction state auto-refresh (max once per second)
//-----------------------------------------------------------


if ($connection instanceof mysqli) {

    // Throttle: only refresh once every 1 second per visitor
    if (!isset($_SESSION['last_state_update']) || (time() - $_SESSION['last_state_update']) > 1) {

        // Optional: ensure MySQL timezone matches PHP timezone
        $connection->query("SET time_zone = '+00:00'");

        // Auto update auction states based on end date and highest bid vs reserve price
        $sql = "
        UPDATE Auction a
        LEFT JOIN (
            SELECT auctionId, MAX(bidAmount) as maxBid
            FROM Bid
            GROUP BY auctionId
        ) b ON a.auctionId = b.auctionId
        SET a.state = CASE
            WHEN NOW() >= a.endDate AND a.state != 'cancelled' AND (
                b.maxBid IS NOT NULL AND (b.maxBid >= COALESCE(a.reservePrice, 0))
            ) THEN 'finished'
            WHEN NOW() >= a.endDate AND a.state != 'cancelled' THEN 'expired'
            WHEN NOW() >= a.startDate AND NOW() < a.endDate AND a.state != 'cancelled' THEN 'ongoing'
            WHEN NOW() < a.startDate AND a.state != 'cancelled' THEN 'not-started'
            ELSE a.state
        END
        WHERE a.state != 'cancelled';
        ";

        $result = $connection->query($sql);

        // Intentionally do not output SQL debug messages to the page; keep operation best-effort.

        $_SESSION['last_state_update'] = time();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Bootstrap and FontAwesome CSS -->
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="css/custom.css">

  <title>Doodle Auctions</title>
</head>

<body>
<!-- Auction state update runs silently; debug output removed -->

<!-- Navbars -->
<nav class="navbar navbar-expand-lg navbar-light bg-light mx-2">
  <a class="navbar-brand" href="#">Doodle</a>
  <ul class="navbar-nav ml-auto">
    <li class="nav-item">

<?php
if ($_SESSION['logged_in'] === true) {
    echo '<a class="nav-link" href="logout.php">Logout</a>';
} else {
    echo '<a class="nav-link" href="login.php">Login</a>';
}
?>

    </li>
  </ul>
</nav>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <ul class="navbar-nav align-middle">
    <li class="nav-item mx-1">
      <a class="nav-link" href="browse.php">Browse</a>
    </li>

<?php if ($_SESSION['account_type'] === 'buyer'): ?>
    <li class="nav-item mx-1">
      <a class="nav-link" href="mybids.php">My Bids</a>
    </li>

    <li class="nav-item mx-1">
      <a class="nav-link" href="watchlist.php">Watchlist</a>
    </li>

    <li class="nav-item mx-1">
      <a class="nav-link" href="recommendations.php">Recommended</a>
    </li>
<?php endif; ?>

<?php if ($_SESSION['account_type'] === 'seller'): ?>
    <li class="nav-item mx-1">
      <a class="nav-link" href="mylistings.php">My Listings</a>
    </li>

    <li class="nav-item mx-1">
      <a class="nav-link" href="myreview.php">My Review</a>
    </li>

    <li class="nav-item ml-3">
      <a class="nav-link btn border-light" href="create_auction.php">+ Create auction</a>
    </li>
<?php endif; ?>

  </ul>
</nav>
