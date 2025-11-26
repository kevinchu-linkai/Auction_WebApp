<?php
//-----------------------------------------------------------
// Session Initialization
//-----------------------------------------------------------
date_default_timezone_set('Europe/London');
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

  <title>Monopoly</title>

  <style>
  .navbar-light.bg-light {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .navbar-light .navbar-brand {
      color: #667eea !important;
      font-weight: bold;
      font-size: 1.5rem;
  }

  .navbar-light .navbar-brand:hover {
      color: #764ba2 !important;
  }

  .navbar-light .navbar-nav .nav-link {
      color: #667eea !important;
      font-weight: 500;
      padding: 8px 16px;
      border-radius: 20px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      z-index: 1;
  }

  .navbar-light .navbar-nav .nav-link::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: left 0.4s ease;
      z-index: -1;
      border-radius: 20px;
  }

  .navbar-light .navbar-nav .nav-link:hover {
      color: #ffffff !important;
  }

  .navbar-light .navbar-nav .nav-link:hover::before {
      left: 0;
  }

  .navbar-light .navbar-nav .nav-link:hover::after {
      display: none; /* Remove underline effect */
  }

  /* Active nav link styles */
  .navbar-nav .nav-link.active {
      color: #ffffff !important;
      background-color: rgba(255, 255, 255, 0.2);
      border-radius: 8px;
  }

  .navbar-nav .nav-link.active::after {
      width: 0;
      /* or display: none; */
  }

  /* For Create Auction button when active */
  .navbar-nav .nav-link.btn.border-light.active {
      background-color: rgba(255, 255, 255, 0.3);
      box-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
  }

  /* Navigation bar styling to match browse page */
  .navbar-dark.bg-dark {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }

  /* Nav link hover styles */
  .navbar-nav .nav-link {
      position: relative;
      transition: all 0.3s ease;
      color: rgba(255, 255, 255, 0.9) !important;
  }

  .navbar-nav .nav-link:hover {
      color: #ffffff !important;
      transform: translateY(-2px);
  }

  .navbar-nav .nav-link::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: 0;
      left: 50%;
      background-color: #ffffff;
      transition: all 0.3s ease;
      transform: translateX(-50%);
  }

  .navbar-nav .nav-link:hover::after {
      width: 80%;
  }

  /* Special styling for Create Auction button */
  .navbar-nav .nav-link.btn.border-light {
      background-color: rgba(255, 255, 255, 0.1);
      transition: all 0.3s ease;
      border: 2px solid rgba(255, 255, 255, 0.5) !important;
  }

  .navbar-nav .nav-link.btn.border-light:hover {
      background-color: rgba(255, 255, 255, 0.25);
      box-shadow: 0 0 20px rgba(255, 255, 255, 0.6);
      border-color: #ffffff !important;
      transform: translateY(-2px) scale(1.05);
  }

  .navbar-nav .nav-link.btn.border-light:hover::after {
      display: none;
  }
  </style>

</head>

<body>
<!-- Auction state update runs silently; debug output removed -->

<!-- Navbars -->
 <?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-light bg-light">
  <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true): ?>
    <a class="navbar-brand" href="browse.php">Monopoly</a>
  <?php else: ?>
    <a class="navbar-brand" href="index.php">Monopoly</a>
  <?php endif; ?>
  
  <ul class="navbar-nav ml-auto flex-row">
    <li class="nav-item">
      <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true): ?>
        <a class="nav-link" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="nav-link" href="login.php">Login</a>
      <?php endif; ?>
    </li>
  </ul>
</nav>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <ul class="navbar-nav align-middle">
    <li class="nav-item mx-1">
      <a class="nav-link <?php echo ($current_page == 'browse.php') ? 'active' : ''; ?>" href="browse.php">Browse</a>
    </li>
<?php if ($_SESSION['account_type'] === 'buyer'): ?>
    <li class="nav-item mx-1">
      <a class="nav-link <?php echo ($current_page == 'mybids.php') ? 'active' : ''; ?>" href="mybids.php">My Bids</a>
    </li>
    <li class="nav-item mx-1">
      <a class="nav-link <?php echo ($current_page == 'watchlist.php') ? 'active' : ''; ?>" href="watchlist.php">Watchlist</a>
    </li>
    <li class="nav-item mx-1">
      <a class="nav-link <?php echo ($current_page == 'recommendations.php') ? 'active' : ''; ?>" href="recommendations.php">Recommended</a>
    </li>
<?php endif; ?>
<?php if ($_SESSION['account_type'] === 'seller'): ?>
    <li class="nav-item mx-1">
      <a class="nav-link <?php echo ($current_page == 'mylistings.php') ? 'active' : ''; ?>" href="mylistings.php">My Listings</a>
    </li>
    <li class="nav-item mx-1">
      <a class="nav-link <?php echo ($current_page == 'myreview.php') ? 'active' : ''; ?>" href="myreview.php">My Review</a>
    </li>
    <li class="nav-item ml-3">
      <a class="nav-link btn border-light <?php echo ($current_page == 'create_auction.php') ? 'active' : ''; ?>" href="create_auction.php">+ Create auction</a>
    </li>
<?php endif; ?>
  </ul>
</nav>