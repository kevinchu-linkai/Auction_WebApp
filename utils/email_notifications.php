<?php
// Include config file
$config_path = dirname(__DIR__) . '/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

class EmailNotifications {
    
    /**
     * Send an email using PHP's mail function
     */
    private function sendEmail($to, $subject, $body) {
        // Use Gmail SMTP if configured
        if (defined('SMTP_USE_GMAIL') && SMTP_USE_GMAIL === true) {
            require_once __DIR__ . '/gmail_mailer.php';
            $mailer = new GmailMailer();
            
            // Extract name from email
            $name = explode('@', $to)[0];
            
            return $mailer->sendEmail($to, $name, $subject, $body);
        }
        
        // Fallback to PHP mail()
        if (!defined('SMTP_FROM_EMAIL')) {
            error_log("Email configuration not found");
            return false;
        }
        
        $from_email = SMTP_FROM_EMAIL;
        $from_name = SMTP_FROM_NAME;
        
        $headers = "From: {$from_name} <{$from_email}>\r\n";
        $headers .= "Reply-To: {$from_email}\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        $result = mail($to, $subject, $body, $headers);
        
        if ($result) {
            error_log("Email sent to: {$to}");
        } else {
            error_log("Failed to send email to: {$to}");
        }
        
        return $result;
    }
    
    /**
     * Notify a buyer that they have been outbid
     */
    public function notifyOutbid($conn, $outbidBidId) {
        $sql = "SELECT 
                    buyer.email, 
                    buyer.username as buyerName, 
                    i.name as itemName, 
                    b.bidAmount as oldBid,
                    b.auctionId,
                    (SELECT MAX(bidAmount) 
                     FROM Bid 
                     WHERE auctionId = b.auctionId) as newBid
                FROM Bid b
                JOIN Auction a ON b.auctionId = a.auctionId
                JOIN Item i ON a.itemId = i.itemId
                JOIN Buyer buyer ON b.buyerId = buyer.buyerId
                WHERE b.bidId = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error in notifyOutbid: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param('i', $outbidBidId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Only send if they were actually outbid
            if ($row['oldBid'] < $row['newBid']) {
                $subject = "You've been outbid on {$row['itemName']}";
                
                $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost';
                
                $body = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <h2>Hello {$row['buyerName']},</h2>
                        <p>Unfortunately, you've been outbid on <strong>{$row['itemName']}</strong>.</p>
                        <p>Your bid: <strong>£{$row['oldBid']}</strong></p>
                        <p>Current highest bid: <strong>£{$row['newBid']}</strong></p>
                        <p><a href='{$site_url}/auction_Webapp/listing.php?id={$row['auctionId']}' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>Place a New Bid</a></p>
                        <p>Best regards,<br>Auction System Team</p>
                    </body>
                    </html>
                ";
                
                return $this->sendEmail($row['email'], $subject, $body);
            }
        }
        
        return false;
    }
    
    /**
     * Notify the winner (buyer) of an auction
     */
    public function notifyAuctionWinner($conn, $auctionId) {
        $sql = "SELECT 
                    buyer.email, 
                    buyer.username as buyerName, 
                    i.name as itemName,
                    MAX(b.bidAmount) as winningBid
                FROM Bid b
                JOIN Buyer buyer ON b.buyerId = buyer.buyerId
                JOIN Auction a ON b.auctionId = a.auctionId
                JOIN Item i ON a.itemId = i.itemId
                WHERE b.auctionId = ?
                GROUP BY b.buyerId, buyer.email, buyer.username, i.name
                ORDER BY MAX(b.bidAmount) DESC
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error in notifyAuctionWinner: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param('i', $auctionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $subject = "Congratulations! You won {$row['itemName']}";
            
            $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost';
            
            $body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Congratulations {$row['buyerName']}! 🎉</h2>
                    <p>You won the auction for <strong>{$row['itemName']}</strong>!</p>
                    <p>Your winning bid: <strong>£{$row['winningBid']}</strong></p>
                    <p>Please check your account for payment and delivery details.</p>
                    <p><a href='{$site_url}/auction_Webapp/mybids.php' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>View My Bids</a></p>
                    <p>Best regards,<br>Auction System Team</p>
                </body>
                </html>
            ";
            
            return $this->sendEmail($row['email'], $subject, $body);
        }
        
        return false;
    }
    
    /**
     * Notify seller that their item has been sold
     */
    public function notifySellerItemSold($conn, $auctionId) {
        $sql = "SELECT 
                    seller.email, 
                    seller.username as sellerName, 
                    i.name as itemName,
                    COALESCE(MAX(b.bidAmount), a.startingPrice) as soldPrice,
                    COUNT(b.bidId) as totalBids
                FROM Auction a
                JOIN Item i ON a.itemId = i.itemId
                JOIN Seller seller ON a.sellerId = seller.sellerId
                LEFT JOIN Bid b ON a.auctionId = b.auctionId
                WHERE a.auctionId = ?
                GROUP BY a.auctionId, seller.email, seller.username, i.name, a.startingPrice";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error in notifySellerItemSold: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param('i', $auctionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $subject = "Your item '{$row['itemName']}' has been sold!";
            
            $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost';
            
            $body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Great news {$row['sellerName']}! 🎉</h2>
                    <p>Your item <strong>{$row['itemName']}</strong> has been sold!</p>
                    <p>Final selling price: <strong>£{$row['soldPrice']}</strong></p>
                    <p>Total bids received: {$row['totalBids']}</p>
                    <p>The buyer will be in contact with you regarding payment and delivery.</p>
                    <p><a href='{$site_url}/auction_Webapp/mylistings.php' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>View My Listings</a></p>
                    <p>Best regards,<br>Auction System Team</p>
                </body>
                </html>
            ";
            
            return $this->sendEmail($row['email'], $subject, $body);
        }
        
        return false;
    }
    
    /**
     * Notify seller that their auction has expired
     */
    public function notifySellerAuctionExpired($conn, $auctionId) {
        $sql = "SELECT 
                    seller.email, 
                    seller.username as sellerName, 
                    i.name as itemName,
                    COUNT(b.bidId) as bidCount,
                    a.endDate
                FROM Auction a
                JOIN Item i ON a.itemId = i.itemId
                JOIN Seller seller ON a.sellerId = seller.sellerId
                LEFT JOIN Bid b ON a.auctionId = b.auctionId
                WHERE a.auctionId = ?
                GROUP BY a.auctionId, seller.email, seller.username, i.name, a.endDate";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error in notifySellerAuctionExpired: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param('i', $auctionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $subject = "Your auction for '{$row['itemName']}' has expired";
            
            $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost';
            
            $body = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Hello {$row['sellerName']},</h2>
                    <p>Your auction for <strong>{$row['itemName']}</strong> has ended.</p>
                    <p>Total bids received: <strong>{$row['bidCount']}</strong></p>
            ";
            
            if ($row['bidCount'] == 0) {
                $body .= "
                    <p>Unfortunately, no bids were received on this item.</p>
                    <p>Consider adjusting the starting price or description and relisting the item.</p>
                ";
            }
            
            $body .= "
                    <p><a href='{$site_url}/auction_Webapp/mylistings.php' style='background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>View My Listings</a></p>
                    <p>Best regards,<br>Auction System Team</p>
                </body>
                </html>
            ";
            
            return $this->sendEmail($row['email'], $subject, $body);
        }
        
        return false;
    }
}
?>