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

// Handle final submission (only when Launch Auction is clicked)
$success = '';
$error = '';
$shouldRedirect = false;
$redirectUrl = '';

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
          mysqli_stmt_bind_param($updAuction, 'iisssi', $startingPrice, $reserveParam, $sd, $ed, $state, $editAuctionId);
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
          mysqli_stmt_bind_param($insAuction, 'iiiisss', $sellerId, $itemId, $startingPrice, $reserveParam, $sd, $ed, $state);
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
