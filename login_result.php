<?php

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_once 'database.php';

$userType = $_POST['userType'] ?? 'buyer';
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Preserve non-sensitive old inputs for repopulation
$_SESSION['login_old'] = [
    'userType' => $userType,
    'email' => $email,
];

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter email and password.';
    header('Location: login.php');
    exit;
}

if ($userType === 'buyer') {
    $sql = "SELECT buyerId, username, password FROM Buyer WHERE email = ? LIMIT 1";
} else {
    $sql = "SELECT sellerId, username, password FROM Seller WHERE email = ? LIMIT 1";
}

$stmt = mysqli_prepare($connection, $sql);
if (!$stmt) {
    $_SESSION['login_error'] = 'Database error (prepare failed).';
    header('Location: login.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) !== 1) {
    mysqli_stmt_close($stmt);
    $_SESSION['login_error'] = 'No account found with that email.';
    header('Location: login.php');
    exit;
}

mysqli_stmt_bind_result($stmt, $id, $dbUsername, $dbPass);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$passwordOK = false;
if (password_verify($password, $dbPass)) {
    $passwordOK = true;
} elseif ($password === $dbPass) {
    $passwordOK = true; // support legacy plaintext entries
}

if (!$passwordOK) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: login.php');
    exit;
}

// Successful login
session_regenerate_id(true);
$_SESSION['logged_in'] = true;
$_SESSION['account_type'] = $userType;
$_SESSION['user_id'] = $id;
$_SESSION['username'] = $dbUsername;

// Clear old input and errors
unset($_SESSION['login_old'], $_SESSION['login_error']);

// Redirect to browse/dashboard
header('Location: browse.php');
exit;

?><?php

// TODO: Extract $_POST variables, check they're OK, and attempt to login.
// Notify user of success/failure and redirect/give navigation options.

// For now, I will just set session variables and redirect.

session_start();
$_SESSION['logged_in'] = true;
$_SESSION['username'] = "test";
$_SESSION['account_type'] = "buyer";

echo('<div class="text-center">You are now logged in! You will be redirected shortly.</div>');

// Redirect to index after 5 seconds
header("refresh:5;url=index.php");

?>