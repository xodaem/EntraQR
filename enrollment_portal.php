<?php
session_start();
require_once 'db.php';
require_once 'enrollment_shared.php';

[$message, $message_type] = enrollment_take_flash();

$search = trim($_GET['search'] ?? '');
$students = enrollment_fetch_students($conn, $search, 'all');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $student_id = trim($_POST['student_id'] ?? '');
    $search = trim($_POST['search'] ?? '');

    if ($action === 'add_student') {
        [$ok, $flash, $createdStudentId] = enrollment_create_student($conn, $_POST);
        enrollment_set_flash($flash, $ok ? 'success' : 'error');
        enrollment_redirect('enrollment_portal.php', [
            'student_id' => $createdStudentId ?? '',
            'search' => $search
        ]);
    }

    $student = enrollment_fetch_student($conn, $student_id);
    if (!$student) {
        enrollment_set_flash('Student not found.', 'error');
        enrollment_redirect('enrollment_portal.php', [
            'search' => $search
        ]);
    }

    if ($action === 'enroll') {
        [$ok, $flash] = enrollment_add_subjects($conn, $student, $_POST['subject_ids'] ?? []);
        enrollment_set_flash($flash, $ok ? 'success' : 'error');
    }

    enrollment_redirect('enrollment_portal.php', [
        'student_id' => $student_id,
        'search' => $search
    ]);
}

$selected_student_id = trim($_GET['student_id'] ?? '');
if ($selected_student_id === '' && !empty($students)) {
    $selected_student_id = $students[0]['student_id'];
}

