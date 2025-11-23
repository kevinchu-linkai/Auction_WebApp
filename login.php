
<?php

    // Start session so we can read/write `$_SESSION` for login state
    if (session_status() === PHP_SESSION_NONE) session_start();

        // Determine selected type (default = buyer)
    $selectedType = $_POST['userType'] ?? 'buyer';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

  // Handle login submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // If userType was submitted from the tiles but the main login button
        // wasn't clicked, treat this as a selection change and skip validation.
        $isSelectionOnly = isset($_POST['userType']) && !isset($_POST['login']);

        if ($isSelectionOnly) {
            // selection-only POST; $selectedType already set from POST above
        }
        else {
        // Connect to DB and validate credentials
        require_once 'database.php';

        $error = null;
        $email = trim($email);

        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } else {
            if ($selectedType === 'buyer') {
                $stmt = mysqli_prepare($connection, "SELECT buyerId, username, password FROM Buyer WHERE email = ? LIMIT 1");
            } else {
                $stmt = mysqli_prepare($connection, "SELECT sellerId, username, password FROM Seller WHERE email = ? LIMIT 1");
            }

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) === 1) {
                    mysqli_stmt_bind_result($stmt, $id, $dbUsername, $dbPass);
                    mysqli_stmt_fetch($stmt);

                    // Support both hashed and plain-text passwords (try password_verify first)
                    $passwordOK = false;
                    if (password_verify($password, $dbPass)) {
                        $passwordOK = true;
                    } elseif ($password === $dbPass) {
                        $passwordOK = true;
                    }

                    if ($passwordOK) {
                        // Successful login: set session and redirect
                        session_regenerate_id(true);
                        $_SESSION['logged_in'] = true;
                        $_SESSION['account_type'] = $selectedType;
                        $_SESSION['user_id'] = $id;
                        $_SESSION['username'] = $dbUsername;

                        // Redirect to appropriate dashboard
                        if ($selectedType === 'buyer') {
                            header('Location: browse.php');
                            exit();
                        } else {
                            header('Location: browse.php');
                            exit();
                        }
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'No account found with that email.';
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Database error (prepare failed).';
            }
        }
        }
  }
?>

<!-- Load Tailwind (kept in body because header already includes Bootstrap) -->
<script src="https://cdn.tailwindcss.com"></script>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 flex items-center justify-center p-4">

<div class="w-full max-w-md">

    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-purple-600 rounded-full mb-4">
        <span class="text-white text-3xl">⚖️</span>
        </div>
        <h1 class="text-gray-900 mb-2 text-2xl font-semibold">Welcome to Auction</h1>
        <p class="text-gray-600">Sign in to your account</p>
    </div>

    <!-- Login Card -->
    <div class="bg-white rounded-2xl shadow-xl p-8">

        <?php if (!empty(
$error ?? null)) : ?>
        <div class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-700">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- User Type Selection -->
        <form method="POST" class="space-y-4">
        <input type="hidden" name="userType" id="userType" value="<?php echo htmlspecialchars($selectedType); ?>">
        <div class="mb-6">
            <label class="text-gray-700 mb-3 block">I want to log in as</label>
            <div class="grid grid-cols-2 gap-3">

            <!-- Buyer button (server-side selection) -->
            <button
                type="submit"
                name="userType"
                value="buyer"
                formnovalidate
                class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all <?php echo $selectedType === 'buyer' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'; ?>"
            >
                <span>👤</span>
                <span>Buyer</span>
            </button>

            <!-- Seller button (server-side selection) -->
            <button
                type="submit"
                name="userType"
                value="seller"
                formnovalidate
                class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all <?php echo $selectedType === 'seller' ? 'border-purple-600 bg-purple-50 text-purple-700' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'; ?>"
            >
                <span>🏪</span>
                <span>Seller</span>
            </button>

            </div>
        </div>

        <!-- Email Input -->
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

        <!-- Password Input -->
        <div>
            <label class="block text-gray-700 mb-2">Password</label>
            <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔒</span>
            <input
                id="password"
                type="password"
                name="password"
                placeholder="Enter your password"
                class="w-full pl-11 pr-11 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                required
            />
            <button
                type="button"
                onclick="togglePassword()"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
                👁️
            </button>
            </div>
        </div>

        <!-- Remember + Forgot -->
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600">
            <span class="text-gray-600">Remember me</span>
            </label>
            <a href="#" class="text-blue-600 hover:text-blue-700">Forgot password?</a>
        </div>

        <!-- Login Button -->
        <button
            type="submit"
            name="login"
            class="w-full py-3 rounded-lg text-white transition-all <?php echo $selectedType === 'buyer' ? 'bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800' : 'bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800'; ?>"
        >
            Sign in as <?php echo ucfirst($selectedType); ?>
        </button>

        <!-- Signup -->
        <div class="mt-6 text-center">
            <p class="text-gray-600">
            Don't have an account?
            <a href="register.php" class="<?php echo $selectedType === 'buyer' ? 'text-blue-600' : 'text-purple-600'; ?>">
                Sign up
            </a>
            </p>
        </div>
        </form>
    </div>
</div>

</div>

<!-- Show/Hide Password Script -->
<script>
function togglePassword() {
  const input = document.getElementById("password");
  input.type = input.type === "password" ? "text" : "password";
}
</script>

<!-- Server-side selection: no JS required. Buttons submit with userType and use PHP ($selectedType) for styling. -->

<?php include 'footer.php'; ?>
