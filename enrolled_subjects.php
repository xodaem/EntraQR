<?php
session_start();

$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) die("Connection failed");

$page = basename($_SERVER['PHP_SELF']);

$student_id = $_GET['student_id'] ?? '';

if ($student_id == '') {
    die("<h2 style='color:red;text-align:center;'>Invalid Student</h2>");
}

/* GET STUDENT */
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if(!$student){
    die("<h2 style='color:red;text-align:center;'>Student not found</h2>");
}

/* GET SUBJECTS THE STUDENT IS ACTUALLY ENROLLED IN */
$stmt = $conn->prepare("
    SELECT s.id, s.subject_name, s.description
    FROM subjects s
    INNER JOIN enrollments e ON e.subject_id = s.id
    WHERE e.student_id = ?
    ORDER BY subject_name
");

$stmt->bind_param("s", $student_id);
$stmt->execute();
$subjects = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Student Subjects</title>

<style>

/* ðŸ”¥ GLOBAL FIX */
html, body{
    margin:0;
    height:100%;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #c8facc);
    overflow:hidden; /* ONLY MAIN SCROLLS */
}

/* SIDEBAR FIXED */
.sidebar{
    width:240px;
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

/* LINKS */
.sidebar a{
    color:#ccc;
    text-decoration:none;
    padding:10px;
    margin:6px 0;
    border-radius:8px;
}

.sidebar a:hover{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* MAIN SCROLL AREA */
.main{
    margin-left:240px;

    height:100vh;
    overflow-y:auto;

    padding:30px;
    box-sizing:border-box;
}

/* HERO */
.hero{
    text-align:center;
    margin-bottom:30px;
}

.hero h1{
    font-size:40px;
    color:#2a322b;
}

.hero p{
    color:#555;
}

/* ðŸ”¥ SUBJECT GRID (LIKE SUBJECT PAGE) */
.subject-container{
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:20px;

    max-width:1100px;
    margin:auto;
}

/* SMALL GLASS BOX */
.subject-box{
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);

    border-radius:16px;
    padding:20px;

    text-align:center;

    box-shadow:0 8px 25px rgba(0,0,0,0.15);

    cursor:pointer;
    transition:0.25s;

    display:flex;
    flex-direction:column;
    justify-content:center;

    min-height:120px;
}

.subject-box:hover{
    transform: translateY(-8px) scale(1.03);
    background:#a1fa9b;
}

/* TEXT */
.subject-box h3{
    margin:0;
    font-size:18px;
}

.subject-box p{
    font-size:13px;
    color:#444;
    margin-top:6px;
}

</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="profile-box">
        <?php if(!empty($_SESSION['profile_pic'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>"
                 alt="Profile"
                 style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
        <?php else: ?>
            <div class="profile-icon">👤</div>
        <?php endif; ?>
        <div class="profile-text">
            <span style="display:block; font-weight:bold; color:#a1fa9b;"><?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?></span>
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
        <h1><?= htmlspecialchars($student['fname'] . " " . $student['lname']) ?></h1>
        <p>Enrolled Subjects</p>
    </div>

    <div class="subject-container">

        <?php if($subjects->num_rows > 0): ?>
            <?php while($row = $subjects->fetch_assoc()): ?>

                <div class="subject-box"
                     onclick="location.href='student_attendance.php?student_id=<?= $student_id ?>&subject_id=<?= $row['id'] ?>'">

                    <h3><?= htmlspecialchars($row['subject_name']) ?></h3>
                    <p><?= htmlspecialchars($row['description']) ?></p>

                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <h3 style="grid-column:1/-1;text-align:center;">No subjects found</h3>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
