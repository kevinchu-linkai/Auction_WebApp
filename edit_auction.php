<?php
// DB-backed edit page: loads Auction and Item for ?edit=<auctionId>, validates seller ownership,
// pre-fills the form and updates Item/Auction on submit.

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
    // map categoryName back to simple key if possible (best-effort)
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
    $itemUpdated = false;
    $oldDeleted = null;

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
                        if (@unlink($oldFull)) {
                            $oldDeleted = true;
                        } else {
                            // couldn't delete old file, but continue
                            $oldDeleted = false;
                        }
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
            } else {
                $itemUpdated = true;
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
            } else {
                $itemUpdated = true;
            }
            mysqli_stmt_close($updItem);
        } else {
            $error = 'Database error preparing item update.';
        }
    }

    // If item update ok, update auction
    // create debug log info for this POST attempt (will be appended)
    $debugLog = [];
    $debugLog['time'] = date('c');
    $debugLog['auctionId'] = $auctionId;
    $debugLog['itemId'] = $item['itemId'] ?? null;
    $debugLog['posted'] = $posted;
    $debugLog['removeRequested'] = $removeRequested;
    $debugLog['oldPhoto'] = $oldPhoto;
    $debugLog['photoPath'] = $photoPath;
    $debugLog['files'] = [];
    if (!empty($_FILES['image'])) {
        $debugLog['files'] = [
            'name' => $_FILES['image']['name'] ?? null,
            'type' => $_FILES['image']['type'] ?? null,
            'tmp_name' => $_FILES['image']['tmp_name'] ?? null,
            'error' => $_FILES['image']['error'] ?? null,
            'size' => $_FILES['image']['size'] ?? null,
        ];
    }

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
            mysqli_stmt_bind_param($updAuction, 'ddsssi', $posted['startingPrice'], $reserveParam, $sd, $ed, $state, $auctionId);
            if (!mysqli_stmt_execute($updAuction)) {
                $error = 'Database error updating auction: ' . mysqli_error($connection);
            } else {
                // Successful update: redirect back to My Listings
                mysqli_stmt_close($updAuction);
                header('Location: mylistings.php');
                exit;
                mysqli_stmt_close($updAuction);
                // append debug info to file before redirect
                $tmpDir = __DIR__ . '/tmp';
                if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
                $logFile = $tmpDir . '/upload_debug.log';
                $debugLog['itemUpdated'] = !empty($itemUpdated);
                $debugLog['oldDeleted'] = $oldDeleted;
                @file_put_contents($logFile, print_r($debugLog, true) . "\n---\n", FILE_APPEND);
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Auction</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 py-6 px-4">
<div class="max-w-3xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900 mb-1">Edit Auction</h1>
        <p class="text-gray-600">Update your listing details</p>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-800">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-800">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($debugOutput)): ?>
        <div class="mb-4">
            <strong class="block text-gray-700 mb-1">Debug info (POST):</strong>
            <?= $debugOutput ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="removePhoto" id="removePhoto" value="0">
        <!-- Image Upload Card -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                    <span class="text-blue-600 text-lg">🖼️</span>
                </div>
                <h3 class="text-gray-900 font-semibold">Image</h3>
            </div>

            <div id="imageSection">
                <?php $hasImage = !empty($initialImage); ?>
                <div id="imagePreviewWrapper" class="space-y-3 <?= $hasImage ? '' : 'hidden' ?>">
                    <div class="relative aspect-[2/1] rounded-lg overflow-hidden bg-gray-100">
                        <img id="previewImage"
                             src="<?= htmlspecialchars($initialImage) ?>"
                             alt="Auction"
                             class="w-full h-full object-cover">
                    </div>
                    <div class="flex gap-2">
                        <label id="changeImageBtn" class="flex-1 py-2 text-center bg-blue-600 text-white rounded-lg cursor-pointer hover:bg-blue-700 transition-colors">
                            Change
                            <input
                                type="file"
                                name="image"
                                id="imageInput"
                                accept="image/*"
                                class="hidden"
                            />
                        </label>
                        <button
                            type="button"
                            id="removeImageBtn"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Remove
                        </button>
                    </div>
                </div>

                <label id="imagePlaceholder" class="<?= $hasImage ? 'hidden' : '' ?> aspect-[2/1] rounded-lg border-2 border-dashed border-gray-300 hover:border-gray-400 transition-colors cursor-pointer bg-gray-50">
                    <div class="h-full flex flex-col items-center justify-center">
                        <span class="text-3xl text-gray-400 mb-2">⬆️</span>
                        <p class="text-gray-600">Click to upload</p>
                        <p class="text-gray-400 mt-1">JPG, PNG or WEBP</p>
                    </div>
                    <input
                        type="file"
                        name="image"
                        id="imageInput2"
                        accept="image/*"
                        class="hidden"
                    />
                </label>
            </div>
        </div>

        <!-- Basic Info Card -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                    <span class="text-green-600 text-lg">ℹ️</span>
                </div>
                <h3 class="text-gray-900 font-semibold">Basic Information</h3>
            </div>

            <div class="space-y-4">
                <!-- Auction Name -->
                <div>
                    <label for="auctionName" class="block text-gray-700 mb-1.5">
                        Auction Name
                    </label>
                    <input
                        id="auctionName"
                        name="auctionName"
                        type="text"
                        value="<?= htmlspecialchars($auctionData['auctionName']) ?>"
                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                        placeholder="Enter auction name"
                        required
                    />
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-gray-700 mb-1.5">
                        Description
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-none transition-all"
                        placeholder="Describe your item..."
                        required
                    ><?= htmlspecialchars($auctionData['description']) ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Category -->
                    <div>
                        <label for="category" class="block text-gray-700 mb-1.5">
                            Category
                        </label>
                        <select
                                                id="category"
                                                name="category"
                                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                                                required
                                            >
                                                <option value="">Select category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= intval($cat['categoryId']) ?>" <?= ($auctionData['categoryId'] == $cat['categoryId']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                    </div>

                    <!-- Condition -->
                    <div>
                        <label for="condition" class="block text-gray-700 mb-1.5">
                            Condition
                        </label>
                        <select
                            id="condition"
                            name="condition"
                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                            required
                        >
                            <option value="">Select condition</option>
                            <option value="new"       <?= $auctionData['condition'] === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="excellent" <?= $auctionData['condition'] === 'excellent' ? 'selected' : '' ?>>Excellent</option>
                            <option value="good"      <?= $auctionData['condition'] === 'good' ? 'selected' : '' ?>>Good</option>
                            <option value="fair"      <?= $auctionData['condition'] === 'fair' ? 'selected' : '' ?>>Fair</option>
                            <option value="poor"      <?= $auctionData['condition'] === 'poor' ? 'selected' : '' ?>>Poor</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing & Schedule Card -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Pricing -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                            <span class="text-amber-600 text-lg">$</span>
                        </div>
                        <h3 class="text-gray-900 font-semibold">Pricing</h3>
                    </div>

                    <div class="space-y-4">
                        <!-- Starting Price -->
                        <div>
                            <label for="startingPrice" class="block text-gray-700 mb-1.5">
                                Starting Price
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                <input
                                    id="startingPrice"
                                    name="startingPrice"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value="<?= htmlspecialchars($auctionData['startingPrice']) ?>"
                                    class="w-full pl-8 pr-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                                    placeholder="0.00"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Reserve Price -->
                        <div>
                            <label for="reservePrice" class="block text-gray-700 mb-1.5">
                                Reserve Price
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                <input
                                    id="reservePrice"
                                    name="reservePrice"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value="<?= htmlspecialchars($auctionData['reservePrice']) ?>"
                                    class="w-full pl-8 pr-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                                    placeholder="Optional"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schedule -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                            <span class="text-purple-600 text-lg">⏰</span>
                        </div>
                        <h3 class="text-gray-900 font-semibold">Schedule</h3>
                    </div>

                    <div class="space-y-4">
                        <!-- Start Date -->
                        <div>
                            <label for="startDate" class="block text-gray-700 mb-1.5">
                                Start Date & Time
                            </label>
                            <input
                                id="startDate"
                                name="startDate"
                                type="datetime-local"
                                value="<?= htmlspecialchars($auctionData['startDate']) ?>"
                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                                required
                            />
                        </div>

                        <!-- End Date -->
                        <div>
                            <label for="endDate" class="block text-gray-700 mb-1.5">
                                End Date & Time
                            </label>
                            <input
                                id="endDate"
                                name="endDate"
                                type="datetime-local"
                                value="<?= htmlspecialchars($auctionData['endDate']) ?>"
                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                                required
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3 pt-2">
            <button
                type="button"
                onclick="window.location.href='mylistings.php'"
                class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="flex-1 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
