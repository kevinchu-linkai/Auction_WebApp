<?php
// Review Seller page — fetch auction, item, seller, and avg rating from DB
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Write a Review</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
  <style>
    .rating { display: inline-flex; flex-direction: row-reverse; gap: 0.5rem; }
    .rating input { display: none; }
    .rating label { font-size: 2rem; line-height: 1; color: #D1D5DB; cursor: pointer; }
    .rating input:checked ~ label { color: #F59E0B; }
    .rating label:hover,
    .rating label:hover ~ label { color: #FBBF24; }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen">

<?php if ($isSubmitted): ?>
  <!-- Success Screen -->
  <div class="min-h-screen bg-gradient-to-br from-green-50 to-emerald-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
        <span class="text-green-600 text-3xl">✅</span>
      </div>
      <h2 class="text-xl font-semibold mb-2">Review Submitted!</h2>
      <p class="text-gray-600 mb-6">
        Thank you for sharing your experience. Your review helps build trust in our community.
      </p>
      <p class="text-gray-500 mb-4">Redirecting to My Bids…</p>
      <a
        href="mybids.php"
        class="inline-block px-6 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors"
      >
        Back to My Bids
      </a>
      <script>
        setTimeout(function(){ window.location.href = 'mybids.php'; }, 1500);
      </script>
    </div>
  </div>
<?php else: ?>
  <!-- Review Form Screen -->
  <div class="min-h-screen py-12 px-4">
    <div class="max-w-3xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-2xl font-semibold mb-2">Write a Review</h1>
        <p class="text-gray-600">Share your experience with this seller</p>
      </div>

      <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Auction Item Info -->
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 p-6 text-white">
          <div class="flex items-start gap-4">
            <?php if ($itemImage): ?>
            <img
              src="<?= htmlspecialchars($itemImage) ?>"
              alt="<?= htmlspecialchars($itemName) ?>"
              class="w-24 h-24 rounded-lg object-cover border-2 border-white"
            >
            <?php else: ?>
            <div class="w-24 h-24 rounded-lg bg-gray-300 border-2 border-white flex items-center justify-center">
              <span class="text-gray-600">📦</span>
            </div>
            <?php endif; ?>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2 text-sm opacity-90">
                <span class="text-lg">📦</span>
                <span>Auction Item</span>
              </div>
              <h3 class="mb-1 text-lg font-semibold">
                <?= htmlspecialchars($itemName ?: 'Item') ?>
              </h3>
              <div class="flex items-center gap-2 text-sm opacity-90 flex-wrap">
                <span>Ends on <?= htmlspecialchars($auctionEndDate ?: 'N/A') ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Seller Info -->
        <div class="border-b border-gray-200 p-6">
          <div class="flex items-center gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-gray-500 text-lg">👤</span>
                <span class="text-sm text-gray-600">Seller</span>
              </div>
              <h4 class="text-gray-900 font-semibold mb-1">
                <?= htmlspecialchars($sellerName ?: 'Unknown') ?>
              </h4>
            </div>
            <div class="text-right">
              <div class="flex items-center gap-1 justify-end mb-1">
                <span class="text-amber-400 text-lg">★</span>
                <span class="text-gray-900 font-semibold">
                  <?= $sellerAvgRating !== null ? htmlspecialchars(number_format($sellerAvgRating, 1)) : 'N/A' ?>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Review Form -->
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="p-6 space-y-6">
          <input type="hidden" name="auctionId" value="<?= (int)$auctionId ?>">
          <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
              <ul class="list-disc list-inside text-sm">
                <?php foreach ($errors as $err): ?>
                  <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <!-- Rating -->
          <div>
            <label class="block mb-3 font-medium">
              Rate Your Experience <span class="text-red-500">*</span>
            </label>
            <div class="rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $rating === $i ? 'checked' : '' ?>>
                <label for="star<?= $i ?>">★</label>
              <?php endfor; ?>
            </div>
            <?php if ($rating > 0): ?>
              <p class="mt-2 text-sm text-gray-600">
                <?= ratingLabel($rating) ?>
              </p>
            <?php endif; ?>
          </div>

          <!-- Review Text -->
          <div>
            <label for="review-text" class="block mb-2 font-medium">
              Your Review <span class="text-red-500">*</span>
            </label>
            <textarea
              id="review-text"
              name="review_text"
              placeholder="Tell us about your experience with this seller. Was the item as described? How was the communication and shipping?"
              rows="6"
              maxlength="500"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition-all resize-none"
            ><?= htmlspecialchars($reviewText) ?></textarea>
            <p class="mt-2 text-sm text-gray-500">
              <span id="charCount"><?= strlen($reviewText) ?></span> / 500 characters
            </p>
          </div>

          <!-- Submit / Cancel -->
          <div class="flex gap-3 pt-4">
            <a href="mybids.php" class="flex-1 text-center px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
            <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Submit Review</button>
          </div>
        </form>
      </div>

      <!-- Footer Note -->
      <p class="text-center text-sm text-gray-600 mt-6">
        Your review will be visible to all users and cannot be edited after submission
      </p>
    </div>
  </div>
<?php endif; ?>

<script>
  (function() {
    const textarea = document.getElementById('review-text');
    const counter = document.getElementById('charCount');
    if (textarea && counter) {
      const update = () => { counter.textContent = textarea.value.length; };
      textarea.addEventListener('input', update);
      update();
    }
  })();
  </script>

</body>
</html>
