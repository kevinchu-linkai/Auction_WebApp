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

    <form method="POST" enctype="multipart/form-data" class="space-y-5">
        <input type="hidden" name="removePhoto" id="removePhoto" value="0">
        <!-- single shared file input (kept outside labels to avoid double-trigger) -->
        <input
            type="file"
            name="image"
            id="imageInput"
            accept="image/*"
            class="hidden"
        />
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
                                    step="1"
                                    min="0"
                                    value="<?= htmlspecialchars($auctionData['startingPrice']) ?>"
                                    class="w-full pl-8 pr-3 py-2 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
                                    placeholder="0"
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
                                    step="1"
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
// make clicking the placeholder open the main file input as well
const placeholder = document.getElementById('imagePlaceholder');
if (placeholder && imageInput) {
    placeholder.addEventListener('click', (e) => {
        e.preventDefault();
        imageInput.click();
    });
}
// make Change button explicitly open the hidden file input (works even if label behavior is inconsistent)
const changeImageBtn = document.getElementById('changeImageBtn');
if (changeImageBtn && imageInput) {
    changeImageBtn.addEventListener('click', (e) => {
        // if click originated on the inner input, let it proceed
        if (e.target && e.target.tagName === 'INPUT') return;
        // ensure remove flag cleared before choosing new file
        const remFld = document.getElementById('removePhoto');
        if (remFld) remFld.value = '0';
        imageInput.click();
    });
}
if (removeImageBtn) {
    removeImageBtn.addEventListener('click', () => {
        previewImage.src = '';
        imagePreviewWrapper.classList.add('hidden');
        imagePlaceholder.classList.remove('hidden');
        if (imageInput) imageInput.value = '';
        // mark removal so server sets photo = NULL
        const rem = document.getElementById('removePhoto');
        if (rem) rem.value = '1';
    });
}
</script>
</body>
</html>
