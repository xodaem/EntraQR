<?php
session_start();

$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) die("Connection failed");

$page = basename($_SERVER['PHP_SELF']);

/* GET YEARS */
$years = $conn->query("
    SELECT DISTINCT year_level
    FROM students
    ORDER BY year_level
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Enrolled Students</title>

<style>

/* =========================
   GLOBAL FIX (IMPORTANT)
========================= */
html, body{
    margin:0;
    padding:0;
    height:100%;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #c8facc);
    overflow:hidden; /* 🔥 STOP PAGE SCROLL */
}

/* WRAPPER STYLE (implicit via body layout) */

/* SIDEBAR FIXED */
.sidebar{
    width:240px;
    min-width:240px;
    max-width:240px;
    height:100vh;

    position:fixed;
    top:0;
    left:0;

    background:#2a322b;
    padding:20px;

    display:flex;
    flex-direction:column;

    box-sizing:border-box;
}

/* PROFILE */
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
    color:#fff;
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

/* LINKS */
.sidebar a{
    color:#ccc;
    text-decoration:none;
    padding:10px;
    margin:6px 0;
    border-radius:8px;
    transition:0.2s;
}

.sidebar a:hover,
.sidebar a.active{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* MAIN FIXED AREA */
.main{
    margin-left:240px; /* space for sidebar */

    height:100vh;
    overflow-y:auto; /* ONLY MAIN SCROLLS */

    padding:30px;
    box-sizing:border-box;
}

/* HERO */
.hero{
    text-align:center;
    margin-bottom:30px;
}

.hero h1{
    font-size:42px;
    color:#2a322b;
}

/* YEAR BLOCK */
.year-block{
    max-width:900px;
    margin:auto;
    margin-bottom:30px;
}

/* TITLE */
.year-title{
    font-size:26px;
    font-weight:bold;
    color:#2a322b;
    margin-bottom:15px;
}

/* SECTION BOX */
.section-box{
    background:#ffffff;
    padding:15px 20px;
    margin-bottom:10px;
    border-radius:10px;

    box-shadow:0 4px 12px rgba(0,0,0,0.1);

    cursor:pointer;
    transition:0.2s;
}

.section-box:hover{
    background:#a1fa9b;
    transform: translateY(-5px);
}

/* COUNT */
.count{
    float:right;
    font-weight:bold;
    color:#2a322b;
}

</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="profile-box">
        <?php if(!empty($_SESSION['profile_pic'])): ?>
            <img src="uploads/<?php echo $_SESSION['profile_pic']; ?>" 
                 style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
        <?php else: ?>
            <div class="profile-icon">👤</div>
        <?php endif; ?>
        <div class="profile-text">
            <span style="font-weight:bold; color:#a1fa9b;"><?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?></span>
            <span><?= htmlspecialchars($_SESSION['email'] ?? 'Admin') ?></span>
        </div>
    </div>

    <a href="homepage.php" class="<?= $page === 'homepage.php' ? 'active' : '' ?>">Homepage</a>
    <a href="student_dashboard.php" class="<?= $page === 'student_dashboard.php' ? 'active' : '' ?>">Students</a>
    <a href="section.php" class="<?= $page === 'section.php' || $page === 'section_subjects.php' || $page === 'section_students.php' ? 'active' : '' ?>">Sections</a>
    <a href="subjects_dashboard.php" class="<?= $page === 'subjects_dashboard.php' || $page === 'subject_students.php' ? 'active' : '' ?>">Subjects</a>
    <a href="attendance_dashboard.php" class="<?= $page === 'attendance_dashboard.php' || $page === 'student_attendance.php' ? 'active' : '' ?>">Attendance</a>
    <a href="enrollement.php" class="<?= $page === 'enrollement.php' || $page === 'enrolled_subjects.php' ? 'active' : '' ?>">Enrollment</a>
    <a href="Manage Profile.php" class="<?= $page === 'Manage Profile.php' ? 'active' : '' ?>">Manage Profile</a>

    <a href="login.php" style="margin-top:auto;color:#ff6b6b;">Logout</a>

</div>

<!-- MAIN -->
<div class="main">

    <div class="hero">
        <h1>Enrolled Students</h1>
    </div>

    <?php while($year = $years->fetch_assoc()): ?>

        <div class="year-block">

            <div class="year-title">
                Year <?= $year['year_level'] ?>
            </div>

            <?php
            $stmt = $conn->prepare("
                SELECT section_name, COUNT(*) as total
                FROM students
                WHERE year_level = ?
                GROUP BY section_name
            ");
            $stmt->bind_param("i", $year['year_level']);
            $stmt->execute();
            $sections = $stmt->get_result();
            ?>

            <?php while($sec = $sections->fetch_assoc()): ?>

                <div class="section-box"
                     onclick="location.href='section_students.php?year=<?= $year['year_level'] ?>&section=<?= urlencode($sec['section_name']) ?>'">

                    <?= htmlspecialchars($sec['section_name']) ?>

                    <span class="count">
                        <?= $sec['total'] ?> students
                    </span>
                </div>

            <?php endwhile; ?>

        </div>

    <?php endwhile; ?>

</div>

</body>
</html>