// Simple image preview + remove logic (UI-only)
const imageInput = document.getElementById('imageInput');
const imageInput2 = document.getElementById('imageInput2');
const previewImage = document.getElementById('previewImage');
const imagePreviewWrapper = document.getElementById('imagePreviewWrapper');
const imagePlaceholder = document.getElementById('imagePlaceholder');
const removeImageBtn = document.getElementById('removeImageBtn');

function handleFileInput(e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    // if user selects a new file, clear any previous remove-photo flag
    const remFld = document.getElementById('removePhoto');
    if (remFld) remFld.value = '0';
    const reader = new FileReader();
    reader.onload = function(evt) {
        if (evt.target && typeof evt.target.result === 'string') {
            previewImage.src = evt.target.result;
            imagePreviewWrapper.classList.remove('hidden');
            imagePlaceholder.classList.add('hidden');
        }
    };
    reader.readAsDataURL(file);
}

if (imageInput) {
    imageInput.addEventListener('change', handleFileInput);
}
if (imageInput2) {
    imageInput2.addEventListener('change', handleFileInput);
}
// make Change button explicitly open the hidden file input (works even if label behavior is inconsistent)
const changeImageBtn = document.getElementById('changeImageBtn');
if (changeImageBtn && imageInput) {
    changeImageBtn.addEventListener('click', (e) => {
        // if click originated on the inner input, let it proceed
        if (e.target && e.target.tagName === 'INPUT') return;
        imageInput.click();
    });
}
if (removeImageBtn) {
    removeImageBtn.addEventListener('click', () => {
        previewImage.src = '';
        imagePreviewWrapper.classList.add('hidden');
        imagePlaceholder.classList.remove('hidden');
        if (imageInput) imageInput.value = '';
        if (imageInput2) imageInput2.value = '';
        // mark removal so server sets photo = NULL
        const rem = document.getElementById('removePhoto');
        if (rem) rem.value = '1';
    });
}
</script>
</body>
</html>