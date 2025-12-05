<?php
session_start();
require_once __DIR__ . '/database.php';

$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$reviewText = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
$errors = [];
$isSubmitted = false;

// Load auction context
$auctionId = isset($_GET['auctionId']) ? (int)$_GET['auctionId'] : (isset($_POST['auctionId']) ? (int)$_POST['auctionId'] : 0);
$itemName = '';
$itemImage = '';
$auctionEndDate = '';
$sellerName = '';
$sellerAvgRating = null;
$sellerId = 0;

if ($auctionId <= 0) {
  $errors[] = 'Missing auctionId parameter.';
}

if (empty($errors) && ($connection instanceof mysqli)) {
  // Fetch auction + item
  $stmt = $connection->prepare('SELECT a.endDate, a.sellerId, i.name AS itemName, i.photo AS itemImage FROM Auction a INNER JOIN Item i ON a.itemId = i.itemId WHERE a.auctionId = ? LIMIT 1');
  $stmt->bind_param('i', $auctionId);
  $stmt->execute();
  $stmt->bind_result($auctionEndDateRaw, $sellerId, $itemNameRaw, $itemImageRaw);
  if ($stmt->fetch()) {
    $auctionEndDate = $auctionEndDateRaw;
    $itemName = $itemNameRaw;
    $itemImage = $itemImageRaw;
  } else {
    $errors[] = 'Auction not found.';
  }
  $stmt->close();

  if (empty($errors)) {
    // Fetch seller name (username used as display name)
    $stmt = $connection->prepare('SELECT username FROM Seller WHERE sellerId = ? LIMIT 1');
    $stmt->bind_param('i', $sellerId);
    $stmt->execute();
    $stmt->bind_result($sellerUsername);
    if ($stmt->fetch()) {
      $sellerName = $sellerUsername;
    }
    $stmt->close();

    // Average rating for this seller
    $stmt = $connection->prepare('SELECT AVG(rate) FROM Review WHERE sellerId = ?');
    $stmt->bind_param('i', $sellerId);
    $stmt->execute();
    $stmt->bind_result($avgRate);
    $stmt->fetch();
    $sellerAvgRating = $avgRate !== null ? (float)$avgRate : null;
    $stmt->close();
  }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($rating < 1 || $rating > 5) {
    $errors[] = 'Please select a rating between 1-5.';
  }
  if ($reviewText === '') {
    $errors[] = 'Please enter your review.';
  }
  if (strlen($reviewText) > 500) {
    $errors[] = 'Review cannot exceed 500 characters.';
  }
  if (empty($errors)) {
    if (($connection instanceof mysqli) && $sellerId > 0 && $auctionId > 0) {
      $sql = 'INSERT INTO Review (auctionId, sellerId, comment, rate, date) VALUES (?,?,?,?, NOW())
              ON DUPLICATE KEY UPDATE comment=VALUES(comment), rate=VALUES(rate), date=VALUES(date), sellerId=VALUES(sellerId)';
      $stmt = $connection->prepare($sql);
      $stmt->bind_param('iisi', $auctionId, $sellerId, $reviewText, $rating);
      if ($stmt->execute()) {
        $isSubmitted = true;
      } else {
        $errors[] = 'Failed to save review.';
      }
      $stmt->close();
    } else {
      $errors[] = 'Database connection not available.';
    }
  }
}

// Helper: label text for rating
function ratingLabel($rating)
{
  switch ($rating) {
    case 1: return 'Poor';
    case 2: return 'Fair';
    case 3: return 'Good';
    case 4: return 'Very Good';
    case 5: return 'Excellent';
    default: return '';
  }
}

// Include the view
require_once __DIR__ . '/rate_seller.php';
?>
