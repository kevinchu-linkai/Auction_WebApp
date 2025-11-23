<?php
  session_start();

    // Read flash messages and previous inputs from session (set by process_registration.php)
    $success = $_SESSION['reg_success'] ?? false;
    $successMessage = $_SESSION['reg_success_msg'] ?? '';
    $error = $_SESSION['reg_error'] ?? '';
    $old = $_SESSION['reg_old'] ?? [];
    $regRedirect = $_SESSION['reg_redirect'] ?? null;

    // Clear flashes. Preserve `reg_old` if registration just succeeded so the
    // page continues to show the filled fields while the success message is
    // visible. Clear `reg_old` only when there's no recent success.
    if ($success) {
      unset($_SESSION['reg_success'], $_SESSION['reg_success_msg'], $_SESSION['reg_error'], $_SESSION['reg_redirect']);
    } else {
      unset($_SESSION['reg_success'], $_SESSION['reg_success_msg'], $_SESSION['reg_error'], $_SESSION['reg_old'], $_SESSION['reg_redirect']);
    }

  // Selected user type (buyer/seller)
  // Prefer session 'old' inputs, then POST (selection change), then GET (link to specific signup), default to 'buyer'.
  $selectedType = $old['userType'] ?? ($_POST['userType'] ?? ($_GET['userType'] ?? 'buyer'));

    // If registration succeeded, send a Refresh header to redirect to login after a short delay.
    if (!empty($success)) {
      $target = $regRedirect ?? 'login.php';
      header('Refresh: 3; url=' . $target);
    }

  // Form data (prefer old inputs from session)
  $username = $old['username'] ?? ($_POST['username'] ?? '');
  $email = $old['email'] ?? ($_POST['email'] ?? '');
  $password = '';
  $confirmPassword = '';
  $agreeToTerms = isset($old['terms']) ? (bool)$old['terms'] : (isset($_POST['terms']) ? true : false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Sign Up - Auction</title>
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 flex items-center justify-center p-4 py-12">

<div class="w-full max-w-md">
  <!-- Logo & Header -->
  <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-purple-600 rounded-full mb-4">
          <span class="text-white text-3xl">⚖️</span>
      </div>
      <h1 class="text-gray-900 mb-2 text-2xl font-semibold">Join Auction</h1>
      <p class="text-gray-600">Create your account to get started</p>
  </div>

  <!-- Sign Up Card -->
  <div class="bg-white rounded-2xl shadow-xl p-8">
    <?php if (!empty($success)) : ?>
      <div class="mb-4 p-3 rounded border border-green-200 bg-green-50 text-green-700" id="reg-success">
        <?php echo $successMessage; ?>
        <div class="text-sm text-gray-600 mt-2">Redirecting to <a href="login.php" class="text-blue-600 underline">login</a> in 3 seconds...</div>
      </div>
    <?php endif; ?>
    <?php if (!empty($error)) : ?>
      <div class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-700">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <!-- User Type Selection -->
    <form method="POST" action="process_registration.php" class="space-y-4">
      <input type="hidden" name="userType" id="userType" value="<?php echo htmlspecialchars($selectedType); ?>">

      <div class="mb-6">
        <label class="text-gray-700 mb-3 block">I want to sign up as</label>
        <div class="grid grid-cols-2 gap-3">

          <!-- Buyer Button -->
          <button
            type="submit"
            name="userType"
            value="buyer"
            formnovalidate
            formaction="register.php"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
            <?php echo $selectedType === 'buyer'
              ? 'border-blue-600 bg-blue-50 text-blue-700'
              : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'; ?>"
          >
            <span>👤</span>
            <span>Buyer</span>
          </button>

          <!-- Seller Button -->
          <button
            type="submit"
            name="userType"
            value="seller"
            formnovalidate
            formaction="register.php"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
            <?php echo $selectedType === 'seller'
              ? 'border-purple-600 bg-purple-50 text-purple-700'
              : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'; ?>"
          >
            <span>🏪</span>
            <span>Seller</span>
          </button>
        </div>
      </div>

      <!-- Username -->
      <div>
        <label class="block text-gray-700 mb-2">Username</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🆔</span>
          <input
            type="text"
            name="username"
            value="<?php echo htmlspecialchars($username); ?>"
            placeholder="Choose a username"
            class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            required
          />
        </div>
      </div>

      <!-- Email -->
      <div>
        <label class="block text-gray-700 mb-2">Email Address</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">📧</span>
          <input
            type="email"
            name="email"
            value="<?php echo htmlspecialchars($email); ?>"
            placeholder="Enter your email"
            class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            required
          />
        </div>
      </div>

      <!-- Password -->
      <div>
        <label class="block text-gray-700 mb-2">Password</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔒</span>
          <input
            id="password"
            type="password"
            name="password"
            placeholder="Create a password"
            class="w-full pl-11 pr-11 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            required
          />
          <button
            type="button"
            onclick="toggle('password')"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
          >
            👁️
          </button>
        </div>
      </div>

      <!-- Confirm Password -->
      <div>
        <label class="block text-gray-700 mb-2">Confirm Password</label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔒</span>
          <input
            id="confirmPassword"
            type="password"
            name="confirmPassword"
            placeholder="Confirm your password"
            class="w-full pl-11 pr-11 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
            required
          />
          <button
            type="button"
            onclick="toggle('confirmPassword')"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
          >
            👁️
          </button>
        </div>
      </div>

      <!-- Terms -->
      <div class="flex items-start gap-2">
        <input type="checkbox" name="terms" class="w-4 h-4 mt-1">
        <label class="text-gray-600">I agree to the <a href="#" class="text-blue-600">Terms and Conditions</a></label>
      </div>

      <!-- Submit -->
      <button
        type="submit"
        name="register"
        class="w-full py-3 rounded-lg text-white transition-all
        <?php echo $selectedType === 'buyer'
          ? 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800'
          : 'bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800'; ?>"
      >
        Create <?php echo ucfirst($selectedType); ?> Account
      </button>

      <div class="mt-6 text-center text-gray-600">
        Already have an account?
        <a href="login.php?userType=<?php echo urlencode($selectedType); ?>" class="text-blue-600 hover:text-blue-700">Sign in</a>
      </div>
    </form>
  </div>
</div>

<script>
function toggle(id) {
    const input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
}
</script>

</body>
</html>

<?php include_once("footer.php")?>