$selected_student = $selected_student_id !== '' ? enrollment_fetch_student($conn, $selected_student_id) : null;
$subjects = $selected_student ? enrollment_fetch_subjects($conn, $selected_student) : [];
$enrolled_subjects = array_values(array_filter($subjects, static function ($subject) {
    return (int)$subject['is_enrolled'] === 1;
}));
$sections = enrollment_fetch_sections($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Enrollment Portal</title>
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    min-height:100vh;
    background:linear-gradient(135deg, #f7fff7, #d5f4d4);
    color:#203022;
}

.wrap{
    max-width:1200px;
    margin:0 auto;
    padding:30px 20px 40px;
}

.hero,
.notice,
.panel{
    background:rgba(255,255,255,0.55);
    backdrop-filter:blur(10px);
    border-radius:22px;
    box-shadow:0 12px 30px rgba(0,0,0,0.07);
    margin-bottom:20px;
}

.hero{
    padding:30px;
    text-align:center;
}

.hero h1{
    font-size:38px;
}

.hero p{
    margin-top:10px;
    color:#546054;
}

.notice{
    padding:14px 18px;
    font-weight:bold;
}

.notice.success{
    background:#def6dd;
    color:#1d5f20;
}

.notice.error{
    background:#ffe1e1;
    color:#8a1f1f;
}

.panel{
    padding:22px;
}

.panel h2{
    margin-bottom:12px;
}

.grid{
    display:grid;
    grid-template-columns:360px 1fr;
    gap:20px;
}

.field label{
    display:block;
    font-size:13px;
    font-weight:bold;
    margin-bottom:8px;
}

.field input,
.field select{
    width:100%;
    padding:12px;
    border-radius:10px;
    border:1px solid #c7d7c7;
}

.search-row,
.add-grid{
    display:grid;
    gap:12px;
    align-items:end;
}

.search-row{
    grid-template-columns:1fr auto;
}

.add-grid{
    grid-template-columns:repeat(5, 1fr) auto;
    margin-top:14px;
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

.student-scroll{
    max-height:620px;
    overflow-y:auto;
    display:grid;
    gap:12px;
    margin-top:16px;
}

.student-card{
    display:block;
    text-decoration:none;
    color:inherit;
    padding:14px 16px;
    border-radius:16px;
    background:#fff;
    border:1px solid rgba(42,50,43,0.08);
}

.student-card.active,
.student-card:hover{
    background:#edf9eb;
}

.student-meta{
    margin-top:6px;
    font-size:13px;
    color:#5b645c;
    line-height:1.5;
}

.pill{
    display:inline-block;
    margin-top:10px;
    padding:6px 10px;
    border-radius:999px;
    background:#edf7ec;
    color:#2f6333;
    font-size:12px;
    font-weight:bold;
}

.subject-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
    gap:16px;
}

.subject-card{
    background:#fff;
    border-radius:16px;
    border:1px solid rgba(42,50,43,0.08);
    padding:16px;
}

.subject-card label{
    display:flex;
    align-items:flex-start;
    gap:10px;
}

.subject-card h3{
    margin-bottom:8px;
}

.subject-card p{
    font-size:14px;
    color:#5b645c;
    min-height:42px;
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

.empty{
    text-align:center;
    color:#5b645c;
    padding:24px 8px;
}

@media (max-width: 1080px){
    .grid{
        grid-template-columns:1fr;
    }

    .add-grid{
        grid-template-columns:repeat(2, 1fr);
    }
}

@media (max-width: 720px){
    .search-row,
    .add-grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>Student Enrollment Portal</h1>
        <p>Students can search their name, add themselves if needed, and enroll only in subjects for their year and section.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice <?= $message_type === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2>Add yourself if you are not yet listed</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_student">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
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
                            <option value="<?= htmlspecialchars($section['section_name']) ?>" data-year="<?= (int)$section['year_level'] ?>">
                                Year <?= (int)$section['year_level'] ?> - <?= htmlspecialchars($section['section_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Add My Record</button>
            </div>
        </form>
    </div>

    <div class="grid">
        <div class="panel">
            <h2>Search your name</h2>
            <form method="GET">
                <div class="search-row">
                    <div class="field">
                        <label for="search">Student ID or full name</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Ex. S123 or Ruih Mata">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>

            <div class="student-scroll">
                <?php if ($students): ?>
                    <?php foreach ($students as $student): ?>
                        <a class="student-card <?= $selected_student_id === $student['student_id'] ? 'active' : '' ?>"
                           href="?student_id=<?= urlencode($student['student_id']) ?>&search=<?= urlencode($search) ?>">
                            <strong><?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?></strong>
                            <div class="student-meta">
                                <?= htmlspecialchars($student['student_id']) ?><br>
                                Year <?= (int)$student['year_level'] ?> - <?= htmlspecialchars($student['section_name']) ?>
                            </div>
                            <span class="pill"><?= (int)$student['enrolled_count'] ?> enrolled subject(s)</span>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty">No students matched your search.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <?php if ($selected_student): ?>
                <h2><?= htmlspecialchars($selected_student['fname'] . ' ' . $selected_student['lname']) ?></h2>
                <div style="color:#5b645c; margin-bottom:16px;">
                    <?= htmlspecialchars($selected_student['student_id']) ?> |
                    Year <?= (int)$selected_student['year_level'] ?> |
                    <?= htmlspecialchars($selected_student['section_name']) ?> |
                    <?= count($enrolled_subjects) ?> enrolled subject(s)
                </div>

                <?php if ($subjects): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="enroll">
                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($selected_student['student_id']) ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <div class="subject-grid">
                            <?php foreach ($subjects as $subject): ?>
                                <div class="subject-card">
                                    <label>
                                        <input type="checkbox" name="subject_ids[]" value="<?= (int)$subject['id'] ?>" <?= (int)$subject['is_enrolled'] === 1 ? 'disabled' : '' ?>>
                                        <div>
                                            <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>
                                            <p><?= htmlspecialchars($subject['description'] ?: 'No description available.') ?></p>
                                        </div>
                                    </label>
                                    <div class="status <?= (int)$subject['is_enrolled'] === 1 ? 'enrolled' : 'available' ?>">
                                        <?= (int)$subject['is_enrolled'] === 1 ? 'Already enrolled' : 'Available to enroll' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:18px; text-align:right;">
                            <button type="submit" class="btn btn-primary">Enroll Selected Subjects</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty">No subjects are available yet for this year and section.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty">Select your student record to continue.</div>
            <?php endif; ?>
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
