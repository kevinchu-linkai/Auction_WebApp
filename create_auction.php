<?php
include "database.php";
date_default_timezone_set('Europe/London');

// Ensure session for multi-step persistence
if (session_status() === PHP_SESSION_NONE) session_start();

// Step Handling
$currentStep = $_POST['currentStep'] ?? 1;

// Use session to persist form values across steps
$sessionKey = 'create_auction_form';
// Initialize session bucket if missing
if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) $_SESSION[$sessionKey] = [];

// Edit mode: if ?edit=<auctionId> provided, load auction+item data
$isEdit = false;
$editAuctionId = null;
if (isset($_GET['edit'])) {
  $maybe = intval($_GET['edit']);
  if ($maybe > 0) {
    require_once 'database.php';
    $sql = 'SELECT auctionId, sellerId, itemId, startingPrice, reservePrice, startDate, endDate, state FROM Auction WHERE auctionId = ? LIMIT 1';
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'i', $maybe);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_store_result($stmt);
      if (mysqli_stmt_num_rows($stmt) === 1) {
        mysqli_stmt_bind_result($stmt, $aId, $aSellerId, $aItemId, $aStarting, $aReserve, $aStartDate, $aEndDate, $aState);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        // Only allow the seller who owns it to edit
        $currentSeller = $_SESSION['user_id'] ?? null;
        if ($currentSeller && ($_SESSION['account_type'] ?? '') === 'seller' && $currentSeller == $aSellerId) {
          $isEdit = true;
          $editAuctionId = $aId;
          $editItemId = $aItemId;
          // persist edit mode into the session so POST submissions continue to act as edits
          $_SESSION[$sessionKey]['is_edit'] = true;
          $_SESSION[$sessionKey]['edit_auction_id'] = $editAuctionId;
          $_SESSION[$sessionKey]['edit_item_id'] = $editItemId;

          // Load item data
          $itm = mysqli_prepare($connection, 'SELECT name, `condition`, description, photo, photo_base64, categoryId FROM Item WHERE itemId = ? LIMIT 1');
          if ($itm) {
            mysqli_stmt_bind_param($itm, 'i', $aItemId);
            mysqli_stmt_execute($itm);
            mysqli_stmt_store_result($itm);
            if (mysqli_stmt_num_rows($itm) === 1) {
              mysqli_stmt_bind_result($itm, $iName, $iCondition, $iDesc, $iPhoto, $iPhotoBase64, $iCategoryId);
              mysqli_stmt_fetch($itm);
              mysqli_stmt_close($itm);

              // load category name
              $catName = '';
              if ($iCategoryId) {
                $cst = mysqli_prepare($connection, 'SELECT name FROM Category WHERE categoryId = ? LIMIT 1');
                if ($cst) {
                  mysqli_stmt_bind_param($cst, 'i', $iCategoryId);
                  mysqli_stmt_execute($cst);
                  mysqli_stmt_bind_result($cst, $catName);
                  mysqli_stmt_fetch($cst);
                  mysqli_stmt_close($cst);
                }
              }

              // Map DB values into the session form so existing UI code picks them up
              $_SESSION[$sessionKey]['title'] = $_SESSION[$sessionKey]['title'] ?? $iName;
              // map DB condition back to simple keys used in the form
              $condKey = strtolower(str_replace(' ', '-', $iCondition));
              $_SESSION[$sessionKey]['condition'] = $_SESSION[$sessionKey]['condition'] ?? $condKey;
              $_SESSION[$sessionKey]['description'] = $_SESSION[$sessionKey]['description'] ?? $iDesc;
              // try to map category name back to known frontend keys
              $categoryMap = [
                'electronics' => 'Electronics',
                'fashion'     => 'Fashion',
                'home'        => 'Home & Garden',
                'sports'      => 'Sports',
                'collectibles'=> 'Collectibles',
                'automotive'  => 'Automotive',
                'books'       => 'Books',
                'jewelry'     => 'Jewelry'
              ];
              $inv = array_flip($categoryMap);
              $key = $inv[$catName] ?? '';
              $_SESSION[$sessionKey]['category'] = $_SESSION[$sessionKey]['category'] ?? $key;

              $_SESSION[$sessionKey]['startingPrice'] = $_SESSION[$sessionKey]['startingPrice'] ?? $aStarting;
              $_SESSION[$sessionKey]['reservePrice'] = $_SESSION[$sessionKey]['reservePrice'] ?? $aReserve;
              $_SESSION[$sessionKey]['startDate'] = $_SESSION[$sessionKey]['startDate'] ?? date('Y-m-d\TH:i', strtotime($aStartDate));
              $_SESSION[$sessionKey]['endDate'] = $_SESSION[$sessionKey]['endDate'] ?? date('Y-m-d\TH:i', strtotime($aEndDate));

              // pick an existing photo path if available (prefer stored file path over base64)
              if (!empty($iPhoto)) {
                $_SESSION[$sessionKey]['temp_photo'] = $iPhoto;
              } elseif (!empty($iPhotoBase64)) {
                // create a data URL to preview base64 image in the UI (small convenience)
                $_SESSION[$sessionKey]['temp_photo'] = 'data:image/*;base64,' . $iPhotoBase64;
              }
            } else {
              mysqli_stmt_close($itm);
            }
          }
        } else {
          // not allowed to edit
          $_SESSION['edit_error'] = 'Auction not found or you do not have permission to edit it.';
          header('Location: mylistings.php');
          exit;
        }
      } else {
        mysqli_stmt_close($stmt);
      }
    }
  }
}
// Merge POST into session on any submission (so Back/Next preserves values)
$fields = ['title','description','category','condition','startingPrice','reservePrice','startDate','endDate'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // restore edit-mode flags from session (if editing)
  if (!empty($_SESSION[$sessionKey]['is_edit'])) {
    $isEdit = true;
    $editAuctionId = $_SESSION[$sessionKey]['edit_auction_id'] ?? $editAuctionId;
    $editItemId = $_SESSION[$sessionKey]['edit_item_id'] ?? $editItemId;
  }

  // Handle Cancel action: clear the multi-step form session and return to listings
  if (isset($_POST['cancel'])) {
    unset($_SESSION[$sessionKey]);
    header('Location: mylistings.php');
    exit;
  }

  foreach ($fields as $f) {
    if (isset($_POST[$f])) {
      $_SESSION[$sessionKey][$f] = $_POST[$f];
    }
  }
}

