<?php
session_start();

$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) die("Connection failed");

$page = basename($_SERVER['PHP_SELF']);

function student_attendance_column_exists(mysqli $conn, string $column): bool
{
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$has_time_in = student_attendance_column_exists($conn, 'time_in');
$has_time_out = student_attendance_column_exists($conn, 'time_out');

$student_id = $_GET['student_id'] ?? '';
$subject_id = $_GET['subject_id'] ?? 0;

if ($student_id == '' || $subject_id == 0) {
    die("<h2 style='color:red;text-align:center;'>Invalid Request</h2>");
}

/* STUDENT */
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

/* SUBJECT */
$stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

/* INSERT ATTENDANCE */
if (isset($_POST['status'])) {

    $status = $_POST['status'];

    $timeInInsert = $has_time_in ? ", time_in" : "";
    $timeOutInsert = $has_time_out ? ", time_out" : "";
    $timeInValue = ($has_time_in && $status !== 'Absent') ? ", CURTIME()" : ($has_time_in ? ", NULL" : "");
    $timeOutValue = $has_time_out ? ", NULL" : "";

    $stmt = $conn->prepare("
        INSERT INTO attendance (student_id, subject_id, date, status{$timeInInsert}{$timeOutInsert})
        VALUES (?, ?, CURDATE(), ?{$timeInValue}{$timeOutValue})
    ");

    $stmt->bind_param("sis", $student_id, $subject_id, $status);
    $stmt->execute();
}

/* FETCH ATTENDANCE */
$timeInSelect = $has_time_in ? ", time_in" : ", NULL AS time_in";
$timeOutSelect = $has_time_out ? ", time_out" : ", NULL AS time_out";

$stmt = $conn->prepare("
    SELECT id, student_id, subject_id, date, status{$timeInSelect}{$timeOutSelect}
    FROM attendance
    WHERE student_id = ? AND subject_id = ?
    ORDER BY date DESC
");

$stmt->bind_param("si", $student_id, $subject_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Student Attendance</title>

<style>
body{
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #c8facc);
    overflow:hidden; /* 🔥 STOP PAGE SCROLL */
}

/* SIDEBAR (FIXED) */
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

.sidebar a:hover,
.sidebar a.active{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* MAIN */
.main{
    margin-left:240px; /* 🔥 space for sidebar */

    height:100vh;
    overflow-y:auto; /* 🔥 ONLY THIS SCROLLS */

    padding:30px;
    box-sizing:border-box;
}

/* CONTAINER */
.container{
    max-width:850px;
    margin:auto;

    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);

    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 18px;

    padding: 25px;

    box-shadow: 
        0 10px 35px rgba(0,0,0,0.15),
        inset 0 1px 0 rgba(255,255,255,0.5);
}

h2{
    margin:0;
    color:#2a322b;
}

h4{
    margin-top:5px;
    color:#555;
}

.buttons{
    margin:20px 0;
}

button{
    padding:10px 15px;
    margin-right:10px;

    border:none;
    border-radius:10px;

    cursor:pointer;
    font-weight:bold;

    backdrop-filter: blur(10px);
    transition:0.2s;
}

.present{ background:#a1fa9b; }
.absent{ background:#ff6b6b; color:#fff; }
.late{ background:#ffd166; }

button:hover{
    transform: translateY(-3px);
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;

    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);

    border-radius:12px;
    overflow:hidden;
    box-shadow:0 6px 20px rgba(0,0,0,0.1);
}

th{
    background: rgba(42, 50, 43, 0.9);
    color:#fff;
    padding:12px;
    text-align:left;
}

td{
    padding:12px;
    border-bottom:1px solid rgba(0,0,0,0.1);
}

.back-btn{
    position: absolute;
    top: -10px;
    left: -160px;
    background:#2a322b;
    color:#fff;
    padding:10px 15px;
    border-radius:8px;
    text-decoration:none;
    font-weight:bold;
    transition:0.2s;
}

.back-btn:hover{
    background:#a1fa9b;
    color:#1f1f1f;
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

    <div class="container">

        <h2><?= htmlspecialchars($student['fname'] . " " . $student['lname']) ?></h2>
        <h4><?= htmlspecialchars($subject['subject_name']) ?> Attendance</h4>

        <a href="javascript:history.back()" class="back-btn">← Back</a>

        <!-- BUTTONS -->
        <form method="POST" class="buttons">
            <button class="present" name="status" value="Present">Present</button>
            <button class="absent" name="status" value="Absent">Absent</button>
            <button class="late" name="status" value="Late">Late</button>
        </form>

        <!-- TABLE -->
        <table>
            <tr>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Date</th>
                <th>Status</th>
            </tr>

            <?php if($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['time_in'] ? htmlspecialchars(date('h:i A', strtotime((string) $row['time_in']))) : '--' ?></td>
                        <td><?= $row['time_out'] ? htmlspecialchars(date('h:i A', strtotime((string) $row['time_out']))) : '--' ?></td>
                        <td><?= $row['date'] ?></td>
                        <td><?= $row['status'] ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align:center;">No Attendance Yet</td>
                </tr>
            <?php endif; ?>

        </table>

    </div>

</div>

</body>
</html>
