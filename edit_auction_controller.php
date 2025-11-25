<?php
/**
 * Edit Auction Controller - Business logic for editing auctions
 * Handles data loading, validation, POST processing, and prepares view variables
 */

date_default_timezone_set('Europe/London');

session_start();
require_once 'database.php';

// must receive an edit auction id
if (!isset($_GET['edit'])) {
    header('Location: mylistings.php');
    exit;
}

$auctionId = intval($_GET['edit']);
if ($auctionId <= 0) {
    header('Location: mylistings.php');
    exit;
}

// must be signed-in seller
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['account_type'] ?? '') !== 'seller') {
    $_SESSION['edit_error'] = 'You must be signed in as a seller to edit an auction.';
    header('Location: login.php');
    exit;
}

$sellerId = $_SESSION['user_id'];

$error = '';
$successMessage = '';

// Load auction and related item
$auction = null;
$item = null;
$initialImage = '';

$stmt = mysqli_prepare($connection, 'SELECT auctionId, sellerId, itemId, startingPrice, reservePrice, startDate, endDate, state FROM Auction WHERE auctionId = ? LIMIT 1');
if (!$stmt) {
    $_SESSION['edit_error'] = 'Database error preparing auction lookup.';
    header('Location: mylistings.php');
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $auctionId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) !== 1) {
    mysqli_stmt_close($stmt);
    $_SESSION['edit_error'] = 'Auction not found.';
    header('Location: mylistings.php');
    exit;
}
mysqli_stmt_bind_result($stmt, $aId, $aSellerId, $aItemId, $aStarting, $aReserve, $aStartDate, $aEndDate, $aState);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($aSellerId != $sellerId) {
    $_SESSION['edit_error'] = 'You do not have permission to edit this auction.';
    header('Location: mylistings.php');
    exit;
}

// load item (note: some DBs may not have photo_base64 column)
$itm = mysqli_prepare($connection, 'SELECT itemId, name, `condition`, description, photo, categoryId FROM Item WHERE itemId = ? LIMIT 1');
if ($itm) {
    mysqli_stmt_bind_param($itm, 'i', $aItemId);
    mysqli_stmt_execute($itm);
    mysqli_stmt_store_result($itm);
    if (mysqli_stmt_num_rows($itm) === 1) {
        mysqli_stmt_bind_result($itm, $iId, $iName, $iCondition, $iDesc, $iPhoto, $iCategoryId);
        mysqli_stmt_fetch($itm);
        mysqli_stmt_close($itm);

        $item = [
            'itemId' => $iId,
            'name' => $iName,
            'condition' => $iCondition,
            'description' => $iDesc,
            'photo' => $iPhoto,
            'categoryId' => $iCategoryId
        ];

        if (!empty($iPhoto)) $initialImage = $iPhoto;
    } else {
        mysqli_stmt_close($itm);
    }
}

// load category name if available
$categoryName = '';
if (!empty($item['categoryId'])) {
    $cst = mysqli_prepare($connection, 'SELECT name FROM Category WHERE categoryId = ? LIMIT 1');
    if ($cst) {
        mysqli_stmt_bind_param($cst, 'i', $item['categoryId']);
        mysqli_stmt_execute($cst);
        mysqli_stmt_bind_result($cst, $categoryName);
        mysqli_stmt_fetch($cst);
        mysqli_stmt_close($cst);
    }
}

// map DB values into form-friendly variables
$auctionData = [
    'auctionName' => $item['name'] ?? '',
    'description' => $item['description'] ?? '',
    'category' => '',
    'condition' => '',
    'startingPrice' => $aStarting ?? '',
    'reservePrice' => $aReserve ?? '',
    'startDate' => !empty($aStartDate) ? date('Y-m-d\\TH:i', strtotime($aStartDate)) : '',
    'endDate' => !empty($aEndDate) ? date('Y-m-d\\TH:i', strtotime($aEndDate)) : ''
];

// load categories for the dropdown
$categories = [];
$catQ = mysqli_query($connection, "SELECT categoryId, name FROM Category ORDER BY name");
if ($catQ) {
    while ($row = mysqli_fetch_assoc($catQ)) $categories[] = $row;
}

// set selected categoryId for the form
if (!empty($item['categoryId'])) {
    $auctionData['categoryId'] = $item['categoryId'];
} else {
    $auctionData['categoryId'] = null;
}

// condition mapping: convert DB 'Excellent' -> 'excellent'
if (!empty($item['condition'])) {
    $auctionData['condition'] = strtolower(str_replace(' ', '-', $item['condition']));
}

// image preview
if (!empty($initialImage)) {
    $initialImage = $initialImage;
} else {
    $initialImage = '';
}

