<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>

<!-- ADDED: CROPPIE -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css" />

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    margin:0;
    font-family:Arial, sans-serif;
    background: linear-gradient(135deg, #ffffff, #5eec6a96);
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
    transition:0.2s;
}

.sidebar a:hover{
    background:#a1fa9b;
    color:#1f1f1f;
}

/* MAIN */
.main{
    flex:1;
    display:flex;
    flex-direction:column;
    height:100vh;
    overflow-y:auto;
}

/* TOPBAR */
.topbar{
    height:60px;
    display:flex;
    justify-content:center;
    align-items:center;
    background: rgba(27, 25, 25, 0.18);
    backdrop-filter: blur(10px);
    border-bottom:1px solid rgba(255,255,255,0.05);
}

/* HERO */
.hero{
    text-align:center;
    padding:40px 20px 20px;
}

.hero h1{
    font-size:38px;
    letter-spacing:2px;
}

/* CARD */
.cards{
    display:flex;
    justify-content:center;
    padding:30px;
}

.card{
    width:350px;
    padding:25px;
    background: rgba(35, 43, 33, 0.12);
    backdrop-filter: blur(12px);
    border-radius:16px;
    text-align:center;
}

.card img{
    width:90px;
    height:90px;
    border-radius:50%;
    object-fit:cover;
    margin-bottom:15px;
    border:3px solid #fff;
}

.info{
    text-align:left;
}

.info p{
    margin:8px 0;
    font-size:14px;
}

.label{
    font-weight:bold;
}

/* BUTTON */
.edit-btn{
    margin-top:15px;
    padding:10px 14px;
    border:none;
    border-radius:8px;
    background:#2a322b;
    color:#fff;
    cursor:pointer;
}

.edit-btn:hover{
    background:#a1fa9b;
    color:#1f1f1f;
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
    padding:25px;
    border-radius:12px;
    width:320px;
}

.modal-content input{
    width:100%;
    padding:8px;
    margin:6px 0;
    border:1px solid #ccc;
    border-radius:6px;
}

.modal-content button{
    margin-top:10px;
    padding:10px;
    width:100%;
    border:none;
    border-radius:8px;
    background:#2a322b;
    color:#fff;
}

/* ADDED: crop modal */
#cropModal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.6);
    justify-content:center;
    align-items:center;
    z-index:999;
}

#cropBox{
    background:#fff;
    padding:20px;
    border-radius:10px;
}
</style>
</head>

<body>

<div class="app">

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="profile-box">
        <?php if(!empty($_SESSION['profile_pic'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($_SESSION['profile_pic']); ?>" 
                 alt="Profile"
                 style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
        <?php else: ?>
            <div class="profile-icon">👤</div>
        <?php endif; ?>

        <div class="profile-text">

            <span style="display:block; font-weight:bold; color:#a1fa9b;">
                <?= htmlspecialchars($_SESSION['fname'] ?? 'User') ?>
            </span>

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

    <div class="topbar">
        <h3>EntraQR Management System</h3>
    </div>

    <div class="hero">
        <h1>PERSONAL INFORMATION</h1>
    </div>

    <div class="cards">

        <div class="card">

            <?php if(!empty($_SESSION['profile_pic'])): ?>
                <img src="uploads/<?php echo $_SESSION['profile_pic']; ?>">
            <?php else: ?>
                <img src="https://via.placeholder.com/90">
            <?php endif; ?>

            <div class="info">
                <p><span class="label">Name:</span> <?= $_SESSION['fname'] ?? 'N/A' ?></p>
                <p><span class="label">Email:</span> <?= $_SESSION['email'] ?? 'N/A' ?></p>
            </div>

            <button class="edit-btn" onclick="document.getElementById('modal').style.display='flex'">
                Edit Details
            </button>

        </div>

    </div>
</div>

</div>

<!-- MODAL -->
<div id="modal" class="modal">
    <div class="modal-content">

        <form method="POST" action="update_profile.php" enctype="multipart/form-data">

            <input type="text" name="fname" value="<?= $_SESSION['fname'] ?? '' ?>">
            <input type="email" name="email" value="<?= $_SESSION['email'] ?? '' ?>">
            <input type="password" name="password" placeholder="New Password">

            <!-- YOUR ORIGINAL INPUT (UNCHANGED) -->
            <input type="file" name="profile_pic">

            <!-- ADDED hidden cropped image -->
            <input type="hidden" name="cropped_image" id="cropped_image">

            <button type="submit">Save</button>
            <button type="button" onclick="document.getElementById('modal').style.display='none'">Cancel</button>

        </form>

    </div>
</div>

<!-- ADDED CROPPER MODAL -->
<div id="cropModal">
    <div id="cropBox">
        <div id="cropArea"></div>
        <button type="button" onclick="applyCrop()">Crop Image</button>
    </div>
</div>

<!-- ADDED SCRIPTS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

<script>
let croppieInstance;

document.querySelector('input[name="profile_pic"]').addEventListener('change', function(e){

    let reader = new FileReader();
    reader.onload = function(event){

        document.getElementById('cropModal').style.display = 'flex';

        croppieInstance = new Croppie(document.getElementById('cropArea'), {
            viewport: { width: 150, height: 150, type: 'circle' },
            boundary: { width: 300, height: 300 }
        });

        croppieInstance.bind({
            url: event.target.result
        });
    };

    reader.readAsDataURL(e.target.files[0]);
});

function applyCrop(){
    croppieInstance.result({
        type: 'base64',
        size: 'viewport'
    }).then(function(base64){

        document.getElementById('cropped_image').value = base64;

        document.getElementById('cropModal').style.display = 'none';

        croppieInstance.destroy();
    });
}
</script>

</body>
</html>
