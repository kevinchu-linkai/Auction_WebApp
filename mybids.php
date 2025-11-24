<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">My bids</h2>

<?php
  // This page is for showing a user the auctions they've bid on.
  // It will be pretty similar to browse.php, except there is no search bar.
  // This can be started after browse.php is working with a database.
  // Feel free to extract out useful functions from browse.php and put them in
  // the shared "utilities.php" where they can be shared by multiple files.
  
  
  // Check whether the user is logged in
  if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-warning">Please <a href="login.php">sign in</a> to view your bids.</div>';
  } 
  // Verify whether the user is a buyer
  elseif (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'buyer') {
    echo '<div class="alert alert-info">This page is for buyers only. Your account is not a buyer.</div>';
  } else {
    $buyerId = $_SESSION['user_id'];

    require_once 'database.php';

    // Retrieve auction information for which the user has participated in bidding
    // Join the Bid, Auction, and Item tables to retrieve comprehensive information.
    $sql = "SELECT DISTINCT a.auctionId, a.itemId, i.name AS itemName, 
                   a.startDate, a.endDate, a.startingPrice, a.reservePrice, 
                   a.state, MAX(b.bidAmount) AS myMaxBid,
                   (SELECT MAX(bidAmount) FROM Bid WHERE auctionId = a.auctionId) AS currentMaxBid
            FROM Bid b
            JOIN Auction a ON b.auctionId = a.auctionId
            JOIN Item i ON a.itemId = i.itemId
            WHERE b.buyerId = ?
            GROUP BY a.auctionId, a.itemId, i.name, a.startDate, a.endDate, a.startingPrice, a.reservePrice, a.state
            ORDER BY a.endDate DESC";

    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'i', $buyerId);
      mysqli_stmt_execute($stmt);
      // Binding result variable
      mysqli_stmt_bind_result($stmt, $auctionId, $itemId, $itemName, $startDate, $endDate, $startingPrice, $reservePrice, $state, $myMaxBid, $currentMaxBid);
      
      $hasAny = false;
      echo '<div class="row">';
      
      // Iterate through the query results
      while (mysqli_stmt_fetch($stmt)) {
        $hasAny = true;
        
        // Confirm bidding status
        $bidStatus = '';
        if ($state === 'finished' || $state === 'expired') {
          if ($myMaxBid == $currentMaxBid) {
            $bidStatus = '<span class="badge badge-success">Won</span>';
          } else {
            $bidStatus = '<span class="badge badge-danger">Outbid</span>';
          }
        } else if ($state === 'ongoing') {
          if ($myMaxBid == $currentMaxBid) {
            $bidStatus = '<span class="badge badge-warning">Leading</span>';
          } else {
            $bidStatus = '<span class="badge badge-secondary">Outbid</span>';
          }
        }
        
        // Display a card for each bid
        ?>
        <div class="col-md-6 mb-4">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title"><?php echo htmlspecialchars($itemName); ?></h5>
              <p class="card-text">Auction ID: <?php echo intval($auctionId); ?></p>
              <p class="card-text">Your Max Bid: $<?php echo htmlspecialchars($myMaxBid); ?></p>
              <p class="card-text">Current Highest Bid: $<?php echo htmlspecialchars($currentMaxBid); ?></p>
              <p class="card-text">Status: <?php echo $bidStatus; ?></p>
              <p class="card-text">Ends: <?php echo htmlspecialchars($endDate); ?></p>
              <a href="listing.php?item_id=<?php echo intval($itemId); ?>" class="btn btn-primary">View Auction</a>
              <?php if ($state === 'ongoing' && $myMaxBid != $currentMaxBid): ?>
                <a href="bid.php?auction_id=<?php echo intval($auctionId); ?>" class="btn btn-warning">Bid Again</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
      }
      echo '</div>';

      // If there is no bidding record
      if (!$hasAny) {
        echo '<div class="alert alert-info">You have not placed any bids yet.</div>';
      }

      mysqli_stmt_close($stmt);
    } else {
      echo '<div class="alert alert-danger">Database error (prepare failed).</div>';
    }
  }

?>

<?php include_once("footer.php")?>