// Handle remove-photo action (user clicked Remove Photo on step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['removePhoto'])) {
  $old = $_SESSION[$sessionKey]['temp_photo'] ?? null;
  if ($old) {
    $full = __DIR__ . '/' . $old;
    if (is_file($full)) @unlink($full);
    unset($_SESSION[$sessionKey]['temp_photo']);
  }
  // stay on step 2 after removal
  $currentStep = 2;
}

// Handle immediate upload of image to a temporary location so it persists across steps
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imageInput']) && is_uploaded_file($_FILES['imageInput']['tmp_name'])) {
  $uploadsDir = __DIR__ . '/img/uploads';
  if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
  $tmp = $_FILES['imageInput']['tmp_name'];
  $orig = basename($_FILES['imageInput']['name']);
  $ext = pathinfo($orig, PATHINFO_EXTENSION);
  $filename = 'temp_' . uniqid('item_') . ($ext ? ".{$ext}" : '');
  $dest = $uploadsDir . '/' . $filename;
  if (move_uploaded_file($tmp, $dest)) {
    // store relative path in session so final submit can use it
    $_SESSION[$sessionKey]['temp_photo'] = 'img/uploads/' . $filename;
  } else {
    error_log('create_auction: failed to move uploaded temp file. tmp=' . $tmp . ' dest=' . $dest);
  }
}

// Local variables read from session (fallback empty/defaults)
$title = $_SESSION[$sessionKey]['title'] ?? '';
$description = $_SESSION[$sessionKey]['description'] ?? '';
$category = $_SESSION[$sessionKey]['category'] ?? '';
$condition = $_SESSION[$sessionKey]['condition'] ?? 'new';
$startingPrice = $_SESSION[$sessionKey]['startingPrice'] ?? '';
$reservePrice = $_SESSION[$sessionKey]['reservePrice'] ?? '';
$startDate = $_SESSION[$sessionKey]['startDate'] ?? '';
$endDate = $_SESSION[$sessionKey]['endDate'] ?? '';

// Existing uploaded temp photo (used to render preview and hide Add area)
$existingPhoto = $_SESSION[$sessionKey]['temp_photo'] ?? null;

// Image previews handled by JS only

