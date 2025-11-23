<?php
include "database.php";

// Ensure session for multi-step persistence
if (session_status() === PHP_SESSION_NONE) session_start();

// Step Handling
$currentStep = $_POST['currentStep'] ?? 1;

// Use session to persist form values across steps
$sessionKey = 'create_auction_form';
// Initialize session bucket if missing
if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) $_SESSION[$sessionKey] = [];

// Merge POST into session on any submission (so Back/Next preserves values)
$fields = ['title','description','category','condition','startingPrice','reservePrice','startDate','endDate'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($fields as $f) {
    if (isset($_POST[$f])) {
      $_SESSION[$sessionKey][$f] = $_POST[$f];
    }
  }
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
                  mysqli_stmt_bind_param($sel, 's', $category);
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

      // Insert Item
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

    if ($error === '') {
      // Normalize dates
      $sd = date('Y-m-d H:i:s', strtotime($startDate));
      $ed = date('Y-m-d H:i:s', strtotime($endDate));
      $now = date('Y-m-d H:i:s');

      if ($sd > $now) $state = 'not-started';
      elseif ($now >= $sd && $now <= $ed) $state = 'ongoing';
      else $state = 'expired';

      // Insert Auction
      $insAuction = mysqli_prepare($connection, "INSERT INTO Auction (sellerId, itemId, startingPrice, reservePrice, startDate, endDate, state) VALUES (?, ?, ?, ?, ?, ?, ?)");
      if ($insAuction) {
        // reserve may be NULL
        $reserveParam = ($reservePrice === '' ? null : $reservePrice);
        mysqli_stmt_bind_param($insAuction, 'iiddsss', $sellerId, $itemId, $startingPrice, $reserveParam, $sd, $ed, $state);
        if (!mysqli_stmt_execute($insAuction)) {
          $error = 'Database error inserting auction: ' . mysqli_error($connection);
        } else {
          $success = 'Auction created successfully.';
          mysqli_stmt_close($insAuction);
            // clear multi-step session data on successful creation
            unset($_SESSION[$sessionKey]);
          header('Location: browse.php?created=1');
          exit();
        }
      } else {
        if ($error === '') $error = 'Database error preparing auction insert.';
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
  <form method="POST" enctype="multipart/form-data" class="space-y-6">

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

        <div class="flex justify-end pt-4">
          <button type="submit" onclick="goStep(2)"
            class="px-8 py-3 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl">
            Continue to Images
          </button>
        </div>
      </div>
    <?php endif; ?>

    <!-- STEP 2: IMAGES -->
    <?php if ($currentStep == 2): ?>
      <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100 space-y-6">

        <h2 class="text-gray-900 mb-2">Upload Photos</h2>
        
        <!-- Image Preview Container -->
        <div id="imagePreviewContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>

        <label class="w-36 h-36 md:w-40 md:h-40 border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center cursor-pointer hover:border-purple-400">
          <span class="text-3xl mb-1">📤</span>
          <span class="text-sm">Add Photo</span>
          <input type="file" id="imageInput" name="imageInput" accept="image/*" class="sr-only">
        </label>

        <div class="flex justify-between pt-4">
          <button type="submit" onclick="goStep(1)"
            class="px-8 py-3 bg-gray-100 text-gray-700 rounded-xl">Back</button>
          <button type="submit" onclick="goStep(3)"
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
          <button type="submit" onclick="goStep(2)"
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

const input = document.getElementById("imageInput");
const previewContainer = document.getElementById("imagePreviewContainer");

if (input) {
  input.addEventListener("change", () => {
    // clear previous preview
    previewContainer.innerHTML = '';
    const file = input.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    const div = document.createElement("div");
    div.className = "relative w-36 h-36 md:w-40 md:h-40";
    div.innerHTML = `
      <img src="${url}" class="w-full h-full object-cover rounded-xl border-2 border-gray-200" />
    `;
    previewContainer.appendChild(div);
  });
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