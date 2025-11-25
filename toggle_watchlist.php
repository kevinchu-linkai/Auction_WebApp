<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

// Check if user is logged in and is a buyer
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit();
}

if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'buyer') {
    echo json_encode(['success' => false, 'message' => 'Only buyers can watch auctions']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$buyerId = $_SESSION['user_id'];
$auctionId = isset($_POST['auctionId']) ? intval($_POST['auctionId']) : 0;

if ($auctionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid auction ID']);
    exit();
}

// Check if already watching
$checkQuery = "SELECT * FROM AuctionWatch WHERE buyerId = ? AND auctionId = ?";
$checkStmt = mysqli_prepare($connection, $checkQuery);
mysqli_stmt_bind_param($checkStmt, 'ii', $buyerId, $auctionId);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$isWatching = mysqli_num_rows($checkResult) > 0;
mysqli_stmt_close($checkStmt);

if ($isWatching) {
    // Remove from watchlist
    $deleteQuery = "DELETE FROM AuctionWatch WHERE buyerId = ? AND auctionId = ?";
    $deleteStmt = mysqli_prepare($connection, $deleteQuery);
    mysqli_stmt_bind_param($deleteStmt, 'ii', $buyerId, $auctionId);
    
    if (mysqli_stmt_execute($deleteStmt)) {
        echo json_encode(['success' => true, 'watching' => false, 'message' => 'Removed from watchlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove from watchlist']);
    }
    mysqli_stmt_close($deleteStmt);
} else {
    // Add to watchlist
    $insertQuery = "INSERT INTO AuctionWatch (buyerId, auctionId, watchTime) VALUES (?, ?, NOW())";
    $insertStmt = mysqli_prepare($connection, $insertQuery);
    mysqli_stmt_bind_param($insertStmt, 'ii', $buyerId, $auctionId);
    
    if (mysqli_stmt_execute($insertStmt)) {
        echo json_encode(['success' => true, 'watching' => true, 'message' => 'Added to watchlist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to watchlist']);
    }
    mysqli_stmt_close($insertStmt);
}
?>