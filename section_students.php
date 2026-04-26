<?php
session_start();

// 1. Connection with correct database name
$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) die("Connection failed");

$page = basename($_SERVER['PHP_SELF']);

$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$section = isset($_GET['section']) ? trim($_GET['section']) : '';

if ($year < 1 || $section == '') {
    die("<h2 style='color:red;text-align:center;'>Invalid Section</h2>");
}

/* GET LOGGED USER (SIDEBAR PROFILE FIX) */
$user_id = $_SESSION['user_id'] ?? 0;

$user = [
    'email' => 'Admin',
    'profile_pic' => '' // Changed from 'image'
];

if ($user_id > 0) {
    // FIX: Line 25 - Changed 'image' to 'profile_pic'
    $resUser = $conn->query("SELECT email, profile_pic FROM users WHERE id='$user_id'");
    if ($resUser && $resUser->num_rows > 0) {
        $user = $resUser->fetch_assoc();
    }
}

/* GET DEFAULT SUBJECT */
$subject_id = 0;

$stmtSub = $conn->prepare("
    SELECT id 
    FROM subjects 
    WHERE year_level = ? AND section_name = ?
    LIMIT 1
");
$stmtSub->bind_param("is", $year, $section);
$stmtSub->execute();
$subRes = $stmtSub->get_result();

if ($rowSub = $subRes->fetch_assoc()) {
    $subject_id = $rowSub['id'];
}

/* GET STUDENTS */
$stmt = $conn->prepare("
    SELECT student_id, fname, lname, year_level, section_name
    FROM students
    WHERE year_level = ? AND section_name = ?
    ORDER BY lname
");

$stmt->bind_param("is", $year, $section);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Students</title>

<style>
body{
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #c8facc);
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

/* MAIN */
.main{
    flex:1;
    height:100vh;
    padding:30px;
    overflow-y:auto;
    box-sizing:border-box;
}

.hero{
    text-align:center;
    margin-bottom:30px;
}

.hero h1{
    font-size:42px;
    color:#2a322b;
}

.hero p{
    color:#555;
}

/* TABLE */
.table-container{
    max-width:900px;
    margin:auto;
    background: rgba(255,255,255,0.25);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-radius:16px;
    padding:20px;
    box-shadow:0 10px 30px rgba(0,0,0,0.12);
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#2a322b;
    color:#fff;
    padding:12px;
}

td{
    padding:12px;
    border-bottom:1px solid rgba(0,0,0,0.1);
}

tr:hover{
    background:#a1fa9b;
    cursor:pointer;
}
</style>
</head>

<body>

<div class="app">

<div class="sidebar">

    <div class="profile-box">

        <?php if(!empty($user['profile_pic'])): ?>
            <img src="uploads/<?= $user['profile_pic'] ?>" 
                 style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
        <?php elseif(!empty($_SESSION['profile_pic'])): ?>
            <img src="uploads/<?php echo $_SESSION['profile_pic']; ?>" 
                 style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
        <?php else: ?>
            <div class="profile-icon">👤</div>
        <?php endif; ?>

        <div class="profile-text">
            <span style="display:block; font-weight:bold; color:#a1fa9b;"><?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?></span>
            <span><?= htmlspecialchars($user['email']) ?></span>
        </div>

    </div>

    <a href="homepage.php" class="<?= $page === 'homepage.php' ? 'active' : '' ?>">Homepage</a>
    <a href="student_dashboard.php" class="<?= $page === 'student_dashboard.php' ? 'active' : '' ?>">Students</a>
    <a href="section.php" class="<?= $page === 'section.php' || $page === 'section_subjects.php' || $page === 'section_students.php' ? 'active' : '' ?>">Sections</a>
    <a href="subjects_dashboard.php" class="<?= $page === 'subjects_dashboard.php' || $page === 'subject_students.php' ? 'active' : '' ?>">Subjects</a>
    <a href="attendance_dashboard.php" class="<?= $page === 'attendance_dashboard.php' || $page === 'student_attendance.php' ? 'active' : '' ?>">Attendance</a>
    <a href="enrollement.php" class="<?= $page === 'enrollement.php' || $page === 'enrolled_subjects.php' ? 'active' : '' ?>">Enrollment</a>
    <a href="Manage Profile.php" class="<?= $page === 'Manage Profile.php' ? 'active' : '' ?>">Manage Profile</a>

</div>

<div class="main">

    <div class="hero">
        <h1>Students</h1>
        <p>Year <?= $year ?> - Section <?= htmlspecialchars($section) ?></p>
    </div>

    <div class="table-container">

        <table>

            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Year & Section</th>
            </tr>

            <?php while($row = $result->fetch_assoc()): ?>

                <tr onclick="location.href='enrolled_subjects.php?student_id=<?= $row['student_id'] ?>&subject_id=<?= $subject_id ?>'">

                    <td><?= htmlspecialchars($row['student_id']) ?></td>

                    <td><?= htmlspecialchars($row['fname'] . " " . $row['lname']) ?></td>

                    <td>Year <?= $row['year_level'] ?> - <?= htmlspecialchars($row['section_name']) ?></td>

                </tr>

            <?php endwhile; ?>

        </table>

    </div>

</div>

</div>

</body>
</html>
