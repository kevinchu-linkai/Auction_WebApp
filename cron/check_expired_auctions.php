<?php
/**
 * Cron job to send notifications for recently finished/expired auctions
 * This should run every 1-5 minutes via crontab
 * 
 * Business Logic:
 * - States are managed elsewhere 
 * - This script ONLY sends emails for finished/expired auctions
 * - Uses notificationSent flag in Auction table to prevent duplicates
 * 
 * 1. Find auctions with state = 'finished' or 'expired' where notificationSent = 0
 * 2. If state = 'finished' (has bids):
 *    - Send email to winning buyer
 *    - Send email to seller (item sold)
 * 3. If state = 'expired':
 *    - Send email to seller 
 * 4. Set notificationSent = 1 after successful email delivery
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/utils/email_notifications.php';

$emailer = new EmailNotifications();

echo "[" . date('Y-m-d H:i:s') . "] Starting email notification check...\n\n";

$totalProcessed = 0;
$successCount = 0;
$failCount = 0;

// ═══════════════════════════════════════════════════════════════
// STEP 1: Find FINISHED auctions that need notifications
// ═══════════════════════════════════════════════════════════════

echo "[FINISHED AUCTIONS] Checking for auctions with winners...\n";

$finishedSql = "SELECT 
                    a.auctionId, 
                    a.sellerId,
                    i.name as itemName,
                    a.endDate,
                    COUNT(b.bidId) as bidCount,
                    MAX(b.bidAmount) as highestBid
                FROM Auction a
                JOIN Item i ON a.itemId = i.itemId
                LEFT JOIN Bid b ON a.auctionId = b.auctionId
                WHERE a.state = 'finished'
                AND a.notificationSent = 0
                GROUP BY a.auctionId
                HAVING bidCount > 0
                ORDER BY a.endDate ASC";

$finishedResult = $conn->query($finishedSql);

if (!$finishedResult) {
    echo "ERROR querying finished auctions: " . $conn->error . "\n";
} else {
    $finishedCount = $finishedResult->num_rows;
    echo "Found $finishedCount finished auction(s) needing notifications.\n\n";
    
    while ($row = $finishedResult->fetch_assoc()) {
        $auctionId = $row['auctionId'];
        $itemName = $row['itemName'];
        $bidCount = $row['bidCount'];
        $highestBid = $row['highestBid'];
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Auction #$auctionId: '$itemName' (FINISHED)\n";
        echo "  Bids: $bidCount | Highest Bid: £$highestBid\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        $emailSuccess = true;
        
        // Send winner notification
        echo "[1/2] Sending winner notification...\n";
        if ($emailer->notifyAuctionWinner($conn, $auctionId)) {
            echo "      ✓ Winner notified\n";
        } else {
            echo "      ✗ FAILED to notify winner\n";
            $emailSuccess = false;
        }
        
        // Send seller notification (item sold)
        echo "[2/2] Sending seller notification (item sold)...\n";
        if ($emailer->notifySellerItemSold($conn, $auctionId)) {
            echo "      ✓ Seller notified\n";
        } else {
            echo "      ✗ FAILED to notify seller\n";
            $emailSuccess = false;
        }
        
        // Mark as notified if both emails sent successfully
        if ($emailSuccess) {
            $updateSql = "UPDATE Auction SET notificationSent = 1 WHERE auctionId = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param('i', $auctionId);
                if ($updateStmt->execute()) {
                    echo "      ✓ Marked as notified\n";
                    $successCount++;
                } else {
                    echo "      ✗ FAILED to mark as notified\n";
                    $failCount++;
                }
                $updateStmt->close();
            }
            echo "\n✓✓✓ Auction #$auctionId processed successfully\n\n";
        } else {
            $failCount++;
            echo "\n✗✗✗ Auction #$auctionId had errors (will retry next run)\n\n";
        }
        
        $totalProcessed++;
    }
}

// ═══════════════════════════════════════════════════════════════
// STEP 2: Find EXPIRED auctions that need notifications
// ═══════════════════════════════════════════════════════════════

echo "\n[EXPIRED AUCTIONS] Checking for auctions expired...\n";

$expiredSql = "SELECT 
                    a.auctionId, 
                    a.sellerId,
                    i.name as itemName,
                    a.endDate,
                    COUNT(b.bidId) as bidCount
                FROM Auction a
                JOIN Item i ON a.itemId = i.itemId
                LEFT JOIN Bid b ON a.auctionId = b.auctionId
                WHERE a.state = 'expired'
                AND a.notificationSent = 0
                GROUP BY a.auctionId
                ORDER BY a.endDate ASC";

$expiredResult = $conn->query($expiredSql);

if (!$expiredResult) {
    echo "ERROR querying expired auctions: " . $conn->error . "\n";
} else {
    $expiredCount = $expiredResult->num_rows;
    echo "Found $expiredCount expired auction(s) needing notifications.\n\n";
    
    while ($row = $expiredResult->fetch_assoc()) {
        $auctionId = $row['auctionId'];
        $itemName = $row['itemName'];
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Auction #$auctionId: '$itemName' (EXPIRED)\n";
        echo "  No bids received\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        $emailSuccess = true;
        
        // Send seller notification (no bids)
        echo "[1/1] Sending seller notification (no bids)...\n";
        if ($emailer->notifySellerAuctionExpired($conn, $auctionId)) {
            echo "      ✓ Seller notified\n";
        } else {
            echo "      ✗ FAILED to notify seller\n";
            $emailSuccess = false;
        }
        
        // Mark as notified if email sent successfully
        if ($emailSuccess) {
            $updateSql = "UPDATE Auction SET notificationSent = 1 WHERE auctionId = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param('i', $auctionId);
                if ($updateStmt->execute()) {
                    echo "      ✓ Marked as notified\n";
                    $successCount++;
                } else {
                    echo "      ✗ FAILED to mark as notified\n";
                    $failCount++;
                }
                $updateStmt->close();
            }
            echo "\n✓✓✓ Auction #$auctionId processed successfully\n\n";
        } else {
            $failCount++;
            echo "\n✗✗✗ Auction #$auctionId had errors (will retry next run)\n\n";
        }
        
        $totalProcessed++;
    }
}

// ═══════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total Processed:  $totalProcessed\n";
echo "Successful:       $successCount ✓\n";
echo "Failed:           $failCount ✗\n";
echo "[" . date('Y-m-d H:i:s') . "] Completed.\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$conn->close();
exit($failCount > 0 ? 1 : 0);
?>