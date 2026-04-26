<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page = basename($_SERVER['PHP_SELF']);
$year = isset($_GET['year']) ? (int) $_GET['year'] : 0;
$section = isset($_GET['section']) ? trim($_GET['section']) : '';
$subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
$export_excel = isset($_GET['export']) && $_GET['export'] === 'excel';

// Set default date to today's date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

function attendance_column_exists(mysqli $conn, string $column): bool
{
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$has_time_in = attendance_column_exists($conn, 'time_in');
$has_time_out = attendance_column_exists($conn, 'time_out');

$selected_subject = null;
if ($subject_id > 0) {
    $stmt = $conn->prepare("
        SELECT id, year_level, section_name, subject_name, description
        FROM subjects
        WHERE id = ?
    ");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $selected_subject = $stmt->get_result()->fetch_assoc();

    if ($selected_subject) {
        $year = (int) $selected_subject['year_level'];
        $section = $selected_subject['section_name'];
    }
}

$years = $conn->query("
    SELECT DISTINCT year_level
    FROM sections
    ORDER BY year_level
");

$subjects = null;
if ($year > 0 && $section !== '' && $subject_id === 0) {
    $stmt = $conn->prepare("
        SELECT id, subject_name, description
        FROM subjects
        WHERE year_level = ? AND section_name = ?
        ORDER BY subject_name
    ");
    $stmt->bind_param("is", $year, $section);
    $stmt->execute();
    $subjects = $stmt->get_result();
}

$attendance_rows = [];
if ($selected_subject) {
    $timeInSelect = $has_time_in ? 'a.time_in' : 'NULL AS time_in';
    $timeOutSelect = $has_time_out ? 'a.time_out' : 'NULL AS time_out';

    // SQL now strictly filters by the selected date (defaults to today)
    $sql = "
        SELECT
            s.student_id,
            s.fname,
            s.lname,
            a.date,
            a.status,
            {$timeInSelect},
            {$timeOutSelect}
        FROM enrollments e
        INNER JOIN students s
            ON s.student_id = e.student_id
        INNER JOIN attendance a
            ON a.student_id = e.student_id
            AND a.subject_id = e.subject_id
            AND a.date = ?
        WHERE e.subject_id = ?
        ORDER BY s.lname, s.fname, a.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $selected_date, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_rows[] = $row;
    }
}

if ($export_excel && $selected_subject) {
    $safeSubject = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $selected_subject['subject_name']);
    $filename = 'attendance_' . trim($safeSubject, '_') . '_' . $selected_date . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output !== false) {
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [
            'Student Name',
            'Student ID',
            'Time In',
            'Time Out',
            'Date',
            'Status'
        ]);

        foreach ($attendance_rows as $row) {
            fputcsv($output, [
                $row['fname'] . ' ' . $row['lname'],
                $row['student_id'],
                $row['time_in'] ? date('h:i A', strtotime((string) $row['time_in'])) : '--',
                $row['time_out'] ? date('h:i A', strtotime((string) $row['time_out'])) : '--',
                $row['date'] ?: '--',
                $row['status'] ?: 'No record'
            ]);
        }

        fclose($output);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance Dashboard</title>
<style>
body{
    margin:0;
    font-family:Arial, sans-serif;
    background:linear-gradient(135deg, #ffffff, #c8facc);
    overflow:hidden;
}

.app{
    display:flex;
    height:100vh;
}

.sidebar{
    width:240px;
    min-width:240px;
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
    background:rgba(255,255,255,0.05);
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

.main{
    flex:1;
    height:100vh;
    overflow-y:auto;
    padding:30px;
    box-sizing:border-box;
}

.hero{
    text-align:center;
    margin-bottom:30px;
}

.hero h1{
    font-size:42px;
    color:#2e2e2e;
    margin-bottom:10px;
}

.hero p{
    color:#4a4a4a;
    max-width:720px;
    margin:0 auto;
    line-height:1.5;
}

.year-block,
.content-panel{
    max-width:980px;
    margin:0 auto 30px;
}

.year-title{
    font-size:28px;
    font-weight:bold;
    color:#2a322b;
    margin-bottom:15px;
}

.section-grid,
.subject-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
    gap:16px;
}

.section-box,
.subject-box{
    display:block;
    background:rgba(255,255,255,0.35);
    color:#1f1f1f;
    text-decoration:none;
    border:1px solid rgba(255,255,255,0.45);
    border-radius:16px;
    padding:20px;
    box-shadow:0 8px 28px rgba(0,0,0,0.12);
    backdrop-filter:blur(14px);
    transition:0.25s ease;
}

.section-box:hover,
.subject-box:hover{
    transform:translateY(-6px);
    background:rgba(161,250,155,0.55);
}

.section-box strong,
.subject-box strong{
    display:block;
    font-size:20px;
    margin-bottom:8px;
}

.section-box span,
.subject-box span{
    color:#4f4f4f;
    line-height:1.5;
}

.toolbar{
    max-width:980px;
    margin:0 auto 20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.back-link{
    display:inline-block;
    padding:10px 16px;
    border-radius:10px;
    background:#2a322b;
    color:#fff;
    text-decoration:none;
    font-weight:bold;
}

.back-link:hover{
    background:#a1fa9b;
    color:#1f1f1f;
}

.toolbar-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.export-link{
    display:inline-block;
    padding:10px 16px;
    border-radius:10px;
    background:#a1fa9b;
    color:#1f1f1f;
    text-decoration:none;
    font-weight:bold;
}

.export-link:hover{
    background:#87ea81;
}

.summary{
    color:#2a322b;
    font-weight:bold;
}

.table-card{
    background:rgba(255,255,255,0.4);
    border:1px solid rgba(255,255,255,0.5);
    border-radius:18px;
    box-shadow:0 10px 35px rgba(0,0,0,0.14);
    padding:20px;
    overflow:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:760px;
}

th, td{
    padding:14px 12px;
    text-align:left;
    border-bottom:1px solid rgba(0,0,0,0.08);
}

th{
    background:#2a322b;
    color:#fff;
}

tr:nth-child(even) td{
    background:rgba(255,255,255,0.35);
}

.empty{
    padding:30px 20px;
    text-align:center;
    color:#555;
    background:rgba(255,255,255,0.4);
    border-radius:16px;
    box-shadow:0 8px 24px rgba(0,0,0,0.08);
}

.status-pill{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:bold;
    background:#ebf6eb;
    color:#2a322b;
}
</style>
</head>
<body>
<div class="app">
    <div class="sidebar">
        <div class="profile-box">
            <?php if (!empty($_SESSION['profile_pic'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>"
                     alt="Profile"
                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
            <?php else: ?>
                <div class="profile-icon">A</div>
            <?php endif; ?>
            <div class="profile-text">
                <span style="font-weight:bold;color:#a1fa9b;"><?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?></span>
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

        <a href="login.php" style="margin-top:auto;color:#ff6b6b;font-weight:bold;">Logout</a>
    </div>

    <div class="main">
        <?php if ($selected_subject): ?>
            <div class="hero">
                <h1>Attendance Monitoring</h1>
                <p>
                    <?= htmlspecialchars($selected_subject['subject_name']) ?> for
                    Year <?= $year ?> - <?= htmlspecialchars($section) ?>
                </p>
            </div>

            <div class="toolbar">
                <a class="back-link" href="attendance_dashboard.php?year=<?= $year ?>&section=<?= urlencode($section) ?>">Back to Subjects</a>

                <div class="toolbar-actions">
                    <a class="export-link" href="attendance_dashboard.php?subject_id=<?= $subject_id ?>&date=<?= urlencode($selected_date) ?>&export=excel">Export Excel</a>

                    <form method="GET" action="attendance_dashboard.php" style="display:flex; align-items:center; gap:10px;">
                        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                        <span style="font-weight:bold; color:#2a322b;">Date:</span>
                        <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" 
                               onchange="this.form.submit()" 
                               style="padding:5px; border-radius:5px; border:1px solid #ccc; font-family:inherit;">
                    </form>
                </div>
            </div>

            <div class="content-panel table-card">
                <table>
                    <tr>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                    <?php if (!empty($attendance_rows)): ?>
                        <?php foreach ($attendance_rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
                                <td><?= htmlspecialchars($row['student_id']) ?></td>
                                <td><?= $row['time_in'] ? htmlspecialchars(date('h:i A', strtotime((string) $row['time_in']))) : '--' ?></td>
                                <td><?= $row['time_out'] ? htmlspecialchars(date('h:i A', strtotime((string) $row['time_out']))) : '--' ?></td>
                                <td><?= $row['date'] ? htmlspecialchars($row['date']) : '--' ?></td>
                                <td><span class="status-pill"><?= htmlspecialchars($row['status'] ?: 'No record') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No attendance records found for this date.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        <?php elseif ($year > 0 && $section !== ''): ?>
            <div class="hero">
                <h1>Attendance Monitoring</h1>
                <p>Select a subject from Year <?= $year ?> - <?= htmlspecialchars($section) ?> to open its attendance table.</p>
            </div>

            <div class="toolbar">
                <a class="back-link" href="attendance_dashboard.php">Back to Sections</a>
                <div class="summary">Year <?= $year ?> - <?= htmlspecialchars($section) ?></div>
            </div>

            <div class="content-panel">
                <?php if ($subjects && $subjects->num_rows > 0): ?>
                    <div class="subject-grid">
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <a class="subject-box" href="attendance_dashboard.php?subject_id=<?= (int) $subject['id'] ?>">
                                <strong><?= htmlspecialchars($subject['subject_name']) ?></strong>
                                <span><?= htmlspecialchars($subject['description'] ?: 'Open attendance records for this subject.') ?></span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="hero">
                <h1>Attendance Monitoring</h1>
                <p>Start by choosing a section.</p>
            </div>

            <?php while ($year_row = $years->fetch_assoc()): ?>
                <div class="year-block">
                    <div class="year-title">Year <?= (int) $year_row['year_level'] ?></div>
                    <?php
                    $stmt = $conn->prepare("SELECT section_name FROM sections WHERE year_level = ? ORDER BY section_name");
                    $stmt->bind_param("i", $year_row['year_level']);
                    $stmt->execute();
                    $sections = $stmt->get_result();
                    ?>
                    <div class="section-grid">
                        <?php while ($section_row = $sections->fetch_assoc()): ?>
                            <a class="section-box" href="attendance_dashboard.php?year=<?= (int) $year_row['year_level'] ?>&section=<?= urlencode($section_row['section_name']) ?>">
                                <strong><?= htmlspecialchars($section_row['section_name']) ?></strong>
                                <span>Open this section to view its subjects and attendance records.</span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
