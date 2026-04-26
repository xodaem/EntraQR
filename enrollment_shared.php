<?php

function enrollment_set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['enrollment_message'] = $message;
    $_SESSION['enrollment_message_type'] = $type;
}

function enrollment_take_flash(): array
{
    $message = $_SESSION['enrollment_message'] ?? '';
    $type = $_SESSION['enrollment_message_type'] ?? 'success';

    unset($_SESSION['enrollment_message'], $_SESSION['enrollment_message_type']);

    return [$message, $type];
}

function enrollment_redirect(string $page, array $params = []): void
{
    $query = http_build_query($params);
    header('Location: ' . $page . ($query !== '' ? '?' . $query : ''));
    exit();
}

function enrollment_fetch_sections(mysqli $conn): array
{
    $result = $conn->query("
        SELECT year_level, section_name
        FROM sections
        ORDER BY year_level, section_name
    ");

    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }

    return $sections;
}

function enrollment_fetch_students(mysqli $conn, string $search = '', string $status = 'all'): array
{
    $sql = "
        SELECT
            s.student_id,
            s.fname,
            s.lname,
            s.year_level,
            s.section_name,
            COUNT(e.id) AS enrolled_count
        FROM students s
        LEFT JOIN enrollments e ON e.student_id = s.student_id
        WHERE 1=1
    ";

    $params = [];
    $types = '';

    if ($search !== '') {
        $sql .= "
            AND (
                s.student_id LIKE ?
                OR s.fname LIKE ?
                OR s.lname LIKE ?
                OR CONCAT(s.fname, ' ', s.lname) LIKE ?
                OR CONCAT(s.lname, ', ', s.fname) LIKE ?
            )
        ";

        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sssss';
    }

    $sql .= "
        GROUP BY s.student_id, s.fname, s.lname, s.year_level, s.section_name
    ";

    if ($status === 'enrolled') {
        $sql .= " HAVING COUNT(e.id) > 0";
    } elseif ($status === 'not_enrolled') {
        $sql .= " HAVING COUNT(e.id) = 0";
    }

    $sql .= " ORDER BY s.year_level, s.section_name, s.lname, s.fname";

    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $stmt->close();

    return $students;
}

function enrollment_fetch_student(mysqli $conn, string $studentId): ?array
{
    $stmt = $conn->prepare("
        SELECT student_id, fname, lname, year_level, section_name
        FROM students
        WHERE student_id = ?
    ");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $student;
}

function enrollment_fetch_subjects(mysqli $conn, array $student, string $semester = '1st Semester'): array
{
    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.subject_name,
            s.description,
            s.semester,
            CASE WHEN e.id IS NULL THEN 0 ELSE 1 END AS is_enrolled
        FROM subjects s
        LEFT JOIN enrollments e
            ON e.subject_id = s.id
           AND e.student_id = ?
        WHERE s.year_level = ? AND s.section_name = ? AND s.semester = ?
        ORDER BY s.subject_name
    ");
    $stmt->bind_param(
        "siss",
        $student['student_id'],
        $student['year_level'],
        $student['section_name'],
        $semester
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

    $stmt->close();

    return $subjects;
}

function enrollment_create_student(mysqli $conn, array $input): array
{
    $studentId = trim($input['new_student_id'] ?? '');
    $fname = trim($input['new_fname'] ?? '');
    $lname = trim($input['new_lname'] ?? '');
    $yearLevel = (int)($input['new_year_level'] ?? 0);
    $sectionName = trim($input['new_section_name'] ?? '');

    if ($studentId === '' || $fname === '' || $lname === '' || $yearLevel < 1 || $sectionName === '') {
        return [false, 'Please complete all student fields.', null];
    }

    $stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        return [false, 'Student ID already exists.', $studentId];
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM sections
        WHERE year_level = ? AND section_name = ?
    ");
    $stmt->bind_param("is", $yearLevel, $sectionName);
    $stmt->execute();
    $stmt->store_result();
    $sectionExists = $stmt->num_rows > 0;
    $stmt->close();

    if (!$sectionExists) {
        return [false, 'Selected section does not belong to the chosen year level.', null];
    }

    $stmt = $conn->prepare("
        INSERT INTO students (student_id, fname, lname, year_level, section_name)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssis", $studentId, $fname, $lname, $yearLevel, $sectionName);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return [false, 'Unable to add student right now.', null];
    }

    return [true, 'Student added successfully. You can enroll them now.', $studentId];
}

function enrollment_add_subjects(mysqli $conn, array $student, array $subjectIds, string $semester = '1st Semester'): array
{
    $selectedSubjectIds = array_map('intval', $subjectIds);
    $selectedSubjectIds = array_values(array_unique(array_filter($selectedSubjectIds)));

    if (!$selectedSubjectIds) {
        return [false, 'Select at least one subject to enroll.'];
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM subjects
        WHERE year_level = ? AND section_name = ? AND semester = ?
    ");
    $stmt->bind_param("iss", $student['year_level'], $student['section_name'], $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    $allowedIds = [];
    while ($row = $result->fetch_assoc()) {
        $allowedIds[] = (int)$row['id'];
    }
    $stmt->close();

    $validSubjectIds = array_values(array_intersect($selectedSubjectIds, $allowedIds));
    if (!$validSubjectIds) {
        return [false, 'Selected subjects do not match the student year and section.'];
    }

    $stmt = $conn->prepare("
        SELECT subject_id
        FROM enrollments
        WHERE student_id = ?
    ");
    $stmt->bind_param("s", $student['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $existingIds = [];
    while ($row = $result->fetch_assoc()) {
        $existingIds[] = (int)$row['subject_id'];
    }
    $stmt->close();

    $newSubjectIds = array_values(array_diff($validSubjectIds, $existingIds));
    if (!$newSubjectIds) {
        return [false, 'The selected subjects are already enrolled.'];
    }

    $stmt = $conn->prepare("
        INSERT INTO enrollments (student_id, subject_id, year_level, section_name)
        VALUES (?, ?, ?, ?)
    ");

    $inserted = 0;
    foreach ($newSubjectIds as $subjectId) {
        $stmt->bind_param("siis", $student['student_id'], $subjectId, $student['year_level'], $student['section_name']);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    $stmt->close();

    if ($inserted < 1) {
        return [false, 'No subjects were enrolled.'];
    }

    return [true, "Successfully enrolled {$inserted} subject(s)."];
}

function enrollment_remove_subject(mysqli $conn, string $studentId, int $subjectId): array
{
    $stmt = $conn->prepare("
        DELETE FROM enrollments
        WHERE student_id = ? AND subject_id = ?
    ");
    $stmt->bind_param("si", $studentId, $subjectId);
    $stmt->execute();
    $removed = $stmt->affected_rows;
    $stmt->close();

    if ($removed > 0) {
        return [true, 'Subject removed from the student enrollment.'];
    }

    return [false, 'That subject was not enrolled.'];
}
