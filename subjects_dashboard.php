<?php
session_start();

$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) die("Connection failed");

$page = basename($_SERVER['PHP_SELF']);
$selected_semester = isset($_GET['semester']) && $_GET['semester'] === '2nd Semester'
    ? '2nd Semester'
    : '1st Semester';

function ensure_subjects_semester_column(mysqli $conn): void
{
    $result = $conn->query("SHOW COLUMNS FROM subjects LIKE 'semester'");
    if ($result instanceof mysqli_result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE subjects ADD COLUMN semester VARCHAR(20) NOT NULL DEFAULT '1st Semester' AFTER description");
    }
}

ensure_subjects_semester_column($conn);

$stmtYears = $conn->prepare("
    SELECT DISTINCT year_level 
    FROM subjects
    WHERE semester = ?
    ORDER BY year_level
");
$stmtYears->bind_param("s", $selected_semester);
$stmtYears->execute();
$years = $stmtYears->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Subjects Dashboard</title>

<style>

/* ✅ GLOBAL FIX (IMPORTANT) */
body{
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #c8facc);

    overflow:hidden; /* ONLY MAIN WILL SCROLL */
}

/* ✅ NEW WRAPPER (DOES NOT CHANGE LOOK) */
.app{
    display:flex;
    height:100vh;
}

/* SIDEBAR (UNCHANGED VISUALLY, JUST FIXED BEHAVIOR) */
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

.sidebar a{
    color:#ccc;
    text-decoration:none;
    padding:10px;
    margin:6px 0;
    border-radius:8px;
}

.sidebar a:hover,
.sidebar a.active{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* MAIN (ONLY SCROLL AREA) */
.main{
    flex:1;
    padding:30px;
    overflow-y:auto; /* 🔥 THIS FIXES YOUR SCROLL ISSUE */
}

/* HERO */
.hero{
    text-align:center;
    margin-bottom:30px;
}

.hero h1{
    font-size:42px;
    color:#2e2e2e;
}

.hero p{
    color:#4a4a4a;
    font-size:18px;
    margin-top:8px;
}

.semester-toggle{
    max-width:900px;
    margin:0 auto 24px;
    display:flex;
    justify-content:center;
    gap:12px;
    flex-wrap:wrap;
}

.semester-link{
    display:inline-block;
    padding:10px 18px;
    border-radius:999px;
    text-decoration:none;
    font-weight:bold;
    background:rgba(255,255,255,0.45);
    color:#2a322b;
    border:1px solid rgba(42,50,43,0.08);
    transition:0.2s ease;
}

.semester-link:hover,
.semester-link.active{
    background:#2a322b;
    color:#fff;
}

/* YEAR BLOCK */
.year-block{
    max-width:900px;
    margin:auto;
    margin-bottom:40px;
}

/* YEAR HEADER */
.year-title{
    font-size:28px;
    font-weight:bold;
    color:#2a322b;
    margin-bottom:15px;
}

/* SECTION BOX (UNCHANGED STYLE) */
.section-box{
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);

    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 16px;

    padding: 18px 22px;
    margin-bottom: 15px;

    box-shadow: 
        0 8px 28px rgba(0, 0, 0, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.5);

    transition: all 0.3s ease;

    cursor: pointer;
}

.section-box:hover{
    transform: translateY(-8px);
    box-shadow:
        0 16px 40px rgba(0, 0, 0, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

/* SECTION TITLE */
.section-title{
    font-weight:bold;
    color:#1f1f1f;
    margin-bottom:8px;
}

/* SUBJECT LIST */
.subject-list{
    margin-left:15px;
    color:#444;
    line-height:1.6;
}

</style>
</head>

<body>

<div class="app">

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
            <span><?= htmlspecialchars($_SESSION['email'] ?? 'Professor') ?></span>
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
        <h1>Subjects Overview</h1>
        <p><?= htmlspecialchars($selected_semester) ?></p>
    </div>

    <div class="semester-toggle">
        <a class="semester-link <?= $selected_semester === '1st Semester' ? 'active' : '' ?>" href="subjects_dashboard.php?semester=<?= urlencode('1st Semester') ?>">1st Semester</a>
        <a class="semester-link <?= $selected_semester === '2nd Semester' ? 'active' : '' ?>" href="subjects_dashboard.php?semester=<?= urlencode('2nd Semester') ?>">2nd Semester</a>
    </div>

    <?php while($year = $years->fetch_assoc()): ?>

        <div class="year-block">

            <div class="year-title">
                Year <?= $year['year_level'] ?>
            </div>

            <?php
            $stmt = $conn->prepare("
                SELECT DISTINCT section_name 
                FROM subjects 
                WHERE year_level = ? AND semester = ?
            ");
            $stmt->bind_param("is", $year['year_level'], $selected_semester);
            $stmt->execute();
            $sections = $stmt->get_result();
            ?>

            <?php while($sec = $sections->fetch_assoc()): ?>

                <div class="section-box"
                     onclick="location.href='section_subjects.php?year=<?= $year['year_level'] ?>&section=<?= urlencode($sec['section_name']) ?>&semester=<?= urlencode($selected_semester) ?>'">

                    <div class="section-title">
                        <?= htmlspecialchars($sec['section_name']) ?> - SUBJECTS
                    </div>

                    <?php
                    $stmt2 = $conn->prepare("
                        SELECT subject_name 
                        FROM subjects 
                        WHERE year_level = ? AND section_name = ? AND semester = ?
                    ");
                    $stmt2->bind_param("iss", $year['year_level'], $sec['section_name'], $selected_semester);
                    $stmt2->execute();
                    $subjects = $stmt2->get_result();
                    ?>

                    <div class="subject-list">
                        <?php while($sub = $subjects->fetch_assoc()): ?>
                            • <?= htmlspecialchars($sub['subject_name']) ?><br>
                        <?php endwhile; ?>
                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php endwhile; ?>

</div>

</div>

</body>
</html>
