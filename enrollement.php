<?php
session_start();
require_once 'db.php';
require_once 'enrollment_shared.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page = basename($_SERVER['PHP_SELF']);
[$message, $message_type] = enrollment_take_flash();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$selected_semester = isset($_GET['semester']) && $_GET['semester'] === '2nd Semester'
    ? '2nd Semester'
    : '1st Semester';
$allowed_statuses = ['all', 'enrolled', 'not_enrolled'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $search = trim($_POST['search'] ?? $search);
    $status = $_POST['status'] ?? $status;
    $selected_semester = ($_POST['semester'] ?? $selected_semester) === '2nd Semester'
        ? '2nd Semester'
        : '1st Semester';

    if ($action === 'add_student') {
        [$ok, $flash, $studentId] = enrollment_create_student($conn, $_POST);
        enrollment_set_flash($flash, $ok ? 'success' : 'error');
        enrollment_redirect('enrollement.php', [
            'student_id' => $studentId ?? '',
            'search' => $search,
            'status' => $status,
            'semester' => $selected_semester
        ]);
    }

    $student_id = trim($_POST['student_id'] ?? '');
    $student = enrollment_fetch_student($conn, $student_id);

    if (!$student) {
        enrollment_set_flash('Student not found.', 'error');
        enrollment_redirect('enrollement.php', [
            'search' => $search,
            'status' => $status,
            'semester' => $selected_semester
        ]);
    }

    if ($action === 'enroll') {
        [$ok, $flash] = enrollment_add_subjects($conn, $student, $_POST['subject_ids'] ?? [], $selected_semester);
        enrollment_set_flash($flash, $ok ? 'success' : 'error');
    } elseif ($action === 'remove') {
        [$ok, $flash] = enrollment_remove_subject($conn, $student_id, (int)($_POST['subject_id'] ?? 0));
        enrollment_set_flash($flash, $ok ? 'success' : 'error');
    }

    enrollment_redirect('enrollement.php', [
        'student_id' => $student_id,
        'search' => $search,
        'status' => $status,
        'semester' => $selected_semester
    ]);
}

$students = enrollment_fetch_students($conn, $search, $status);
$selected_student_id = trim($_GET['student_id'] ?? '');

if ($selected_student_id === '' && !empty($students)) {
    $selected_student_id = $students[0]['student_id'];
}

$selected_student = $selected_student_id !== '' ? enrollment_fetch_student($conn, $selected_student_id) : null;
$subjects = $selected_student ? enrollment_fetch_subjects($conn, $selected_student, $selected_semester) : [];
$enrolled_subjects = array_values(array_filter($subjects, static function ($subject) {
    return (int)$subject['is_enrolled'] === 1;
}));
$sections = enrollment_fetch_sections($conn);

$portal_link = 'enrollment_portal.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enrollment</title>
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background: linear-gradient(135deg, #ffffff, #c8facc);
    overflow:hidden;
}

.app{
    display:flex;
    height:100vh;
}

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
    padding:28px;
    overflow-y:auto;
    box-sizing:border-box;
}

.hero{
    text-align:center;
    margin-bottom:22px;
}

.hero h1{
    font-size:40px;
    color:#203022;
}

.hero p{
    margin-top:10px;
    color:#596159;
}

.semester-toggle{
    max-width:1180px;
    margin:0 auto 18px;
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
    background:rgba(255,255,255,0.55);
    color:#2a322b;
    border:1px solid rgba(42,50,43,0.08);
    transition:0.2s ease;
}

.semester-link:hover,
.semester-link.active{
    background:#2a322b;
    color:#fff;
}

.notice,
.share-box,
.add-student-box,
.student-list,
.details-box{
    max-width:1180px;
    margin:0 auto 20px;
    background:rgba(255,255,255,0.45);
    backdrop-filter: blur(12px);
    border-radius:20px;
    box-shadow:0 12px 32px rgba(0,0,0,0.08);
}