// If form POST, process update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // gather posted values (use same names as UI)
    $posted = [];
    $fields = ['auctionName','description','category','condition','startingPrice','reservePrice','startDate','endDate'];
    foreach ($fields as $f) {
        $posted[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : '';
    }

    // handle image upload (optional) and removal
    $photoPath = $item['photo'] ?? null;
    $oldPhoto = $photoPath;

    // check if the user requested removal
    $removeRequested = (!empty($_POST['removePhoto']) && $_POST['removePhoto'] === '1');
    if ($removeRequested) {
        // remove existing file on disk if it lives under img/uploads
        if (!empty($photoPath) && strpos($photoPath, 'img/uploads/') === 0) {
            $full = __DIR__ . '/' . $photoPath;
            if (is_file($full)) @unlink($full);
        }
        $photoPath = null;
    }

    if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        // check PHP upload error
        if (!empty($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload error code: ' . intval($_FILES['image']['error']);
        } else {
            $uploadsDir = __DIR__ . '/img/uploads';
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
            $tmp = $_FILES['image']['tmp_name'];
            $orig = basename($_FILES['image']['name']);
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $filename = 'item_' . uniqid() . ($ext ? ".{$ext}" : '');
            $dest = $uploadsDir . '/' . $filename;
            if (move_uploaded_file($tmp, $dest)) {
                // ensure readable by web server
                @chmod($dest, 0644);
                $photoPath = 'img/uploads/' . $filename;
                // override removal request since a new file was uploaded
                $removeRequested = false;
                // remove old file if it lived under img/uploads and is different
                if (!empty($oldPhoto) && $oldPhoto !== $photoPath && strpos($oldPhoto, 'img/uploads/') === 0) {
                    $oldFull = __DIR__ . '/' . $oldPhoto;
                    if (is_file($oldFull)) {
                        @unlink($oldFull);
                    }
                }
            } else {
                $error = 'Failed to move uploaded file.';
            }
        }
    }

    // resolve category id (we use categoryId values in the select)
    $postedCategoryId = intval($posted['category']);
    $categoryId = $postedCategoryId > 0 ? $postedCategoryId : null;

    // map condition to DB enum form
    $dbCondition = $posted['condition'];
    switch ($posted['condition']) {
        case 'new': $dbCondition = 'New'; break;
        case 'like-new': $dbCondition = 'Like new'; break;
        case 'excellent': $dbCondition = 'Excellent'; break;
        case 'good': $dbCondition = 'Good'; break;
        case 'fair': $dbCondition = 'Fair'; break;
        case 'poor': $dbCondition = 'Poor'; break;
        default: $dbCondition = ucwords(str_replace('-', ' ', $posted['condition'])); break;
    }

    // Update Item
    if ($photoPath === null) {
        // set photo = NULL
        $updItem = mysqli_prepare($connection, 'UPDATE Item SET name = ?, `condition` = ?, description = ?, photo = NULL, categoryId = ? WHERE itemId = ?');
        if ($updItem) {
            mysqli_stmt_bind_param($updItem, 'sssii', $posted['auctionName'], $dbCondition, $posted['description'], $categoryId, $item['itemId']);
            if (!mysqli_stmt_execute($updItem)) {
                $error = 'Database error updating item: ' . mysqli_error($connection) . ' (' . mysqli_stmt_errno($updItem) . ')';
            }
            mysqli_stmt_close($updItem);
        } else {
            $error = 'Database error preparing item update.';
        }
    } else {
        $updItem = mysqli_prepare($connection, 'UPDATE Item SET name = ?, `condition` = ?, description = ?, photo = ?, categoryId = ? WHERE itemId = ?');
        if ($updItem) {
            // bind types: name(s), condition(s), description(s), photo(s), categoryId(i), itemId(i)
            mysqli_stmt_bind_param($updItem, 'ssssii', $posted['auctionName'], $dbCondition, $posted['description'], $photoPath, $categoryId, $item['itemId']);
            if (!mysqli_stmt_execute($updItem)) {
                $error = 'Database error updating item: ' . mysqli_error($connection) . ' (' . mysqli_stmt_errno($updItem) . ')';
            }
            mysqli_stmt_close($updItem);
        } else {
            $error = 'Database error preparing item update.';
        }
    }

    // If item update ok, update auction
    if (empty($error)) {
        $sd = date('Y-m-d H:i:s', strtotime($posted['startDate']));
        $ed = date('Y-m-d H:i:s', strtotime($posted['endDate']));
        $now = date('Y-m-d H:i:s');
        if ($sd > $now) $state = 'not-started';
        elseif ($now >= $sd && $now <= $ed) $state = 'ongoing';
        else $state = 'expired';

        $reserveParam = ($posted['reservePrice'] === '' ? null : $posted['reservePrice']);
        $updAuction = mysqli_prepare($connection, 'UPDATE Auction SET startingPrice = ?, reservePrice = ?, startDate = ?, endDate = ?, state = ? WHERE auctionId = ?');
        if ($updAuction) {
            mysqli_stmt_bind_param($updAuction, 'iisssi', $posted['startingPrice'], $reserveParam, $sd, $ed, $state, $auctionId);
            if (!mysqli_stmt_execute($updAuction)) {
                $error = 'Database error updating auction: ' . mysqli_error($connection);
            } else {
                // Successful update: redirect back to My Listings
                mysqli_stmt_close($updAuction);
                header('Location: mylistings.php');
                exit;
            }
            mysqli_stmt_close($updAuction);
        } else {
            $error = 'Database error preparing auction update.';
        }
    }
}
?>