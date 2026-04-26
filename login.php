<?php
// DB CONNECTION
$host = "localhost";
$user = "root";
$pass = "";
$db = "admin_system";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

session_start();
$errors = [];
$mode = $_GET['mode'] ?? 'register';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'];
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if ($mode === 'register') {
        if (empty($fname) || empty($lname)) {
            $errors[] = "First name and last name are required";
        }

        if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\\d)(?=.*[@$!%*?&]).{8,}$/', $password)) {
            $errors[] = "Password must be at least 8 characters, include upper, lower, number, and special character.";
        }

        if (empty($errors)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $errors[] = "Email already registered";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (fname, lname, email, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $fname, $lname, $email, $hashed);
                if ($stmt->execute()) {
                    header("Location: ?mode=login");
                    exit();
                } else {
                    $errors[] = "Something went wrong";
                }
            }
        }
    }

    if ($mode === 'login') {
        if (empty($errors)) {
            // UPDATED: Added profile_pic to the SELECT statement
            $stmt = $conn->prepare("SELECT id, email, fname, password, profile_pic FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                // UPDATED: Added $profile_pic to the bind_result
                $stmt->bind_result($id, $db_email, $fname, $hash, $profile_pic);
                $stmt->fetch();

                if (password_verify($password, $hash)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['email']   = $db_email;
                    $_SESSION['fname']   = $fname;
                    
                    // UPDATED: Save profile_pic filename to session so it doesn't disappear
                    $_SESSION['profile_pic'] = $profile_pic;

                    header("Location: homepage.php");
                    exit();

                } else {
                    $errors[] = "Incorrect password";
                }
            } else {
                $errors[] = "Email not found";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EntraQr</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial, sans-serif;background:#2b2b3c}

.wrapper{display:flex;height:100vh;width:100%}

.left{
  width:50%;
  background: linear-gradient(135deg, #a1fa9b 0%, #e8ffe9 50%, #ffffff 100%);
  color:#1f1f1f;
  display:flex;
  flex-direction:column;
  justify-content:center;
  align-items:center;
  padding:40px;
  position:relative;
  overflow:hidden;
}

.left::before{
  content:"";
  position:absolute;
  width:400px;
  height:400px;
  background:rgba(123,92,255,0.15);
  border-radius:50%;
  top:-100px;
  left:-100px;
  filter:blur(60px);
}

.left::after{
  content:"";
  position:absolute;
  width:350px;
  height:350px;
  background:rgba(161,250,155,0.3);
  border-radius:50%;
  bottom:-120px;
  right:-120px;
  filter:blur(70px);
}

.logo{
  position:relative;
  z-index:2;
  display:flex;
  justify-content:center;
  align-items:center;
  margin-top:-10;
  margin-bottom:15px;
  animation: float 3s ease-in-out infinite;
}

.logo img{
  max-width:250px;
  filter: drop-shadow(0 10px 20px rgba(0,0,0,0.15));
}

.webname{
  position:relative;
  z-index:2;
  text-align:center;
}

.webname .title{
  font-size:48px;
  font-weight:900;
  background: linear-gradient(90deg, #2a322b, #4a7c59);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  letter-spacing:2px;
  margin-bottom:5px;
}

.webname .subtitle{
  font-size:16px;
  font-weight:500;
  color:#2a322b;
  letter-spacing:3px;
  opacity:0.8;
}

@keyframes float{
  0%{transform:translateY(0px);}
  50%{transform:translateY(-10px);}
  100%{transform:translateY(0px);}
}

.right{
  width:50%;
  display:flex;
  justify-content:center;
  align-items:center;
  background: linear-gradient(135deg, #eafbea, #ffffff);
}

.card{
  width:80%;
  max-width:400px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

h2{
  margin-bottom:20px;
  color:#2a322b;
}

input{
  width:100%;
  padding:12px;
  margin-bottom:15px;
  border-radius:8px;
  border:1px solid #d6e5d6;
  background:#f8fff8;
  color:#2a322b;
  outline:none;
}

input:focus{
  border-color:#a1fa9b;
  box-shadow:0 0 0 2px rgba(161,250,155,0.3);
}

.row{display:flex;gap:10px}
.row input{width:50%}

button{
  width:100%;
  padding:12px;
  background:#2a322b;
  border:none;
  border-radius:8px;
  color:#fff;
  font-weight:bold;
  cursor:pointer;
  transition:0.2s;
}

button:hover{
  background:#a1fa9b;
  color:#1f1f1f;
}

.error{
  color:#e74c3c;
  margin-bottom:10px;
}

.link{
  margin-top:15px;
  font-size:14px;
  color:#555;
}

.link a{
  color:#2a322b;
  text-decoration:none;
  font-weight:bold;
}

small{
  display:block;
  color:#666;
  margin-bottom:10px;
}
</style>
</head>
<body>

<div class="wrapper">
  <div class="left">
    <div class="logo">
      <img src="logo.png" alt="Logo">
    </div>
    <div class="webname">
      <div class="title">EntraQR</div>
      <div class="subtitle">Management System</div>
    </div>
  </div>

  <div class="right">
    <div class="card">
      <h2><?php echo $mode === 'login' ? 'Log in' : 'Create an account'; ?></h2>

      <?php foreach ($errors as $error): ?>
        <div class="error"><?php echo $error; ?></div>
      <?php endforeach; ?>

      <form method="POST">
        <input type="hidden" name="mode" value="<?php echo $mode; ?>">

        <?php if ($mode === 'register'): ?>
          <div class="row">
            <input type="text" name="fname" placeholder="First name" required>
            <input type="text" name="lname" placeholder="Last name" required>
          </div>
        <?php endif; ?>

        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <?php if ($mode === 'register'): ?>
          <small>Use 8+ chars, uppercase, lowercase, number & symbol</small>
        <?php endif; ?>

        <button type="submit">
          <?php echo $mode === 'login' ? 'Log in' : 'Create account'; ?>
        </button>
      </form>

      <div class="link">
        <?php if ($mode === 'login'): ?>
          Don’t have an account? <a href="?mode=register">Create one</a>
        <?php else: ?>
          Have an existing account? <a href="?mode=login">Log in</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</body>
</html>