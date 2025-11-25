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
              <input type="number" name="startingPrice" min="0" step="1" required
                value="<?php echo $startingPrice; ?>"
                class="w-full pl-10 pr-4 py-3 border-2 border-green-300 rounded-xl">
            </div>
          </div>

          <!-- Reserve Price -->
          <div class="p-6 bg-blue-50 border-2 border-blue-200 rounded-xl">
            <label class="block text-gray-900 mb-2">Reserve Price (Optional)</label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-600 text-xl">$</span>
              <input type="number" name="reservePrice" min="0" step="1"
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
