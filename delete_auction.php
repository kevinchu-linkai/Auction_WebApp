<?php
require_once 'database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Check user is logged in and is a seller
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['delete_error'] = 'Please log in to delete auctions.';
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'seller') {
    $_SESSION['delete_error'] = 'Only sellers can delete auctions.';
    header('Location: mylistings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auctionId'])) {
    $auctionId = intval($_POST['auctionId']);
    $sellerId = $_SESSION['user_id'];

    // Verify the auction belongs to this seller and get itemId
    $checkSql = "SELECT itemId, sellerId FROM Auction WHERE auctionId = ? LIMIT 1";
    $checkStmt = mysqli_prepare($connection, $checkSql);
    
    if ($checkStmt) {
        mysqli_stmt_bind_param($checkStmt, 'i', $auctionId);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_bind_result($checkStmt, $itemId, $ownerSellerId);
        mysqli_stmt_fetch($checkStmt);
        mysqli_stmt_close($checkStmt);

        if ($ownerSellerId != $sellerId) {
            $_SESSION['delete_error'] = 'You do not have permission to delete this auction.';
            header('Location: mylistings.php');
            exit;
        }

        // Delete bids first (foreign key constraint)
        $deleteBidsSql = "DELETE FROM Bid WHERE auctionId = ?";
        $deleteBidsStmt = mysqli_prepare($connection, $deleteBidsSql);
        if ($deleteBidsStmt) {
            mysqli_stmt_bind_param($deleteBidsStmt, 'i', $auctionId);
            mysqli_stmt_execute($deleteBidsStmt);
            mysqli_stmt_close($deleteBidsStmt);
        }

        // Delete the auction
        $deleteAuctionSql = "DELETE FROM Auction WHERE auctionId = ?";
        $deleteAuctionStmt = mysqli_prepare($connection, $deleteAuctionSql);
        
        if ($deleteAuctionStmt) {
            mysqli_stmt_bind_param($deleteAuctionStmt, 'i', $auctionId);
            if (mysqli_stmt_execute($deleteAuctionStmt)) {
                mysqli_stmt_close($deleteAuctionStmt);

                // Delete the associated item
                $deleteItemSql = "DELETE FROM Item WHERE itemId = ?";
                $deleteItemStmt = mysqli_prepare($connection, $deleteItemSql);
                
                if ($deleteItemStmt) {
                    mysqli_stmt_bind_param($deleteItemStmt, 'i', $itemId);
                    if (mysqli_stmt_execute($deleteItemStmt)) {
                        $_SESSION['delete_success'] = 'Auction and item deleted successfully.';
                    } else {
                        $_SESSION['delete_error'] = 'Auction deleted but failed to delete item: ' . mysqli_error($connection);
                    }
                    mysqli_stmt_close($deleteItemStmt);
                } else {
                    $_SESSION['delete_error'] = 'Auction deleted but failed to prepare item deletion.';
                }
            } else {
                $_SESSION['delete_error'] = 'Failed to delete auction: ' . mysqli_error($connection);
                mysqli_stmt_close($deleteAuctionStmt);
            }
        } else {
            $_SESSION['delete_error'] = 'Database error: Failed to prepare auction deletion.';
        }
    } else {
        $_SESSION['delete_error'] = 'Database error: Failed to verify auction ownership.';
    }
} else {
    $_SESSION['delete_error'] = 'Invalid request.';
}

header('Location: mylistings.php');
exit;