.notice{
    padding:14px 18px;
    font-weight:bold;
}

.notice.success{
    color:#1d5f20;
    background:#dff7dd;
}

.notice.error{
    color:#8a1f1f;
    background:#ffe2e2;
}

.share-box,
.add-student-box,
.student-list,
.details-box{
    padding:22px;
}

.share-box code{
    display:block;
    margin-top:10px;
    padding:12px;
    background:#f5fff4;
    border-radius:10px;
    overflow:auto;
}

.search-row{
    display:grid;
    grid-template-columns: 1.4fr 180px auto;
    gap:12px;
    align-items:end;
    margin-top:14px;
}

.field label{
    display:block;
    font-size:13px;
    font-weight:bold;
    color:#2a322b;
    margin-bottom:8px;
}

.field input,
.field select{
    width:100%;
    padding:12px;
    border-radius:10px;
    border:1px solid #c9d8c8;
    background:#fff;
}

.btn{
    border:none;
    border-radius:10px;
    padding:12px 16px;
    font-weight:bold;
    cursor:pointer;
}

.btn-primary{
    background:#2a322b;
    color:#fff;
}

.btn-primary:hover{
    background:#1e251f;
}

.btn-light{
    background:#edf7ec;
    color:#213123;
}

.btn-danger{
    background:#ec6d6d;
    color:#fff;
}

.btn-danger:hover{
    background:#d55858;
}

.add-grid{
    display:grid;
    grid-template-columns: repeat(5, 1fr) auto;
    gap:12px;
    margin-top:14px;
    align-items:end;
}

.content-grid{
    max-width:1180px;
    margin:0 auto;
    display:grid;
    grid-template-columns: 360px 1fr;
    gap:20px;
}

.student-list-header,
.details-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:16px;
}

.student-scroll{
    max-height:620px;
    overflow-y:auto;
    display:grid;
    gap:12px;
}

.student-card{
    display:block;
    text-decoration:none;
    color:inherit;
    padding:14px 16px;
    border-radius:16px;
    background:#fff;
    border:1px solid rgba(42,50,43,0.08);
    transition:0.2s;
}

.student-card:hover,
.student-card.active{
    background:#ecfaea;
    transform:translateY(-2px);
}

.student-card h3{
    color:#203022;
    margin-bottom:4px;
}

.student-meta{
    font-size:13px;
    color:#5b645c;
    line-height:1.5;
}

.pill{
    display:inline-block;
    margin-top:8px;
    padding:6px 10px;
    border-radius:999px;
    background:#eef7ed;
    color:#2f6333;
    font-size:12px;
    font-weight:bold;
}

.subject-grid{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap:16px;
}

.subject-card{
    background:#fff;
    border-radius:16px;
    border:1px solid rgba(42,50,43,0.08);
    padding:16px;
}

.subject-card h3{
    color:#203022;
    margin-bottom:8px;
}

.subject-card p{
    color:#5b645c;
    font-size:14px;
    min-height:42px;
}

.subject-card label{
    display:flex;
    gap:10px;
    align-items:flex-start;
}

.status{
    margin-top:10px;
    font-size:12px;
    font-weight:bold;
}

.status.available{
    color:#8b7412;
}

.status.enrolled{
    color:#2f6333;
}

.section-title{
    color:#203022;
    margin:22px 0 14px;
}

.enrolled-list{
    display:grid;
    gap:12px;
}

.enrolled-item{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    background:#fff;
    border-radius:14px;
    padding:14px 16px;
    border:1px solid rgba(42,50,43,0.08);
}

.empty{
    color:#5b645c;
    text-align:center;
    padding:24px 8px;
}

