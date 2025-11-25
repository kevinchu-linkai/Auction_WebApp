<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">My listings</h2>

<?php
// Show flash messages if present
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['delete_success'])) {
  echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['delete_success']) . '</div>';
  unset($_SESSION['delete_success']);
}
if (!empty($_SESSION['delete_error'])) {
  echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['delete_error']) . '</div>';
  unset($_SESSION['delete_error']);
}
?>

<?php
  // This page is for showing a user the auction listings they've made.
  // It will be pretty similar to browse.php, except there is no search bar.
  // This can be started after browse.php is working with a database.
  // Feel free to extract out useful functions from browse.php and put them in
  // the shared "utilities.php" where they can be shared by multiple files.
  
  
  // Check user is logged in and is a seller
  if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-warning">Please <a href="login.php">sign in</a> to view your listings.</div>';
  } elseif (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'seller') {
    echo '<div class="alert alert-info">This page is for sellers only. Your account is not a seller.</div>';
  } else {
    $sellerId = $_SESSION['user_id'];

    require_once 'database.php';

    // Fetch auctions for this seller, join to Item for the item name
    $sql = "SELECT a.auctionId, a.itemId, i.name AS itemName, a.startDate, a.endDate, a.startingPrice, a.reservePrice, a.state
            FROM Auction a
            JOIN Item i ON a.itemId = i.itemId
            WHERE a.sellerId = ?
            ORDER BY a.startDate DESC";

    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'i', $sellerId);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_bind_result($stmt, $auctionId, $itemId, $itemName, $startDate, $endDate, $startingPrice, $reservePrice, $state);

      $hasAny = false;
      echo '<div class="row">';
      while (mysqli_stmt_fetch($stmt)) {
        $hasAny = true;
        // Simple card for each listing
        ?>
        <div class="col-md-6 mb-4">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title"><?php echo htmlspecialchars($itemName); ?></h5>
              <p class="card-text">Auction ID: <?php echo intval($auctionId); ?></p>
              <p class="card-text">Start: <?php echo htmlspecialchars($startDate); ?> — End: <?php echo htmlspecialchars($endDate); ?></p>
              <p class="card-text">Starting: $<?php echo htmlspecialchars($startingPrice); ?> <?php if ($reservePrice) echo '(Reserve: $'.htmlspecialchars($reservePrice).')'; ?></p>
              <p class="card-text">State: <?php echo htmlspecialchars($state); ?></p>
              <a href="listing.php?auctionId=<?php echo intval($auctionId); ?>&from=mylistings" class="btn btn-primary">View</a>
              <a href="edit_auction.php?edit=<?php echo intval($auctionId); ?>" class="btn btn-secondary ml-2">Edit</a>
              <form method="POST" action="delete_auction.php" class="d-inline-block ml-2" onsubmit="return confirm('Permanently delete this auction and item? This cannot be undone!');">
                <input type="hidden" name="auctionId" value="<?php echo intval($auctionId); ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
              </form>
            </div>
          </div>
        </div>
        <?php
      }
      echo '</div>';

      if (!$hasAny) {
        echo '<div class="alert alert-info">You have not created any listings yet.</div>';
      }

      mysqli_stmt_close($stmt);
    } else {
      echo '<div class="alert alert-danger">Database error (prepare failed).</div>';
    }
  }
  
?>

<?php include_once("footer.php")?>