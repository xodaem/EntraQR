<?php
session_start();
include __DIR__ . "/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* 1. GET VALUES FROM FORM */
$fname = $_POST['fname'] ?? '';
$email = $_POST['email'] ?? '';
$password_input = $_POST['password'] ?? '';

/* 2. UPDATE NAME + EMAIL */
$stmt = $conn->prepare("UPDATE users SET fname=?, email=? WHERE id=?");
$stmt->bind_param("ssi", $fname, $email, $user_id);
$stmt->execute();

/* 3. PASSWORD UPDATE */
if (!empty($password_input)) {
    $password = password_hash($password_input, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $password, $user_id);
    $stmt->execute();
}

/* 4. PROFILE PICTURE UPDATE (NORMAL UPLOAD + CROPPED IMAGE SUPPORT ADDED) */

$image_name = null;

/* ✅ ADDED: CROPPED IMAGE HANDLING */
if (!empty($_POST['cropped_image'])) {

    $data = $_POST['cropped_image'];

    $image_array_1 = explode(";", $data);
    $image_array_2 = explode(",", $image_array_1[1]);

    $data = base64_decode($image_array_2[1]);

    $image_name = time() . "_cropped.png";

    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    file_put_contents("uploads/" . $image_name, $data);

    $stmt = $conn->prepare("UPDATE users SET profile_pic=? WHERE id=?");
    $stmt->bind_param("si", $image_name, $user_id);
    $stmt->execute();

/* ORIGINAL UPLOAD (UNCHANGED) */
} elseif (!empty($_FILES['profile_pic']['name'])) {

    $image_name = time() . "_" . $_FILES['profile_pic']['name'];
    $tmp = $_FILES['profile_pic']['tmp_name'];
    $path = "uploads/" . $image_name;

    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    if(move_uploaded_file($tmp, $path)) {
        $stmt = $conn->prepare("UPDATE users SET profile_pic=? WHERE id=?");
        $stmt->bind_param("si", $image_name, $user_id);
        $stmt->execute();
    }
}

/* 5. REFRESH SESSION DATA */
$stmt = $conn->prepare("SELECT fname, email, profile_pic FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$_SESSION['fname'] = $user['fname'];
$_SESSION['email'] = $user['email'];
$_SESSION['profile_pic'] = $user['profile_pic']; 

header("Location: homepage.php");
exit();
?>