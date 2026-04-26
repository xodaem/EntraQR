<?php 
ob_start();
session_start(); 

$page = basename($_SERVER['PHP_SELF']);

$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$section = isset($_GET['section']) ? trim($_GET['section']) : '';
$selected_semester = isset($_GET['semester']) && $_GET['semester'] === '2nd Semester'
    ? '2nd Semester'
    : '1st Semester';

if($year < 1 || empty($section)) {
    die("<h2 style='color:red;text-align:center;'>Error: Invalid Year or Section</h2>");
}

$conn = new mysqli("localhost", "root", "", "admin_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function ensure_subjects_semester_column(mysqli $conn): void
{
    $result = $conn->query("SHOW COLUMNS FROM subjects LIKE 'semester'");
    if ($result instanceof mysqli_result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE subjects ADD COLUMN semester VARCHAR(20) NOT NULL DEFAULT '1st Semester' AFTER description");
    }
}

ensure_subjects_semester_column($conn);


if(isset($_POST['add_subject'])){

    $subject = $_POST['subject'] ?? '';
    $description = $_POST['description'] ?? '';
    $year_post = $_POST['year'] ?? '';
    $section_post = $_POST['section'] ?? '';
    $semester_post = ($_POST['semester'] ?? '') === '2nd Semester' ? '2nd Semester' : '1st Semester';

    if($subject == '' || $year_post == '' || $section_post == ''){
        die("Missing form data");
    }

    $stmt = $conn->prepare("
        INSERT INTO subjects (year_level, section_name, subject_name, description, semester)
        VALUES (?, ?, ?, ?, ?)
    ");

    if(!$stmt){
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("issss", $year_post, $section_post, $subject, $description, $semester_post);

    if(!$stmt->execute()){
        die("Insert failed: " . $stmt->error);
    }

    $stmt->close();

    header("Location: section_subjects.php?year=$year_post&section=$section_post&semester=" . urlencode($semester_post));
    exit();
}

/* FETCH SUBJECTS */
$stmt = $conn->prepare("SELECT * FROM subjects 
                       WHERE year_level = ? AND section_name = ? AND semester = ?
                       ORDER BY subject_name");
$stmt->bind_param("iss", $year, $section, $selected_semester);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Subjects - <?= $year ?> <?= htmlspecialchars($section) ?></title>

<style>
body{
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #c8facc);

    display:flex;
    height:100vh;
    overflow:hidden; 
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

.sidebar a:hover{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* MAIN */
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
    font-size:50px;
    color:#2e2e2e;
}

.hero p{
    font-size:19px;
    color:#444;
    margin:8px 0 0 0;
}

.semester-toggle{
    display:flex;
    justify-content:center;
    gap:12px;
    flex-wrap:wrap;
    margin:0 auto 22px;
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

.back-btn{
    position: absolute;
    top: 20px;
    left: 300px;
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

/* GRID (UNCHANGED) */
.subject-container{
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    max-width: 1100px;
    margin: 30px auto;
}

.subject-box{
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);

    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 16px;

    padding: 25px 15px;
    text-align: center;

    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.5);

    transition: all 0.3s ease;

    min-height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: center;

    position: relative;
    overflow: hidden;
}

.subject-box:hover{
    transform: translateY(-10px) scale(1.03);
    box-shadow:
        0 18px 40px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

/* ADD BUTTON FIXED (NO REDIRECT) */
.add-btn{
    position: fixed;
    bottom: 25px;
    right: 25px;
    background:#2a322b;
    color:#fff;
    padding:15px 20px;
    border-radius:50px;
    border:none;
    font-weight:bold;
    cursor:pointer;
}

/* MODAL */
.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    justify-content:center;
    align-items:center;
}

.modal-content{
    background:#fff;
    padding:35px;
    border-radius:12px;
    width:450px;
    max-width:90%;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
    transform: translateX(120px); /* your shift */
  
    
}

.modal-content form{
    display:flex;
    flex-direction:column;
    gap:10px;
    align-items:stretch;
}

.modal-content label{
    font-size:12px;
    font-weight:bold;
    color:#2a322b;
    margin-top:5px;
}

.modal-content input,
.modal-content textarea{
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
    font-family:Arial;
    margin:0;
    transform: translateX(-10px);
}

.modal-content textarea{
    resize:none;
    height:80px;
}

.modal-content button{
    margin-top:10px;
    padding:12px;
    border:none;
    border-radius:8px;
    background:#2a322b;
    color:#fff;
    font-weight:bold;
    cursor:pointer;
    transition:0.2s;
}

.modal-content button:hover{
    background:#a1fa9b;
    color:#1f1f1f;
}
</style>
</head>

<body>

<!-- SIDEBAR (UNCHANGED) -->
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
        <h1>Subjects</h1>
        <p><?= $year ?><?= ['st','nd','rd','th'][$year-1] ?? 'th' ?> Year - Section <?= htmlspecialchars($section) ?></p>
        <p><?= htmlspecialchars($selected_semester) ?></p>
    </div>

    <a href="javascript:history.back()" class="back-btn">← Back</a>

    <div class="semester-toggle">
        <a class="semester-link <?= $selected_semester === '1st Semester' ? 'active' : '' ?>" href="section_subjects.php?year=<?= $year ?>&section=<?= urlencode($section) ?>&semester=<?= urlencode('1st Semester') ?>">1st Semester</a>
        <a class="semester-link <?= $selected_semester === '2nd Semester' ? 'active' : '' ?>" href="section_subjects.php?year=<?= $year ?>&section=<?= urlencode($section) ?>&semester=<?= urlencode('2nd Semester') ?>">2nd Semester</a>
    </div>

    <!-- SUBJECT GRID -->
<div class="subject-container">
    <?php if($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            
            <div class="subject-box"
                 onclick="location.href='subject_students.php?subject_id=<?= $row['id'] ?>&year=<?= $year ?>&section=<?= urlencode($section) ?>'">

                <h3><?= htmlspecialchars($row['subject_name']) ?></h3>
                <p><?= htmlspecialchars($row['description']) ?></p>

            </div>

        <?php endwhile; ?>
    <?php else: ?>
        <div style="grid-column:1/-1;text-align:center;">
            <h2>No Subjects Found</h2>
        </div>
    <?php endif; ?>
</div>


<button class="add-btn" onclick="openModal()">+ Add Subject</button>

<!-- MODAL FORM -->
<div id="modal" class="modal">
    <div class="modal-content">

        <span onclick="closeModal()" style="float:right;cursor:pointer;font-size:20px;">&times;</span>

        <h2 style="text-align:center; margin-bottom:15px; color:#2a322b;">
            Add Subject
        </h2>

        <form method="POST">

            <input type="hidden" name="add_subject" value="1">

            <!-- YEAR LEVEL -->
            <label>Year Level</label>
            <input type="text" value="<?= htmlspecialchars($year) ?>" readonly>

            <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">

            <!-- SECTION -->
            <label>Section</label>
            <input type="text" value="<?= htmlspecialchars($section) ?>" readonly>

            <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">

            <label>Semester</label>
            <input type="text" value="<?= htmlspecialchars($selected_semester) ?>" readonly>

            <input type="hidden" name="semester" value="<?= htmlspecialchars($selected_semester) ?>">

            <!-- SUBJECT NAME -->
            <label>Subject Name</label>
            <input type="text" name="subject" placeholder="Enter subject name" required>

            <!-- DESCRIPTION -->
            <label>Description</label>
            <textarea name="description" placeholder="Enter subject description"></textarea>

            <button type="submit">Save Subject</button>

        </form>

    </div>
</div>

<script>
function openModal(){
    document.getElementById("modal").style.display = "flex";
}

function closeModal(){
    document.getElementById("modal").style.display = "none";
}
</script>



</body>
</html>
