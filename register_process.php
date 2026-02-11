<?php
require 'config.php';

$name     = trim($_POST['name']);
$email    = trim($_POST['email']);
$password = $_POST['password'];

if (empty($name) || empty($email) || empty($password)) {
    die("All fields are required");
}

// hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// check if email already exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    die("Email already registered");
}

// insert user
$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hashedPassword);

if ($stmt->execute()) {
    header("Location: login.php");
} else {
    echo "Registration failed";
}