// Handle final submission (only when Launch Auction is clicked)
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['launch'])) {
  require_once 'database.php';
  if (session_status() === PHP_SESSION_NONE) session_start();

  // Basic seller check
  $sellerId = $_SESSION['user_id'] ?? null;
  if (!$sellerId || ($_SESSION['account_type'] ?? '') !== 'seller') {
    $error = 'You must be logged in as a seller to create an auction.';
  } else {
    // Trim and validate required fields
    $title = trim($title);
    $description = trim($description);
    $category = trim($category);
    $startingPrice = trim($startingPrice);
    $startDate = trim($startDate);
    $endDate = trim($endDate);

    if ($title === '' || $description === '' || $category === '' || $startingPrice === '' || $startDate === '' || $endDate === '') {
      $error = 'Please fill in all required fields.';
    } else {
      // Ensure category exists (or create)
      $categoryId = null;
      // Map frontend category keys to the exact names stored in the Category table
      $categoryMap = [
        'electronics' => 'Electronics',
        'fashion'     => 'Fashion',
        'home'        => 'Home & Garden',
        'sports'      => 'Sports',
        'collectibles'=> 'Collectibles',
        'automotive'  => 'Automotive',
        'books'       => 'Books',
        'jewelry'     => 'Jewelry'
      ];
      $categoryName = $categoryMap[$category] ?? $category;

      $stmt = mysqli_prepare($connection, "SELECT categoryId FROM Category WHERE name = ? LIMIT 1");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $categoryName);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) === 1) {
          mysqli_stmt_bind_result($stmt, $categoryId);
          mysqli_stmt_fetch($stmt);
          mysqli_stmt_close($stmt);
        } else {
          mysqli_stmt_close($stmt);
          // Try to insert the category. Use INSERT IGNORE to avoid duplicate-key
          // exceptions on races; if no row was inserted, SELECT the existing id.
          $insSql = "INSERT IGNORE INTO Category (name) VALUES (?)";
          $ins = mysqli_prepare($connection, $insSql);
          if ($ins) {
            mysqli_stmt_bind_param($ins, 's', $categoryName);
            if (!mysqli_stmt_execute($ins)) {
              $error = 'Database error inserting category: ' . mysqli_error($connection);
              mysqli_stmt_close($ins);
            } else {
              $insertId = mysqli_insert_id($connection);
              mysqli_stmt_close($ins);
              if ($insertId && $insertId > 0) {
                $categoryId = $insertId;
              } else {
                // Another process likely inserted the same name; select the id.
                $sel = mysqli_prepare($connection, "SELECT categoryId FROM Category WHERE name = ? LIMIT 1");
                if ($sel) {
                  mysqli_stmt_bind_param($sel, 's', $categoryName);
                  if (mysqli_stmt_execute($sel)) {
                    mysqli_stmt_bind_result($sel, $categoryId);
                    mysqli_stmt_fetch($sel);
                  } else {
                    $error = 'Database error fetching existing category after insert-ignore.';
                  }
                  mysqli_stmt_close($sel);
                } else {
                  $error = 'Database error preparing category select after insert-ignore.';
                }
              }
            }
          } else {
            $error = 'Database error preparing category insert.';
          }
        }
      } else {
        $error = 'Database error preparing category lookup.';
      }
    }

    // If still OK, use any previously uploaded temp image (or none)
    if ($error === '') {
      $photoPath = $_SESSION[$sessionKey]['temp_photo'] ?? null;
      $photoBase64 = null;

      // Enforce that a photo exists (either temp path from earlier step or a newly uploaded file)
      if (empty($photoPath) && !(isset($_FILES['imageInput']) && is_uploaded_file($_FILES['imageInput']['tmp_name']))) {
        $error = 'Please upload at least one photo for your listing.';
      }

      // If we have a temp photo path but file doesn't exist (permissions),
      // attempt to read the original uploaded tmp file from PHP upload data
      // as a fallback and store base64 in DB.
      if ($photoPath && !file_exists(__DIR__ . '/' . $photoPath)) {
        // try to find the tmp upload (may not exist at this point)
        if (isset($_FILES['imageInput']) && is_uploaded_file($_FILES['imageInput']['tmp_name'])) {
          $tmp = $_FILES['imageInput']['tmp_name'];
          $contents = @file_get_contents($tmp);
          if ($contents !== false) {
            $photoBase64 = base64_encode($contents);
            // remove tmp file
            @unlink($tmp);
            // clear the temp photo path since we store blob instead
            $photoPath = null;
          }
        }
      }

      // Map form condition to DB ENUM values
      $dbCondition = $condition;
      switch ($condition) {
        case 'new': $dbCondition = 'New'; break;
        case 'like-new': $dbCondition = 'Like new'; break;
        case 'excellent': $dbCondition = 'Excellent'; break;
        case 'good': $dbCondition = 'Good'; break;
        case 'fair': $dbCondition = 'Fair'; break;
        default: $dbCondition = ucwords(str_replace('-', ' ', $condition)); break;
      }

      // Insert or Update Item
      if (!empty($isEdit) && !empty($editItemId) && !empty($editAuctionId)) {
        // Update existing Item
        // ensure photo_base64 column exists if needed
        if ($photoBase64 !== null) {
          $colCheck = mysqli_query($connection, "SHOW COLUMNS FROM Item LIKE 'photo_base64'");
          if (!$colCheck || mysqli_num_rows($colCheck) === 0) {
            mysqli_query($connection, "ALTER TABLE Item ADD COLUMN photo_base64 MEDIUMTEXT NULL");
          }
          $updItem = mysqli_prepare($connection, "UPDATE Item SET name = ?, `condition` = ?, description = ?, photo_base64 = ?, photo = NULL, categoryId = ? WHERE itemId = ?");
          if ($updItem) {
            mysqli_stmt_bind_param($updItem, 'ssssii', $title, $dbCondition, $description, $photoBase64, $categoryId, $editItemId);
            if (!mysqli_stmt_execute($updItem)) {
              $error = 'Database error updating item (blob): ' . mysqli_error($connection);
            }
            mysqli_stmt_close($updItem);
          } else {
            $error = 'Database error preparing item update (blob).';
          }
        } else {
          $updItem = mysqli_prepare($connection, "UPDATE Item SET name = ?, `condition` = ?, description = ?, photo = ?, categoryId = ? WHERE itemId = ?");
          if ($updItem) {
            mysqli_stmt_bind_param($updItem, 'ssssii', $title, $dbCondition, $description, $photoPath, $categoryId, $editItemId);
            if (!mysqli_stmt_execute($updItem)) {
              $error = 'Database error updating item: ' . mysqli_error($connection);
            }
            mysqli_stmt_close($updItem);
          } else {
            $error = 'Database error preparing item update.';
          }
        }
        $itemId = $editItemId;
      } else {
        // Insert new Item
        if ($photoBase64 !== null) {
          // ensure column exists: photo_base64 (MEDIUMTEXT)
          $colCheck = mysqli_query($connection, "SHOW COLUMNS FROM Item LIKE 'photo_base64'");
          if (!$colCheck || mysqli_num_rows($colCheck) === 0) {
            mysqli_query($connection, "ALTER TABLE Item ADD COLUMN photo_base64 MEDIUMTEXT NULL");
          }

          $insItem = mysqli_prepare($connection, "INSERT INTO Item (name, `condition`, description, photo_base64, categoryId) VALUES (?, ?, ?, ?, ?)");
          if ($insItem) {
            mysqli_stmt_bind_param($insItem, 'ssssi', $title, $dbCondition, $description, $photoBase64, $categoryId);
            if (!mysqli_stmt_execute($insItem)) {
              $error = 'Database error inserting item (blob): ' . mysqli_error($connection);
            } else {
              $itemId = mysqli_insert_id($connection);
            }
            mysqli_stmt_close($insItem);
          } else {
            $error = 'Database error preparing item insert (blob).';
          }
        } else {
          $insItem = mysqli_prepare($connection, "INSERT INTO Item (name, `condition`, description, photo, categoryId) VALUES (?, ?, ?, ?, ?)");
          if ($insItem) {
            mysqli_stmt_bind_param($insItem, 'ssssi', $title, $dbCondition, $description, $photoPath, $categoryId);
            if (!mysqli_stmt_execute($insItem)) {
              $error = 'Database error inserting item: ' . mysqli_error($connection);
            } else {
              $itemId = mysqli_insert_id($connection);
            }
            mysqli_stmt_close($insItem);
          } else {
            $error = 'Database error preparing item insert.';
          }
        }
      }
    }
    if ($error === '') {
      // Normalize dates
      $sd = date('Y-m-d H:i:s', strtotime($startDate));
      $ed = date('Y-m-d H:i:s', strtotime($endDate));
      $now = date('Y-m-d H:i:s');

      if ($sd > $now) $state = 'not-started';
      elseif ($now >= $sd && $now <= $ed) $state = 'ongoing';
      else $state = 'expired';

      // Insert or Update Auction
      if (!empty($isEdit) && !empty($editAuctionId)) {
        $reserveParam = ($reservePrice === '' ? null : $reservePrice);
        $updAuction = mysqli_prepare($connection, "UPDATE Auction SET startingPrice = ?, reservePrice = ?, startDate = ?, endDate = ?, state = ? WHERE auctionId = ?");
        if ($updAuction) {
          mysqli_stmt_bind_param($updAuction, 'ddsssi', $startingPrice, $reserveParam, $sd, $ed, $state, $editAuctionId);
          if (!mysqli_stmt_execute($updAuction)) {
            $error = 'Database error updating auction: ' . mysqli_error($connection);
          } else {
            $success = 'Auction updated successfully. Redirecting to your listings...';
            mysqli_stmt_close($updAuction);
            unset($_SESSION[$sessionKey]);
            $shouldRedirect = true;
            $redirectUrl = 'mylistings.php?updated=1';
          }
        } else {
          if ($error === '') $error = 'Database error preparing auction update.';
        }
      } else {
        $insAuction = mysqli_prepare($connection, "INSERT INTO Auction (sellerId, itemId, startingPrice, reservePrice, startDate, endDate, state) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($insAuction) {
          // reserve may be NULL
          $reserveParam = ($reservePrice === '' ? null : $reservePrice);
          mysqli_stmt_bind_param($insAuction, 'iiddsss', $sellerId, $itemId, $startingPrice, $reserveParam, $sd, $ed, $state);
          if (!mysqli_stmt_execute($insAuction)) {
            $error = 'Database error inserting auction: ' . mysqli_error($connection);
          } else {
            // Show on-page success message and then auto-redirect after short delay
            $success = 'Auction created successfully. Redirecting to listings...';
            mysqli_stmt_close($insAuction);
            // clear multi-step session data on successful creation
            unset($_SESSION[$sessionKey]);
            $shouldRedirect = true;
            $redirectUrl = 'mylistings.php?created=1';
          }
        } else {
          if ($error === '') $error = 'Database error preparing auction insert.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Create Auction</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-purple-50 via-white to-blue-50 p-6">

<div class="max-w-5xl mx-auto">
  <div class="text-center mb-12">
    <h1 class="text-gray-900 mb-3 text-3xl font-semibold">Create Your Auction</h1>
    <p class="text-gray-600">Fill in the details to list your item</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="mb-6 p-3 rounded border border-red-200 bg-red-50 text-red-700">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
    <div class="mb-6 p-3 rounded border border-green-200 bg-green-50 text-green-700">
      <?php echo htmlspecialchars($success); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($shouldRedirect) && !empty($redirectUrl)): ?>
    <script>
      // Auto-redirect after a short delay so user sees the success message
      setTimeout(function () {
        window.location.href = '<?php echo htmlspecialchars($redirectUrl); ?>';
      }, 2000);
    </script>
  <?php endif; ?>

  <!-- Progress Steps -->
  <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 mb-8">
    <div class="flex items-center justify-between max-w-2xl mx-auto">
      <?php
      $steps = [
        1 => "Basic Info",
        2 => "Images",
        3 => "Pricing & Timing"
      ];
      foreach ($steps as $stepNumber => $label):
        $isActive = ($currentStep == $stepNumber);
        $isCompleted = ($currentStep > $stepNumber);
      ?>
        <div class="flex items-center flex-1">
          <div class="flex flex-col items-center flex-1">
            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-all
              <?php echo $isActive ? 'bg-gradient-to-br from-purple-500 to-blue-500 text-white shadow-lg scale-110' :
                   ($isCompleted ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500'); ?>">
              <?php echo $stepNumber; ?>
            </div>
            <span class="mt-2 text-sm <?php echo $isActive ? 'text-purple-600' : ($isCompleted ? 'text-green-600' : 'text-gray-500'); ?>">
              <?php echo $label; ?>
            </span>
          </div>

          <?php if ($stepNumber < 3): ?>
          <div class="h-1 flex-1 mx-4 rounded-full <?php echo $isCompleted ? 'bg-green-500' : 'bg-gray-200'; ?>"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Multi-step Form -->
  <form id="createAuctionForm" method="POST" enctype="multipart/form-data" class="space-y-6">

    <input type="hidden" name="currentStep" id="currentStep" value="<?php echo $currentStep; ?>">
    <!-- Persist values across steps -->
    <input type="hidden" name="title" value="<?php echo htmlspecialchars($title); ?>">
    <input type="hidden" name="description" value="<?php echo htmlspecialchars($description); ?>">
    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
    <input type="hidden" name="condition" value="<?php echo htmlspecialchars($condition); ?>">
    <input type="hidden" name="startingPrice" value="<?php echo htmlspecialchars($startingPrice); ?>">
    <input type="hidden" name="reservePrice" value="<?php echo htmlspecialchars($reservePrice); ?>">
    <input type="hidden" name="startDate" value="<?php echo htmlspecialchars($startDate); ?>">
    <input type="hidden" name="endDate" value="<?php echo htmlspecialchars($endDate); ?>">

    <!-- STEP 1: BASIC INFO -->
    <?php if ($currentStep == 1): ?>
      <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100 space-y-6">

        <!-- Title -->
        <div>
          <label class="block text-gray-900 mb-2">What are you selling? *</label>
          <input type="text" name="title" required
            value="<?php echo htmlspecialchars($title); ?>"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500">
        </div>

        <!-- Description -->
        <div>
          <label class="block text-gray-900 mb-2">Description *</label>
          <textarea name="description" required rows="6"
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500"><?php echo htmlspecialchars($description); ?></textarea>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-gray-900 mb-3">Category *</label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <?php
            $categories = [
              "electronics" => "💻 Electronics",
              "fashion"     => "👗 Fashion",
              "home"        => "🏡 Home & Garden",
              "sports"      => "⚽ Sports",
              "collectibles" => "🎨 Collectibles",
              "automotive"   => "🚗 Automotive",
              "books"        => "📚 Books",
              "jewelry"      => "💎 Jewelry"
            ];
            foreach ($categories as $value => $label):
            ?>
              <label class="category-option flex flex-col items-center p-4 border-2 rounded-xl cursor-pointer transition-all
                <?php echo ($category == $value) ? 'border-purple-500 bg-purple-50' : 'border-gray-200'; ?>">
                <input type="radio" name="category" class="sr-only"
                  value="<?php echo $value; ?>" <?php if ($category == $value) echo 'checked'; ?> required>
                <span class="text-3xl mb-2"><?php echo explode(' ', $label)[0]; ?></span>
                <span><?php echo implode(' ', array_slice(explode(' ', $label), 1)); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Condition -->
        <div>
          <label class="block text-gray-900 mb-3">Condition *</label>
          <div class="space-y-2">
            <?php
            $conditions = [
              "new" => "Brand New",
              "like-new" => "Like New",
              "excellent" => "Excellent",
              "good" => "Good",
              "fair" => "Fair"
            ];
            foreach ($conditions as $value => $label):
            ?>
              <label class="condition-option flex items-start p-4 border-2 rounded-xl cursor-pointer
                <?php echo ($condition == $value) ? 'border-purple-500 bg-purple-50' : 'border-gray-200'; ?>">
                <input type="radio" name="condition" value="<?php echo $value; ?>"
                  class="mt-1" <?php if ($condition == $value) echo 'checked'; ?> required>
                <div class="ml-3">
                  <div class="text-gray-900"><?php echo $label; ?></div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="flex justify-between pt-4">
          <button type="submit" name="cancel" value="1"
            onclick="document.getElementById('createAuctionForm').noValidate = true;"
            class="px-8 py-3 bg-red-50 text-red-700 rounded-xl">
            Cancel
          </button>

          <div>
            <button type="submit" onclick="goStep(2)"
              class="px-8 py-3 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl">
              Continue to Images
            </button>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- STEP 2: IMAGES -->
    <?php if ($currentStep == 2): ?>
      <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100 space-y-6">

        <h2 class="text-gray-900 mb-2">Upload Photos *</h2>

        <!-- Image Preview Container -->
        <div id="imagePreviewContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <?php if ($existingPhoto): ?>
            <div class="relative w-36 h-36 md:w-40 md:h-40">
              <img src="<?php echo htmlspecialchars($existingPhoto); ?>" class="w-full h-full object-cover rounded-xl border-2 border-gray-200" />
            </div>
          <?php endif; ?>
        </div>

        <!-- Hidden file input (always present so Replace works) -->
        <input type="file" id="imageInput" name="imageInput" accept="image/*" class="sr-only">

        <?php if (!$existingPhoto): ?>
          <div id="addPhotoArea" class="w-36 h-36 md:w-40 md:h-40 border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center cursor-pointer hover:border-purple-400">
            <button type="button" id="addPhotoBtn" class="flex flex-col items-center justify-center w-full h-full bg-transparent border-0">
              <span class="text-3xl mb-1">📤</span>
              <span class="text-sm">Add Photo</span>
            </button>
          </div>
        <?php else: ?>
          <div class="flex items-center gap-3">
            <button type="button" id="replacePhotoBtn" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl" onclick="document.getElementById('imageInput').click()">Replace Photo</button>
            <button type="submit" name="removePhoto" value="1" class="px-4 py-2 bg-red-50 text-red-700 rounded-xl" onclick="document.getElementById('currentStep').value=2">Remove Photo</button>
          </div>
        <?php endif; ?>

        <div class="flex justify-between pt-4">
          <button type="button" onclick="submitStep(1, false)"
            class="px-8 py-3 bg-gray-100 text-gray-700 rounded-xl">Back</button>
          <button type="submit" onclick="return validateStep3()"
            class="px-8 py-3 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl">
            Continue to Pricing
          </button>
        </div>

      </div>
    <?php endif; ?>

    <!-- STEP 3: PRICING -->
    <?php if ($currentStep == 3): ?>
      <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100 space-y-8">

        <h2 class="text-gray-900 mb-6">Set Your Prices</h2>

        <!-- Starting Price -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="p-6 bg-green-50 border-2 border-green-200 rounded-xl">
            <label class="block text-gray-900 mb-2">Starting Bid *</label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-600 text-xl">$</span>
              <input type="number" name="startingPrice" min="0" step="0.01" required
                value="<?php echo $startingPrice; ?>"
                class="w-full pl-10 pr-4 py-3 border-2 border-green-300 rounded-xl">
            </div>
          </div>

          <!-- Reserve Price -->
          <div class="p-6 bg-blue-50 border-2 border-blue-200 rounded-xl">
            <label class="block text-gray-900 mb-2">Reserve Price (Optional)</label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-600 text-xl">$</span>
              <input type="number" name="reservePrice" min="0" step="0.01"
                value="<?php echo $reservePrice; ?>"
                class="w-full pl-10 pr-4 py-3 border-2 border-blue-300 rounded-xl">
            </div>
          </div>
        </div>

        <h2 class="text-gray-900 mt-6">Schedule</h2>

        <!-- Start Date -->
        <label class="block text-gray-900 mb-2 mt-4">Start Date *</label>
        <input type="datetime-local" name="startDate" required
          min="<?php echo date('Y-m-d\\TH:i'); ?>"
          value="<?php echo $startDate; ?>"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl">

        <!-- End Date -->
        <label class="block text-gray-900 mb-2 mt-4">End Date *</label>
        <input type="datetime-local" name="endDate" required
          min="<?php echo $startDate ?: date('Y-m-d\\TH:i'); ?>"
          value="<?php echo $endDate; ?>"
          class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl">

        <div class="flex justify-between pt-6 border-t border-gray-200">
          <button type="button" onclick="submitStep(2, false)"
            class="px-8 py-3 bg-gray-100 text-gray-700 rounded-xl">Back</button>
            <button type="submit" name="launch" value="1"
              class="px-8 py-3 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl">
              Launch Auction 🚀
            </button>
        </div>
      </div>
    <?php endif; ?>

  </form>
</div>

<!-- Image Preview Script -->
<script>
function goStep(step) {
  document.getElementById("currentStep").value = step;
}

// Submit the form for step navigation. If `validate` is false,
// disable HTML5 validation so users can go Back without filling
// required fields on the current step.
function submitStep(step, validate = true) {
  document.getElementById("currentStep").value = step;
  const form = document.querySelector('form');
  if (!validate) form.noValidate = true;
  form.submit();
}

const input = document.getElementById("imageInput");
const previewContainer = document.getElementById("imagePreviewContainer");

if (input) {
  input.addEventListener('change', () => {
    // clear previous preview
    previewContainer.innerHTML = '';
    const file = input.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);

    // container for image + controls
    const wrapper = document.createElement('div');
    wrapper.className = 'flex flex-col items-start';

    const imgWrap = document.createElement('div');
    imgWrap.className = 'relative w-36 h-36 md:w-40 md:h-40';
    imgWrap.innerHTML = `
      <img src="${url}" class="w-full h-full object-cover rounded-xl border-2 border-gray-200" />
    `;

    // controls: Replace + Delete
    const controls = document.createElement('div');
    controls.className = 'mt-2 flex items-center gap-2';

    const replaceBtn = document.createElement('button');
    replaceBtn.type = 'button';
    replaceBtn.className = 'px-3 py-1 bg-gray-100 text-gray-700 rounded-xl';
    replaceBtn.textContent = 'Replace';
    replaceBtn.addEventListener('click', (e) => {
      e.preventDefault();
      // trigger file chooser
      input.click();
    });

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'px-3 py-1 bg-red-50 text-red-700 rounded-xl';
    deleteBtn.textContent = 'Delete';
    deleteBtn.addEventListener('click', (e) => {
      e.preventDefault();
      // clear preview and file input
      previewContainer.innerHTML = '';
      try { input.value = ''; } catch (err) { /* ignore */ }
      // show add area again if present
      const addArea = document.getElementById('addPhotoArea');
      if (addArea) addArea.style.display = '';
      // revoke object URL
      URL.revokeObjectURL(url);
    });

    controls.appendChild(replaceBtn);
    controls.appendChild(deleteBtn);

    wrapper.appendChild(imgWrap);
    wrapper.appendChild(controls);

    previewContainer.appendChild(wrapper);

    // hide add area if present
    const addArea = document.getElementById('addPhotoArea');
    if (addArea) addArea.style.display = 'none';
  });
}

// Wire up Add/Replace buttons to trigger file chooser
const addBtn = document.getElementById('addPhotoBtn');
if (addBtn && input) addBtn.addEventListener('click', () => input.click());
</script>

<!-- Photo presence validation: prevent moving to Step 3 unless a photo exists -->
<script>
function validateStep3() {
  var form = document.getElementById('createAuctionForm');
  var input = document.getElementById('imageInput');
  var preview = document.getElementById('imagePreviewContainer');

  var hasPreview = preview && preview.children && preview.children.length > 0;
  var hasFile = input && input.files && input.files.length > 0;

  if (hasPreview || hasFile) {
    document.getElementById('currentStep').value = 3;
    return true; // allow submit
  }

  alert('Please upload at least one photo before continuing.');
  return false; // block submit
}
</script>

<!-- Category selection visual update (client-side) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const options = document.querySelectorAll('.category-option');

  function updateCategoryVisuals() {
    options.forEach(opt => {
      const input = opt.querySelector('input[type="radio"]');
      if (!input) return;
      if (input.checked) {
        opt.classList.add('border-purple-500', 'bg-purple-50');
        opt.classList.remove('border-gray-200');
      } else {
        opt.classList.remove('border-purple-500', 'bg-purple-50');
        if (!opt.classList.contains('border-gray-200')) opt.classList.add('border-gray-200');
      }
    });
  }

  // attach listeners
  document.querySelectorAll('input[name="category"]').forEach(r => {
    r.addEventListener('change', updateCategoryVisuals);
  });

  // initial sync (handles server-rendered selected value)
  updateCategoryVisuals();
});
</script>

<!-- Condition selection visual update (client-side) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const options = document.querySelectorAll('.condition-option');

  function updateConditionVisuals() {
    options.forEach(opt => {
      const input = opt.querySelector('input[type="radio"]');
      if (!input) return;
      if (input.checked) {
        opt.classList.add('border-purple-500', 'bg-purple-50');
        opt.classList.remove('border-gray-200');
      } else {
        opt.classList.remove('border-purple-500', 'bg-purple-50');
        if (!opt.classList.contains('border-gray-200')) opt.classList.add('border-gray-200');
      }
    });
  }

  // attach listeners
  document.querySelectorAll('input[name="condition"]').forEach(r => {
    r.addEventListener('change', updateConditionVisuals);
  });

  // initial sync
  updateConditionVisuals();
});
</script>

</body>
</html>