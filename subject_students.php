<?php
session_start();

$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) die("Connection failed");

// GET SUBJECT ID
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

/* ---------------------------
   GET SUBJECT INFO
----------------------------*/
$stmt = $conn->prepare("SELECT year_level, section_name, subject_name FROM subjects WHERE id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$subjectResult = $stmt->get_result();
$subject = $subjectResult->fetch_assoc();

$year = $subject['year_level'] ?? 0;
$section = $subject['section_name'] ?? '';
$subject_name = $subject['subject_name'] ?? '';

/* ---------------------------
   GET ENROLLED STUDENTS
----------------------------*/
$stmt = $conn->prepare("
    SELECT s.student_id, s.fname, s.lname, s.year_level, s.section_name
    FROM students s
    JOIN enrollments e ON s.student_id = e.student_id
    WHERE e.subject_id = ?
");

$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html>
<head>
<title>Students - <?= $year ?> <?= htmlspecialchars($section) ?></title>

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

/* SIDEBAR (same style) */
.sidebar{
    width:240px;
    min-width:240px;
    max-width:240px;

    background:#2a322b;
    padding:20px;

    display:flex;
    flex-direction:column;

    height:100vh; /* IMPORTANT */
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
    transition:0.2s;
}

.sidebar a:hover, .sidebar a.active{
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
    color:#2e2e2e;
}

.hero p{
    font-size:19px;
    color:#444;
}

/* TABLE STYLE */
.table-container{
    max-width:900px;
    margin:0 auto;
    background:#fff;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
    overflow:hidden;
}

table{
    width:100%;
    border-collapse:collapse;
}

th, td{
    padding:15px;
    text-align:left;
}

th{
    background:#2a322b;
    color:#fff;
}

tr:nth-child(even){
    background:#f4f4f4;
}

tr:hover{
    background:#a1fa9b;
    transition:0.2s;
}

/* BACK BUTTON */
.back-btn{
    position:absolute;
    top:20px;
    left:300px;
    background:#2a322b;
    color:#fff;
    padding:10px 15px;
    border-radius:8px;
    text-decoration:none;
}
.back-btn:hover{
    background:#a1fa9b;
    color:#1f1f1f;
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
            <span style="display:block; font-weight:bold; color:#a1fa9b;"><?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?></span>
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
        <h1>Student List</h1>
        <p><?= $year ?><?= ['st','nd','rd','th'][$year-1] ?? 'th' ?> Year - Section <?= htmlspecialchars($section) ?></p>
    </div>

    <a href="javascript:history.back()" class="back-btn">← Back</a>

    <div class="table-container">
        <table>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Year & Section</th>
            </tr>

            <?php if($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
<tr onclick="location.href='student_attendance.php?student_id=<?= $row['student_id'] ?>&subject_id=<?= $subject_id ?>'"
    style="cursor:pointer;">
    
    <td><?= htmlspecialchars($row['student_id']) ?></td>
    <td><?= htmlspecialchars($row['fname'] . " " . $row['lname']) ?></td>
    <td><?= $row['year_level'] ?> - <?= htmlspecialchars($row['section_name']) ?></td>
</tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align:center;">No Students Found</td>
                </tr>
            <?php endif; ?>

        </table>
    </div>

</div>

</div>

</body>
</html>
