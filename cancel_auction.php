<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mylistings.php');
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['cancel_error'] = 'You must be signed in to cancel an auction.';
    header('Location: mylistings.php');
    exit;
}

if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'seller') {
    $_SESSION['cancel_error'] = 'Only sellers can cancel auctions.';
    header('Location: mylistings.php');
    exit;
}

$auctionId = intval($_POST['auctionId'] ?? 0);
if ($auctionId <= 0) {
    $_SESSION['cancel_error'] = 'Invalid auction id.';
    header('Location: mylistings.php');
    exit;
}

require_once 'database.php';

// Ensure the auction belongs to the logged-in seller
$sellerId = $_SESSION['user_id'];
$checkSql = 'SELECT state, itemId FROM Auction WHERE auctionId = ? AND sellerId = ? LIMIT 1';
$stmt = mysqli_prepare($connection, $checkSql);
if (!$stmt) {
    $_SESSION['cancel_error'] = 'Database error.';
    header('Location: mylistings.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $auctionId, $sellerId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) !== 1) {
    mysqli_stmt_close($stmt);
    $_SESSION['cancel_error'] = 'Auction not found or you do not have permission.';
    header('Location: mylistings.php');
    exit;
}

mysqli_stmt_bind_result($stmt, $state, $itemId);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($state === 'finished' || $state === 'cancelled') {
    $_SESSION['cancel_error'] = 'Auction already finished or cancelled.';
    header('Location: mylistings.php');
    exit;
}
// Update the Auction state to 'cancelled' (do not delete rows)
$upd = mysqli_prepare($connection, 'UPDATE Auction SET state = ? WHERE auctionId = ? AND sellerId = ?');
if (!$upd) {
    $_SESSION['cancel_error'] = 'Database error (prepare failed).';
    header('Location: mylistings.php');
    exit;
}
$newState = 'cancelled';
mysqli_stmt_bind_param($upd, 'sii', $newState, $auctionId, $sellerId);
$ok = mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

if ($ok) {
    $_SESSION['cancel_success'] = 'Auction cancelled successfully.';
} else {
    $_SESSION['cancel_error'] = 'Failed to cancel auction.';
}

header('Location: mylistings.php');
exit;
?>