@media (max-width: 1080px){
    .add-grid{
        grid-template-columns: repeat(2, 1fr);
    }

    .content-grid{
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px){
    body{
        display:block;
    }

    .sidebar{
        width:100%;
        min-width:100%;
        height:auto;
        position:relative;
    }

    .search-row{
        grid-template-columns: 1fr;
    }

    .add-grid{
        grid-template-columns: 1fr;
    }
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
    <a href="login.php" style="margin-top:auto;color:#ff6b6b;font-weight:bold;">Logout</a>
</div>

<div class="main">
    <div class="hero">
        <h1>Enrollment Manager</h1>
        <p>Search students by name, filter by enrollment status, add new students, and enroll them in their section subjects.</p>
    </div>

    <div class="semester-toggle">
        <a class="semester-link <?= $selected_semester === '1st Semester' ? 'active' : '' ?>" href="enrollement.php?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&student_id=<?= urlencode($selected_student_id) ?>&semester=<?= urlencode('1st Semester') ?>">1st Semester</a>
        <a class="semester-link <?= $selected_semester === '2nd Semester' ? 'active' : '' ?>" href="enrollement.php?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&student_id=<?= urlencode($selected_student_id) ?>&semester=<?= urlencode('2nd Semester') ?>">2nd Semester</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice <?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="share-box">
        <strong>Student self-enrollment link</strong>
        <div style="margin-top:8px;color:#566056;">
            Share only this page if you want students to enroll for themselves without accessing the whole system.
        </div>
        <code><?= htmlspecialchars($portal_link) ?></code>
    </div>

    <div class="add-student-box">
        <strong>Add student without creating an account</strong>
        <form method="POST">
            <input type="hidden" name="action" value="add_student">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            <input type="hidden" name="semester" value="<?= htmlspecialchars($selected_semester) ?>">
            <div class="add-grid">
                <div class="field">
                    <label for="new_student_id">Student ID</label>
                    <input type="text" id="new_student_id" name="new_student_id" required>
                </div>
                <div class="field">
                    <label for="new_fname">First name</label>
                    <input type="text" id="new_fname" name="new_fname" required>
                </div>
                <div class="field">
                    <label for="new_lname">Last name</label>
                    <input type="text" id="new_lname" name="new_lname" required>
                </div>
                <div class="field">
                    <label for="new_year_level">Year level</label>
                    <select id="new_year_level" name="new_year_level" required>
                        <option value="">Select year</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                </div>
                <div class="field">
                    <label for="new_section_name">Section</label>
                    <select id="new_section_name" name="new_section_name" required>
                        <option value="">Select section</option>
                        <?php foreach ($sections as $section): ?>
                            <option
                                value="<?= htmlspecialchars($section['section_name']) ?>"
                                data-year="<?= (int)$section['year_level'] ?>">
                                Year <?= (int)$section['year_level'] ?> - <?= htmlspecialchars($section['section_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add Student</button>
            </div>
        </form>
    </div>

    <div class="content-grid">
        <div class="student-list">
            <div class="student-list-header">
                <strong>Student List</strong>
                <span style="color:#5b645c; font-size:13px;"><?= count($students) ?> result(s)</span>
            </div>

            <form method="GET">
                <div class="search-row">
                    <input type="hidden" name="semester" value="<?= htmlspecialchars($selected_semester) ?>">
                    <div class="field">
                        <label for="search">Search by student ID or name</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ex. S123 or Ruih Mata">
                    </div>
                    <div class="field">
                        <label for="status">Student list</label>
                        <select id="status" name="status">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All students</option>
                            <option value="enrolled" <?= $status === 'enrolled' ? 'selected' : '' ?>>Already enrolled</option>
                            <option value="not_enrolled" <?= $status === 'not_enrolled' ? 'selected' : '' ?>>Not enrolled yet</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-light">Search</button>
                </div>
            </form>

            <div class="student-scroll" style="margin-top:16px;">
                <?php if ($students): ?>
                    <?php foreach ($students as $student): ?>
                        <a class="student-card <?= $selected_student_id === $student['student_id'] ? 'active' : '' ?>"
                           href="?student_id=<?= urlencode($student['student_id']) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&semester=<?= urlencode($selected_semester) ?>">
                            <h3><?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?></h3>
                            <div class="student-meta">
                                <?= htmlspecialchars($student['student_id']) ?><br>
                                Year <?= (int)$student['year_level'] ?> - <?= htmlspecialchars($student['section_name']) ?>
                            </div>
                            <span class="pill"><?= (int)$student['enrolled_count'] ?> enrolled subject(s)</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">No students matched your search or filter.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="details-box">
            <?php if ($selected_student): ?>
                <div class="details-header">
                    <div>
                        <h2 style="color:#203022;"><?= htmlspecialchars($selected_student['fname'] . ' ' . $selected_student['lname']) ?></h2>
                        <div style="color:#5b645c; margin-top:6px;">
                            <?= htmlspecialchars($selected_student['student_id']) ?> |
                            Year <?= (int)$selected_student['year_level'] ?> |
                            <?= htmlspecialchars($selected_student['section_name']) ?> |
                            <?= htmlspecialchars($selected_semester) ?>
                        </div>
                    </div>
                    <span class="pill"><?= count($enrolled_subjects) ?> enrolled subject(s)</span>
                </div>

                <?php if ($subjects): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="enroll">
                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($selected_student['student_id']) ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <input type="hidden" name="semester" value="<?= htmlspecialchars($selected_semester) ?>">

                        <div class="subject-grid">
                            <?php foreach ($subjects as $subject): ?>
                                <div class="subject-card">
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="subject_ids[]"
                                            value="<?= (int)$subject['id'] ?>"
                                            <?= (int)$subject['is_enrolled'] === 1 ? 'disabled' : '' ?>>
                                        <div>
                                            <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>
                                            <p><?= htmlspecialchars($subject['description'] ?: 'No description available.') ?></p>
                                        </div>
                                    </label>
                                    <div class="status <?= (int)$subject['is_enrolled'] === 1 ? 'enrolled' : 'available' ?>">
                                        <?= (int)$subject['is_enrolled'] === 1 ? 'Already enrolled' : 'Ready to enroll' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top:18px; text-align:right;">
                            <button type="submit" class="btn btn-primary">Enroll Selected Subjects</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty">No subjects are assigned to this student's year and section yet.</div>
                <?php endif; ?>

                <h3 class="section-title">Current Enrolled Subjects</h3>
                <?php if ($enrolled_subjects): ?>
                    <div class="enrolled-list">
                        <?php foreach ($enrolled_subjects as $subject): ?>
                            <div class="enrolled-item">
                                <div>
                                    <strong><?= htmlspecialchars($subject['subject_name']) ?></strong>
                                    <div style="color:#5b645c; margin-top:4px; font-size:14px;">
                                        <?= htmlspecialchars($subject['description'] ?: 'No description available.') ?>
                                    </div>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($selected_student['student_id']) ?>">
                                    <input type="hidden" name="subject_id" value="<?= (int)$subject['id'] ?>">
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                    <input type="hidden" name="semester" value="<?= htmlspecialchars($selected_semester) ?>">
                                    <button type="submit" class="btn btn-danger">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">This student has no enrolled subjects yet.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty">Select a student from the list to manage enrollment.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script>
const yearSelect = document.getElementById('new_year_level');
const sectionSelect = document.getElementById('new_section_name');

function filterSections() {
    const year = yearSelect.value;
    const options = sectionSelect.querySelectorAll('option[data-year]');
    let firstVisible = '';

    options.forEach((option) => {
        const visible = !year || option.dataset.year === year;
        option.hidden = !visible;
        if (visible && firstVisible === '') {
            firstVisible = option.value;
        }
    });

    if (sectionSelect.selectedOptions.length && sectionSelect.selectedOptions[0].hidden) {
        sectionSelect.value = firstVisible;
    }
}

yearSelect.addEventListener('change', filterSections);
filterSections();
</script>
</body>
</html>
