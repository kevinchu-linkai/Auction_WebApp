<?php

// TODO: Extract $_POST variables, check they're OK, and attempt to make a bid.
// Notify user of success/failure and redirect/give navigation options.

include_once("header.php");

// Check user authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['account_type'] !== 'buyer') {
    die('<div class="container alert alert-danger mt-3">Only buyers can place bids. 
         <a href="index.php">Return to homepage</a></div>');
}

// Initialize variables
$auctionId = 0;
$itemName = '';
$startingPrice = 0;
$reservePrice = 0;
$currentPrice = 0;
$state = '';
$minBid = 0;
$error = '';
$success = false;

// Process form submission if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract and validate POST variables
    $auctionId = intval($_POST['auction_id'] ?? 0);
    $bidAmount = floatval($_POST['bid_amount'] ?? 0);
    $buyerId = intval($_SESSION['user_id']);
    
    // Check if POST variables are OK
    if ($auctionId <= 0 || $bidAmount <= 0) {
        $error = "Invalid bid data.";
    } else {
        // Attempt to make a bid
        $checkSql = "SELECT a.state, a.startingPrice, a.reservePrice,
                    (SELECT MAX(bidAmount) FROM Bid WHERE auctionId = a.auctionId) as currentMax,
                    i.name as itemName
                    FROM Auction a
                    JOIN Item i ON a.itemId = i.itemId
                    WHERE a.auctionId = ?";
        
        $stmt = mysqli_prepare($connection, $checkSql);
        mysqli_stmt_bind_param($stmt, 'i', $auctionId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $state, $startingPrice, $reservePrice, $currentMax, $itemName);
        
        if (!mysqli_stmt_fetch($stmt)) {
            $error = "Auction does not exist.";
        } else {
            // Auction status verification
            if ($state === 'finished' || $state === 'expired') {
                $error = "Auction has ended. Cannot place bid.";
            } 
            elseif ($state === 'cancelled') {
                $error = "Auction has been cancelled. Cannot place bid.";
            }
            elseif ($state === 'not-started') {
                $error = "Auction has not started yet. Cannot place bid.";
            }
            elseif ($state !== 'ongoing') {
                $error = "Auction status is invalid. Cannot place bid.";
            }
            // Bid verification rules
            elseif ($currentMax && $bidAmount <= $currentMax) {
                $error = "Bid must be higher than current highest bid of $" . number_format($currentMax, 2) . ".";
            }
            elseif (!$currentMax && $bidAmount < $startingPrice) {
                $error = "Bid must be at least the starting price of $" . number_format($startingPrice, 2) . ".";
            } else {
                // Attempt to place the bid
                $insertSql = "INSERT INTO Bid (auctionId, buyerId, bidAmount, bidTime) VALUES (?, ?, ?, NOW())";
                $stmt2 = mysqli_prepare($connection, $insertSql);
                mysqli_stmt_bind_param($stmt2, 'iid', $auctionId, $buyerId, $bidAmount);
                
                if (mysqli_stmt_execute($stmt2)) {
                    $success = true;
                    mysqli_stmt_close($stmt2);
                } else {
                    $error = "Bid failed due to database error.";
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// If bid was successful, redirect
if ($success) {
    header("Location: mybids.php?success=1&auction_id=" . $auctionId);
    exit;
}

// If it's a GET request or we need to show the form (after error)
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $error) {
    // Get auction ID from GET parameters (for initial page load)
    if (!$auctionId) {
        $auctionId = intval($_GET['auction_id'] ?? 0);
    }
    
    if ($auctionId <= 0) {
        die('<div class="container alert alert-danger mt-3">Invalid auction ID.</div>');
    }
    
    // Fetch auction details
    $sql = "SELECT a.auctionId, i.name as itemName, a.startingPrice, a.reservePrice, a.state,
                   (SELECT MAX(bidAmount) FROM Bid WHERE auctionId = a.auctionId) as currentPrice
            FROM Auction a
            JOIN Item i ON a.itemId = i.itemId
            WHERE a.auctionId = ?";
    
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $auctionId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $auctionId, $itemName, $startingPrice, $reservePrice, $state, $currentPrice);
    
    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        die('<div class="container alert alert-danger mt-3">Auction does not exist.</div>');
    }
    mysqli_stmt_close($stmt);
    
    // Calculation of the minimum bid displayed on the form
    if ($currentPrice) {
        $minBid = $currentPrice + 0.01;
    } else {
        $minBid = $startingPrice;
    }
    
    $displayPrice = $currentPrice ?: $startingPrice;
    
    // Check if auction can accept bids
    if ($state !== 'ongoing') {
        // Display auction information but disable bidding
        $bidDisabled = true;
        switch ($state) {
            case 'not-started':
                $statusMessage = "Auction has not started yet.";
                break;
            case 'finished':
                $statusMessage = "Auction has ended.";
                break;
            case 'cancelled':
                $statusMessage = "Auction has been cancelled.";
                break;
            case 'expired':
                $statusMessage = "Auction has expired.";
                break;
            default:
                $statusMessage = "Auction is not available for bidding.";
        }
    } else {
        $bidDisabled = false;
        $statusMessage = "Auction is ongoing.";
    }
}
?>

<div class="container">
    <h2 class="my-3">Place a Bid</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title"><?php echo htmlspecialchars($itemName); ?></h5>
            <p class="card-text">
                <strong>Current Price:</strong> $<?php echo number_format($displayPrice, 2); ?><br>
                <strong>Starting Price:</strong> $<?php echo number_format($startingPrice, 2); ?><br>
                <?php if ($reservePrice > 0): ?>
                <strong>Reserve Price:</strong> $<?php echo number_format($reservePrice, 2); ?><br>
                <?php endif; ?>
                <strong>Minimum Bid:</strong> 
                <?php 
                if ($currentPrice) {
                    echo "Higher than $" . number_format($currentPrice, 2);
                } else {
                    echo "At least $" . number_format($startingPrice, 2);
                }
                ?><br>
                <strong>Auction Status:</strong> <?php echo $state; ?><br>
                <strong>Status Message:</strong> <?php echo $statusMessage; ?>
            </p>
            
            <?php if ($bidDisabled): ?>
                <div class="alert alert-warning">
                    Bidding is not available for this auction. 
                    <a href="listing.php?item_id=<?php echo $auctionId; ?>" class="btn btn-secondary">View Auction Details</a>
                </div>
            <?php else: ?>
                <form action="place_bid.php" method="POST">
                    <input type="hidden" name="auction_id" value="<?php echo $auctionId; ?>">
                    
                    <div class="form-group">
                        <label for="bid_amount">Bid Amount:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" class="form-control" id="bid_amount" name="bid_amount" 
                                   min="<?php echo $minBid; ?>" step="0.01" 
                                   value="<?php 
                                       if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bid_amount'])) {
                                           echo htmlspecialchars($_POST['bid_amount']);
                                       } else {
                                           echo number_format($minBid, 2, '.', '');
                                       }
                                   ?>" 
                                   required>
                        </div>
                        <small class="form-text text-muted">
                            <?php 
                            if ($currentPrice) {
                                echo "Bid must be higher than current highest bid of $" . number_format($currentPrice, 2);
                            } else {
                                echo "Bid must be at least the starting price of $" . number_format($startingPrice, 2);
                            }
                            ?>
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Place Bid</button>
                    <a href="listing.php?item_id=<?php echo $auctionId; ?>" class="btn btn-secondary">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once("footer.php"); ?>