<?php
require_once 'db.php';

// Assuming student_id and subject_id are passed from your QR Scanner
$student_id = $_POST['student_id'] ?? null;
$subject_id = $_POST['subject_id'] ?? null;
$today = date('Y-m-d');
$current_time = date('H:i:s');

if ($student_id && $subject_id) {
    // 1. Check if record for today/subject exists
    $stmt = $conn->prepare("SELECT id, time_in, time_out FROM attendance WHERE student_id = ? AND subject_id = ? AND date = ?");
    $stmt->bind_param("iis", $student_id, $subject_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // FIRST SCAN: Record Time In
        $ins = $conn->prepare("INSERT INTO attendance (student_id, subject_id, date, time_in, status) VALUES (?, ?, ?, ?, 'Present')");
        $ins->bind_param("iiss", $student_id, $subject_id, $today, $current_time);
        $ins->execute();
        echo "Time In Recorded: " . $current_time;
    } else {
        $row = $result->fetch_assoc();
        
        if (empty($row['time_out'])) {
            // SECOND SCAN: Record Time Out
            $upd = $conn->prepare("UPDATE attendance SET time_out = ? WHERE id = ?");
            $upd->bind_param("si", $current_time, $row['id']);
            $upd->execute();
            echo "Time Out Recorded: " . $current_time;
        } else {
            // THIRD SCAN OR MORE: Prevent duplicate
            echo "Attendance already completed (Time In & Time Out recorded) for today.";
        }
    }
} else {
    echo "Invalid Scan Data.";
}
?>