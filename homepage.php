<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #5eec6a96);
    overflow:hidden;
}

.app{
    display:flex;
    height:100vh;
}

/* SIDEBAR */
.sidebar{
    width:240px;
    min-width:240px;
    max-width:240px;
    background:#2a322b;
    padding:20px;
    display:flex;
    flex-direction:column;
    height:100vh;
    box-sizing:border-box;
}

.profile-box{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    min-height:64px;
    background: rgba(255,255,255,0.05);
    border-radius:12px;
    margin-bottom:25px;
}

.profile-icon{
    width:40px;
    height:40px;
    border-radius:50%;
    background:#444;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
}

.profile-text{
    min-width:0;
    flex:1;
}

.profile-text span{
    display:block;
    color:#fff;
    font-size:12px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.sidebar a{
    color:#ccc;
    text-decoration:none;
    padding:10px;
    margin:6px 0;
    border-radius:8px;
    transition:0.2s;
}

.sidebar a:hover, .sidebar a.active{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* MAIN */
.main{
    flex:1;
    display:flex;
    flex-direction:column;
    height:100vh;
    overflow-y:auto;
}

/* TOPBAR */
.topbar{
    height:60px;
    display:flex;
    justify-content:center;
    align-items:center;
    background: rgba(27, 25, 25, 0.18);
    backdrop-filter: blur(10px);
    border-bottom:1px solid rgba(255,255,255,0.05);
}

/* HERO */
.hero{
    text-align:center;
    padding:40px 20px 20px;
}

.hero h1{
    font-size:38px;
    letter-spacing:2px;
}

.hero p{
    margin-top:15px;
    color:#555;
    max-width:600px;
    margin-left:auto;
    margin-right:auto;
    line-height:1.5;
}

/* CARDS */
.cards{
    display:flex;
    justify-content:center;
    gap:20px;
    padding:30px;
    flex-wrap:wrap;
}

.card{
    width:250px;
    padding:20px;
    background: rgba(35, 43, 33, 0.12);
    backdrop-filter: blur(12px);
    border-radius:16px;
    text-align:center;
    transition:0.3s;
}

.card:hover{
    transform:translateY(-5px);
    background: rgba(255,255,255,0.08);
}

.card button{
    margin-top:15px;
    padding:8px 14px;
    border:none;
    border-radius:8px;
    background:#a1fa9b;
    color:#1f1f1f;
    cursor:pointer;
}

.icon{
    font-size:40px;
}
</style>
</head>

<body>

<div class="app">

<div class="sidebar">

    <div class="profile-box">
        <?php if(!empty($_SESSION['profile_pic'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" 
                 alt="Profile"
                 style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
        <?php else: ?>
            <div class="profile-icon">👤</div>
        <?php endif; ?>

        <div class="profile-text">
            <span style="display:block; font-weight:bold; color:#a1fa9b;"><?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?></span>
            <span><?= htmlspecialchars($_SESSION['email'] ?? 'no-email') ?></span>
        </div>
    </div>

    <a href="homepage.php" class="<?= $page === 'homepage.php' ? 'active' : '' ?>">Homepage</a>
    <a href="student_dashboard.php" class="<?= $page === 'student_dashboard.php' ? 'active' : '' ?>">Students</a>
    <a href="section.php" class="<?= $page === 'section.php' || $page === 'section_subjects.php' || $page === 'section_students.php' ? 'active' : '' ?>">Sections</a>
    <a href="subjects_dashboard.php" class="<?= $page === 'subjects_dashboard.php' || $page === 'subject_students.php' ? 'active' : '' ?>">Subjects</a>
    <a href="attendance_dashboard.php" class="<?= $page === 'attendance_dashboard.php' || $page === 'student_attendance.php' ? 'active' : '' ?>">Attendance</a>
    <a href="enrollement.php" class="<?= $page === 'enrollement.php' || $page === 'enrolled_subjects.php' ? 'active' : '' ?>">Enrollment</a>
    <a href="Manage Profile.php" class="<?= $page === 'Manage Profile.php' ? 'active' : '' ?>">Manage Profile</a>

    <a href="login.php" style="margin-top:auto; color:#ff6b6b; font-weight:bold;">Logout</a>
</div>

<div class="main">
    <div class="topbar">
        <h3>EntraQR Management System</h3>
    </div>

    <div class="hero">
        <?php $fname = strtoupper($_SESSION['fname'] ?? 'USER'); ?>
        <h1>WELCOME, <?php echo $fname; ?>!</h1>
        <p>
            This Management System allows administrators to manage subjects,
            monitor attendance, and handle user records in a centralized and secure platform.
        </p>
    </div>

    <div class="cards">
        <div class="card">
            <div class="icon">📘</div>
            <h3>Subjects</h3>
            <p>Manage and organize academic subjects efficiently.</p>
            <a href="subjects_dashboard.php"><button>Open</button></a>
        </div>

        <div class="card">
            <div class="icon">👥</div>
            <h3>Students</h3>
            <p>View and manage registered students.</p>
            <a href="student_dashboard.php"><button>Open</button></a>
        </div>

        <div class="card">
            <div class="icon">📝</div>
            <h3>Enrollment</h3>
            <p>Monitor system activity and reports.</p>
            <a href="enrollement.php"><button>Open</button></a>
        </div>

        <div class="card">
            <div class="icon">📊</div>
            <h3>Attendance</h3>
            <p>Open sections, choose a subject, and monitor enrolled student attendance.</p>
            <a href="attendance_dashboard.php"><button>Open</button></a>
        </div>
    </div>
</div>

</div>

</body>
</html>
