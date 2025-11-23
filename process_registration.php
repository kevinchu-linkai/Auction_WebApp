<?php
session_start();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

require_once 'database.php';

$userType = $_POST['userType'] ?? 'buyer';
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirmPassword'] ?? '';
$terms = isset($_POST['terms']);

// Preserve inputs for re-display (except passwords)
$_SESSION['reg_old'] = [
    'userType' => $userType,
    'username' => $username,
    'email' => $email,
    'terms' => $terms,
];

// Basic validation
if ($username === '' || $email === '') {
    $_SESSION['reg_error'] = 'Please provide a username and email.';
    header('Location: register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['reg_error'] = 'Please provide a valid email address.';
    header('Location: register.php');
    exit;
}

if ($password === '' || $confirm === '' || $password !== $confirm) {
    $_SESSION['reg_error'] = 'Passwords do not match or are empty.';
    header('Location: register.php');
    exit;
}

if (!$terms) {
    $_SESSION['reg_error'] = 'You must agree to the terms and conditions.';
    header('Location: register.php');
    exit;
}

if (!in_array($userType, ['buyer', 'seller'], true)) {
    $userType = 'buyer';
}

// Choose table and SQL
if ($userType === 'seller') {
    $checkSql = 'SELECT sellerId FROM Seller WHERE email = ? OR username = ? LIMIT 1';
    $insertSql = 'INSERT INTO Seller (username, email, password) VALUES (?, ?, ?)';
} else {
    $checkSql = 'SELECT buyerId FROM Buyer WHERE email = ? OR username = ? LIMIT 1';
    $insertSql = 'INSERT INTO Buyer (username, email, password) VALUES (?, ?, ?)';
}

// Check duplicates
$stmt = mysqli_prepare($connection, $checkSql);
if (!$stmt) {
    $_SESSION['reg_error'] = 'Database error (prepare failed).';
    header('Location: register.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ss', $email, $username);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    $_SESSION['reg_error'] = 'An account with that email or username already exists.';
    header('Location: register.php');
    exit;
}
mysqli_stmt_close($stmt);

// Insert user
$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = mysqli_prepare($connection, $insertSql);
if (!$ins) {
    $_SESSION['reg_error'] = 'Database error (prepare failed).';
    header('Location: register.php');
    exit;
}

mysqli_stmt_bind_param($ins, 'sss', $username, $email, $hash);
$ok = mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

if ($ok) {
    $_SESSION['reg_success'] = true;
    // Simplified message to avoid duplicate UI elements on the register page
    $_SESSION['reg_success_msg'] = 'Account created successfully.';
    // Preserve old input (username/email) so the register page can show the
    // filled-in fields while displaying the success message before redirect.
    $_SESSION['reg_old'] = [
        'userType' => $userType,
        'username' => $username,
        'email' => $email,
        'terms' => $terms,
    ];
    // Redirect target depends on user type (send user to appropriate login)
    $target = 'login.php';
    if ($userType === 'seller') {
        $target .= '?userType=seller';
        // Make the intermediate register reload keep seller selected
        $registerLocation = 'register.php?userType=seller';
    } else {
        $target .= '?userType=buyer';
        $registerLocation = 'register.php?userType=buyer';
    }
    $_SESSION['reg_redirect'] = $target;
    // Redirect back to register page but include userType so the seller/buyer
    // selection remains visible while the success message shows.
    header('Location: ' . $registerLocation);
    exit;
} else {
    $_SESSION['reg_error'] = 'Failed to create account (database error).';
    header('Location: register.php');
    exit;
}

?><?php

?>