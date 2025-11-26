<?php
/**
 * Place Bid - Handles bid submission
 * Validates bid amount and inserts into Bid table
 */
date_default_timezone_set('Europe/London');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'database.php';

// Check if user is logged in as buyer
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'buyer') {
    $_SESSION['error'] = 'Only buyers can place bids';
    header('Location: browse.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: browse.php');
    exit;
}

// Extract POST variables
$auctionId = isset($_POST['auction_id']) ? (int)$_POST['auction_id'] : 0;
$bidAmount = isset($_POST['bid_amount']) ? (int)$_POST['bid_amount'] : 0;
$buyerId = (int)$_SESSION['user_id'];

if ($auctionId <= 0 || $bidAmount <= 0) {
    $_SESSION['error'] = 'Invalid auction or bid amount';
    header('Location: listing.php?auctionId=' . $auctionId);
    exit;
}

// Get auction details
$stmt = $connection->prepare('SELECT startingPrice, endDate, state FROM Auction WHERE auctionId = ?');
$stmt->bind_param('i', $auctionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = 'Auction not found';
    $stmt->close();
    header('Location: browse.php');
    exit;
}

$auction = $result->fetch_assoc();
$stmt->close();

// Check if auction is ongoing
$now = new DateTime();
$endDate = new DateTime($auction['endDate']);

if ($auction['state'] !== 'ongoing' || $now >= $endDate) {
    $_SESSION['error'] = 'Bidding is not open for this auction';
    header('Location: listing.php?auctionId=' . $auctionId);
    exit;
}

// Get highest bid for this auction
$stmt = $connection->prepare('SELECT MAX(bidAmount) as highestBid FROM Bid WHERE auctionId = ?');
$stmt->bind_param('i', $auctionId);
$stmt->execute();
$result = $stmt->get_result();
$bidData = $result->fetch_assoc();
$stmt->close();

$highestBid = $bidData['highestBid'];
$startingPrice = (int)$auction['startingPrice'];

// Determine minimum required bid
if ($highestBid !== null) {
    // There are existing bids - new bid must be at least highestBid + 1
    $minimumBid = (int)$highestBid + 1;
    if ($bidAmount < $minimumBid) {
        $_SESSION['error'] = 'Bid must be at least $' . number_format($minimumBid, 2);
        header('Location: listing.php?auctionId=' . $auctionId);
        exit;
    }
} else {
    // No existing bids - new bid must be at least startingPrice + 1
    $minimumBid = $startingPrice + 1;
    if ($bidAmount < $minimumBid) {
        $_SESSION['error'] = 'Bid must be at least $' . number_format($minimumBid, 2);
        header('Location: listing.php?auctionId=' . $auctionId);
        exit;
    }
}

// Insert the bid
$stmt = $connection->prepare('INSERT INTO Bid (auctionId, buyerId, bidAmount, bidTime) VALUES (?, ?, ?, NOW())');

$invalidateCache = "DELETE FROM RecommendationCache WHERE buyerId = ?";
$invalidateStmt = mysqli_prepare($connection, $invalidateCache);
mysqli_stmt_bind_param($invalidateStmt, 'i', $buyerId);
mysqli_stmt_execute($invalidateStmt);
mysqli_stmt_close($invalidateStmt);

$stmt->bind_param('iii', $auctionId, $buyerId, $bidAmount);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Bid placed successfully!';
    $stmt->close();
    header('Location: listing.php?auctionId=' . $auctionId);
    exit;
} else {
    $_SESSION['error'] = 'Failed to place bid. Please try again.';
    $stmt->close();
    header('Location: listing.php?auctionId=' . $auctionId);
    exit;
}
?>