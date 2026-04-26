<?php 
session_start(); 
$page = basename($_SERVER['PHP_SELF']);

$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$default_sections = [
    1 => ['BSECE 1A', 'BSECE 1B'],
    2 => ['BSECE 2A', 'BSECE 2B'],
    3 => ['BSECE 3A', 'BSECE 3B', 'BSECE 3C'],
    4 => ['BSECE 4A', 'BSECE 4B', 'BSECE 4C', 'BSECE 4D'],
];

function migrate_section_names(mysqli $conn): void
{
    $section_map = [
        'BSIT 1A' => 'BSECE 1A',
        'BSIT 1B' => 'BSECE 1B',
        'BSIT 2A' => 'BSECE 2A',
        'BSIT 2B' => 'BSECE 2B',
        'BSIT 3A' => 'BSECE 3A',
        'BSIT 3B' => 'BSECE 3B',
        'BSIT3C'  => 'BSECE 3C',
        'BSIT 3C' => 'BSECE 3C',
        'BSIT 4A' => 'BSECE 4A',
        'BSIT 4B' => 'BSECE 4B',
        'BSIT 4C' => 'BSECE 4C',
        'BSIT 4D' => 'BSECE 4D',
    ];

    $tables = ['sections', 'students', 'subjects', 'enrollments'];

    foreach ($tables as $table) {
        $stmt = $conn->prepare("
            UPDATE {$table}
            SET section_name = ?
            WHERE section_name = ?
        ");

        if (!$stmt) {
            continue;
        }

        foreach ($section_map as $old_name => $new_name) {
            $stmt->bind_param("ss", $new_name, $old_name);
            $stmt->execute();
        }
    }
}

function ensure_default_sections(mysqli $conn, array $default_sections): void
{
    $check = $conn->prepare("
        SELECT id
        FROM sections
        WHERE year_level = ? AND section_name = ?
        LIMIT 1
    ");
    $insert = $conn->prepare("
        INSERT INTO sections (year_level, section_name)
        VALUES (?, ?)
    ");

    if (!$check || !$insert) {
        die("Failed to prepare section sync statements.");
    }

    foreach ($default_sections as $year_level => $section_names) {
        foreach ($section_names as $section_name) {
            $check->bind_param("is", $year_level, $section_name);
            $check->execute();
            $exists = $check->get_result();

            if ($exists->num_rows === 0) {
                $insert->bind_param("is", $year_level, $section_name);
                $insert->execute();
            }
        }
    }
}

function remove_duplicate_sections(mysqli $conn): void
{
    $result = $conn->query("
        SELECT year_level, section_name, MIN(id) AS keep_id
        FROM sections
        GROUP BY year_level, section_name
        HAVING COUNT(*) > 1
    ");

    if (!$result) {
        return;
    }

    $delete = $conn->prepare("
        DELETE FROM sections
        WHERE year_level = ? AND section_name = ? AND id <> ?
    ");

    if (!$delete) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $year_level = (int) $row['year_level'];
        $section_name = $row['section_name'];
        $keep_id = (int) $row['keep_id'];

        $delete->bind_param("isi", $year_level, $section_name, $keep_id);
        $delete->execute();
    }
}

migrate_section_names($conn);
ensure_default_sections($conn, $default_sections);
remove_duplicate_sections($conn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['add_section'])) {
    $year_level = isset($_POST['year_level']) ? (int) $_POST['year_level'] : 0;
    $section_name = strtoupper(trim($_POST['section_name'] ?? ''));

    if ($year_level >= 1 && $year_level <= 4 && $section_name !== '') {
        $check = $conn->prepare("
            SELECT id
            FROM sections
            WHERE year_level = ? AND section_name = ?
            LIMIT 1
        ");

        $insert = $conn->prepare("
            INSERT INTO sections (year_level, section_name)
            VALUES (?, ?)
        ");

        if ($check && $insert) {
            $check->bind_param("is", $year_level, $section_name);
            $check->execute();
            $existing = $check->get_result();

            if ($existing->num_rows === 0) {
                $insert->bind_param("is", $year_level, $section_name);
                $insert->execute();
            }
        }
    }

    header("Location: section.php");
    exit();
}

$sections = [];

$result = $conn->query("SELECT * FROM sections ORDER BY year_level, section_name");

while($row = $result->fetch_assoc()){
    $sections[$row['year_level']][] = $row['section_name'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Sections</title>

<style>

/* =========================
   GLOBAL LAYOUT FIX
========================= */
body{
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #c8facc);

    overflow:hidden; /* 🔥 ONLY MAIN SCROLLS */
}

/* WRAPPER (DOES NOT CHANGE DESIGN) */
.app{
    display:flex;
    height:100vh;
}

/* =========================
   SIDEBAR (FIXED, NO SCROLL)
========================= */
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
    transition:0.2s;
}

.sidebar a:hover{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* =========================
   MAIN (ONLY SCROLL AREA)
========================= */
.main{
    flex:1;

    height:100vh;
    overflow-y:auto;  /* 🔥 SCROLL ONLY HERE */

    display:flex;
    flex-direction:column;
    align-items:center;

    padding:30px;
    box-sizing:border-box;
}

/* HERO */
.hero{
    text-align:center;
    margin-bottom:30px;
}

.hero h1{
    font-size:50px;
    color:#2e2e2e;
}

/* GRID */
.year-container{
    display:grid;
    grid-template-columns: repeat(2, 300px);
    justify-content:center;
    column-gap:150px;
    row-gap:45px;
    align-items:start;
}

/* YEAR ITEM */
.year-item{
    width:300px;
}

/* YEAR CARD */
.year-card{
    width:100%;
    padding:40px;

    /* ✅ make it glass */
    background: rgba(200, 250, 204, 0.86);

    border-radius:12px;
    text-align:center;
    cursor:pointer;
    font-weight:bold;
    transition:0.2s;

    /* 3D */
    transform-style: preserve-3d;
    perspective: 1000px;

    /* glass blur */
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);

    /* border */
    border: 1px solid rgba(255,255,255,0.5);

    /* ✅ MERGED box-shadow (ONLY ONE) */
    box-shadow: 
        0 8px 25px rgba(0,0,0,0.1),
        inset 0 2px 0 rgba(255,255,255,0.8),   /* top shine */
        inset 0 -3px 8px rgba(0,0,0,0.1),      /* depth */
        0 0 0 1px rgba(255,255,255,0.25);      /* outer glow */
}

.year-card:hover{
    background:#a1fa9b;
    transform:scale(1.03);
    .year-card:hover{
    transform: rotateX(5deg) rotateY(-5deg) translateY(-8px) scale(1.02);

    background: rgba(255, 255, 255, 0.4);

    box-shadow: 
        0 20px 40px rgba(0,0,0,0.2),
        inset 0 1px 0 rgba(255,255,255,0.7);
        box-shadow: 
    0 20px 40px rgba(0,0,0,0.2),
    inset 0 1px 0 rgba(255,255,255,0.8),
    inset 0 -3px 10px rgba(0,0,0,0.15),
    0 0 0 2px rgba(255,255,255,0.3);
}
}

.year-actions{
    display:flex;
    justify-content:center;
    margin-top:12px;
}

.add-section-btn{
    border:none;
    border-radius:999px;
    padding:10px 16px;
    background:#2a322b;
    color:#fff;
    font-weight:bold;
    cursor:pointer;
    transition:0.2s;
}

.add-section-btn:hover{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* SECTIONS */
.sections{
    max-height:0;
    overflow:hidden;
    opacity:0;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:10px;
    margin-top:10px;
}

.sections.open{
    max-height:200px;
    opacity:1;
    transform: translateY(0);
}

/* SECTION BOX */
.section-box{
    display:flex;
    align-items:center;
    justify-content:center;

    width:120px;
    padding:10px;

    background:#fff;
    border-radius:10px;

    text-align:center;
    box-shadow:0 2px 5px rgba(0,0,0,0.1);

    cursor:pointer;
    transition:0.2s;

    text-decoration:none;
    color:#000;
}

.section-box:hover{
    background:#a1fa9b;
    transform: scale(1.05);
    color:#1f1f1f;
}

.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.45);
    align-items:center;
    justify-content:center;
    z-index:1000;
}

.modal.open{
    display:flex;
}

.modal-card{
    width:min(420px, calc(100vw - 32px));
    background:#fff;
    border-radius:18px;
    padding:24px;
    box-shadow:0 18px 45px rgba(0,0,0,0.18);
}

.modal-card h2{
    margin:0 0 8px;
    color:#2a322b;
}

.modal-card p{
    margin:0 0 18px;
    color:#4f4f4f;
}

.modal-card label{
    display:block;
    margin-bottom:8px;
    font-size:13px;
    font-weight:bold;
    color:#2a322b;
}

.modal-card input{
    width:100%;
    padding:12px 14px;
    border:1px solid #cfd8cf;
    border-radius:10px;
    box-sizing:border-box;
    font:inherit;
    margin-bottom:16px;
}

.modal-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

.modal-actions button{
    border:none;
    border-radius:10px;
    padding:10px 16px;
    font:inherit;
    font-weight:bold;
    cursor:pointer;
}

.modal-cancel{
    background:#eef2ee;
    color:#2a322b;
}

.modal-save{
    background:#2a322b;
    color:#fff;
}

.modal-save:hover{
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
            <span style="font-weight:bold; color:#a1fa9b;"><?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?></span>
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

    <a href="login.php" style="margin-top:auto;color:#ff6b6b;">Logout</a>

</div>

<!-- MAIN -->
<div class="main">

    <div class="hero">
        <h1>Sections</h1>
    </div>

    <div class="year-container">

    <?php for($year = 1; $year <= 4; $year++): ?>
        <div class="year-item">

            <div class="year-card" onclick="toggle('yr<?= $year ?>')">
                <?= $year ?><?= ['st','nd','rd','th'][$year-1] ?? 'th' ?> Year
            </div>

            <div class="year-actions">
                <button type="button" class="add-section-btn" onclick="openAddSectionModal(<?= $year ?>);">
                    + Add Section
                </button>
            </div>

            <div class="sections" id="yr<?= $year ?>">
                <?php if(isset($sections[$year]) && !empty($sections[$year])): ?>
                    <?php foreach($sections[$year] as $sec): ?>

                        <a class="section-box"
                           href="section_subjects.php?year=<?= $year ?>&section=<?= urlencode($sec) ?>">
                            <?= htmlspecialchars($sec) ?>
                        </a>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="section-box">No sections</div>
                <?php endif; ?>
            </div>

        </div>
    <?php endfor; ?>

    </div>

</div>

</div>

<div class="modal" id="addSectionModal">
    <div class="modal-card">
        <h2>Add Section</h2>
        <p id="modalYearText">Add a section for this year level.</p>

        <form method="POST">
            <input type="hidden" name="add_section" value="1">
            <input type="hidden" name="year_level" id="modalYearInput" value="">

            <label for="modalSectionName">Section name</label>
            <input type="text" id="modalSectionName" name="section_name" placeholder="Enter section name" required>

            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeAddSectionModal()">Cancel</button>
                <button type="submit" class="modal-save">Save Section</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggle(id){
    document.getElementById(id).classList.toggle("open");
}

function openAddSectionModal(yearLevel){
    document.getElementById("modalYearInput").value = yearLevel;
    document.getElementById("modalYearText").textContent = "Add a section for Year " + yearLevel + ".";
    document.getElementById("modalSectionName").value = "";
    document.getElementById("addSectionModal").classList.add("open");
    document.getElementById("modalSectionName").focus();
}

function closeAddSectionModal(){
    document.getElementById("addSectionModal").classList.remove("open");
}
</script>

</body>
</html>
