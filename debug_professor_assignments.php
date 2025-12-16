<?php
// require_once '../includes/init.php';
// requireLogin();

// // Get current logged-in user
// $userId = $_SESSION['user_id'] ?? null;
// $userRole = $_SESSION['role'] ?? null;

// echo "<h2>Debug: Professor Course Assignments</h2>";
// echo "<p>Logged in as User ID: $userId (Role: $userRole)</p>";

// // Check if table exists
// try {
//     $db->query("SELECT 1 FROM professor_course_assignments LIMIT 1");
//     echo "<p style='color: green;'>✓ professor_course_assignments table exists</p>";
// } catch (Exception $e) {
//     echo "<p style='color: red;'>✗ professor_course_assignments table does NOT exist!</p>";
//     echo "<p>Error: " . $e->getMessage() . "</p>";
//     echo "<p><strong>Action needed:</strong> Run the professor_course_assignments.sql migration first!</p>";
//     exit;
// }

// // Get all assignments for this professor
// echo "<h3>Assignments for Professor ID: $userId</h3>";
// $assignments = $db->fetchAll(
//     "SELECT pca.*, c.course_code, c.course_name 
//      FROM professor_course_assignments pca
//      JOIN courses c ON pca.course_id = c.id
//      WHERE pca.professor_id = ?",
//     [$userId]
// );

// if (empty($assignments)) {
//     echo "<p style='color: orange;'><strong>No courses assigned to this professor yet!</strong></p>";
//     echo "<p>Go to Admin → Assign Courses to assign courses to this professor.</p>";
// } else {
//     echo "<table border='1' cellpadding='10'>";
//     echo "<tr><th>Course Code</th><th>Course Name</th><th>Section</th><th>Semester</th><th>School Year</th></tr>";
//     foreach ($assignments as $assignment) {
//         echo "<tr>";
//         echo "<td>{$assignment['course_code']}</td>";
//         echo "<td>{$assignment['course_name']}</td>";
//         echo "<td>{$assignment['section']}</td>";
//         echo "<td>{$assignment['semester']}</td>";
//         echo "<td>{$assignment['school_year']}</td>";
//         echo "</tr>";
//     }
//     echo "</table>";
// }

// // Get students enrolled in professor's courses
// echo "<h3>Students in Professor's Assigned Courses</h3>";
// $students = $db->fetchAll(
//     "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, c.course_code, c.course_name
//      FROM users u
//      JOIN course_enrollments ce ON u.id = ce.student_id
//      JOIN courses c ON ce.course_id = c.id
//      JOIN professor_course_assignments pca ON ce.course_id = pca.course_id
//      WHERE pca.professor_id = ?",
//     [$userId]
// );

// if (empty($students)) {
//     echo "<p>No students found in your assigned courses.</p>";
// } else {
//     echo "<table border='1' cellpadding='10'>";
//     echo "<tr><th>Student Name</th><th>Email</th><th>Course</th></tr>";
//     foreach ($students as $student) {
//         echo "<tr>";
//         echo "<td>{$student['first_name']} {$student['last_name']}</td>";
//         echo "<td>{$student['email']}</td>";
//         echo "<td>{$student['course_code']} - {$student['course_name']}</td>";
//         echo "</tr>";
//     }
//     echo "</table>";
// }

// // Show all students without filtering (to compare)
// echo "<h3>ALL Students (without filtering)</h3>";
// $allStudents = $db->fetchAll(
//     "SELECT u.id, u.first_name, u.last_name, u.email
//      FROM users u
//      WHERE u.role = 'student'
//      ORDER BY u.last_name"
// );

// echo "<p>Total students in system: " . count($allStudents) . "</p>";
// echo "<table border='1' cellpadding='10'>";
// echo "<tr><th>Name</th><th>Email</th></tr>";
// foreach ($allStudents as $student) {
//     echo "<tr>";
//     echo "<td>{$student['first_name']} {$student['last_name']}</td>";
//     echo "<td>{$student['email']}</td>";
//     echo "</tr>";
// }
// echo "</table>";
?>
