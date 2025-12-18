# AcademicAdvising

Mysql Commands

CREATE DATABASE IF NOT EXISTS advising_system;
USE advising_system;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student','professor','admin') NOT NULL
);

CREATE TABLE email_verification_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  student_number VARCHAR(50) NOT NULL,
  year_level INT NOT NULL,
  course_program VARCHAR(255) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE professors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  department VARCHAR(255),
  specialization VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  title VARCHAR(150) NOT NULL,
  units INT NOT NULL,
  year_level INT,
  semester ENUM('1','2','summer') DEFAULT '1'
);

-- PREREQUISITES TABLE
CREATE TABLE prerequisites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  prereq_course_id INT NOT NULL,
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (prereq_course_id) REFERENCES courses(id)
);

CREATE TABLE student_grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  grade DECIMAL(5,2),
  status ENUM('PASSED','FAILED','DROPPED','INCOMPLETE') DEFAULT 'PASSED',
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (course_id) REFERENCES courses(id)
);

CREATE TABLE advising_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  professor_id INT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  notes TEXT,
  outcome TEXT,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (professor_id) REFERENCES professors(id)
);

CREATE TABLE ai_recommendations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  recommended_topic VARCHAR(255),
  recommendation_text TEXT,
  risk_flag ENUM('NONE','WARNING','ALERT') DEFAULT 'NONE',
  FOREIGN KEY (session_id) REFERENCES advising_sessions(id)
);

CREATE TABLE predictive_analytics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  prediction_type VARCHAR(100),
  risk_score INT,
  insights TEXT,
  FOREIGN KEY (student_id) REFERENCES students(id)
);


-- Initial mysql
-- Database Schema for Academic Advising System
CREATE DATABASE IF NOT EXISTS advising_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE advising_system;

CREATE TABLE course_grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    grade VARCHAR(5),
    semester VARCHAR(50),
    school_year VARCHAR(20),
    remarks VARCHAR(50),

    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grade_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_grade_id INT NOT NULL,
    period ENUM('prelim', 'midterm', 'semi_final', 'final') NOT NULL,
    component_type ENUM('class_standing', 'exam', 'activity', 'performance') NOT NULL,
    component_name VARCHAR(100),
    score DECIMAL(5,2),
    max_score DECIMAL(5,2),
    weight DECIMAL(5,2), -- percentage weight
    date_recorded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (course_grade_id) REFERENCES course_grades(id) ON DELETE CASCADE,
    INDEX idx_period (course_grade_id, period)
) ENGINE=InnoDB;

CREATE TABLE learning_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL,
    risk_level ENUM('low', 'at_risk', 'good') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    url VARCHAR(500) NOT NULL,
    INDEX idx_course_risk (course_code, risk_level)
) ENGINE=InnoDB;

-- Users table (handles all user types)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'professor', 'admin') NOT NULL DEFAULT 'student',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;
ALTER TABLE users CHANGE password  password_hash VARCHAR(255)  NOT NULL;

-- Student profiles
CREATE TABLE student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    major VARCHAR(100),
    gpa DECIMAL(3,2) DEFAULT 0.00,
    credits_completed INT DEFAULT 0,
    enrollment_year YEAR,
    expected_graduation YEAR,
    academic_standing ENUM('good', 'probation', 'warning') DEFAULT 'good',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Professor profiles
CREATE TABLE professor_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    department VARCHAR(100),
    specialization VARCHAR(200),
    office_location VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Courses catalog
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(200) NOT NULL,
    credits INT NOT NULL,
    department VARCHAR(100),
    level ENUM('freshman', 'sophomore', 'junior', 'senior', 'graduate') NOT NULL,
    prerequisites TEXT, -- JSON array of course_codes
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_course_code (course_code),
    INDEX idx_department (department)
) ENGINE=InnoDB;



-- Student course history
CREATE TABLE course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL, -- e.g., "Fall 2024"
    school_year VARCHAR(20), -- e.g., "2024-2025"
    grade VARCHAR(5), -- A, B+, C, etc.
    status ENUM('enrolled', 'completed', 'dropped', 'failed') DEFAULT 'enrolled',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_student_semester (student_id, semester)
) ENGINE=InnoDB;
select * from course_enrollments;

-- Advising sessions
CREATE TABLE advising_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    professor_id INT NOT NULL,
    session_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    recommendations TEXT, -- JSON array of recommended course_ids
    follow_up_required BOOLEAN DEFAULT FALSE,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'completed',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_professor (professor_id)
) ENGINE=InnoDB;

-- Professor remarks on students
CREATE TABLE professor_remarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    professor_id INT NOT NULL,
    remark TEXT NOT NULL,
    is_private BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- System activity logs
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action)
) ENGINE=InnoDB;




-- Added Tables/Updated table Below:


ALTER TABLE student_profiles 
ADD COLUMN current_section VARCHAR(20) AFTEr major;

-- sections for irregular students
CREATE TABLE IF NOT EXISTS course_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    section VARCHAR(20) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_student_course (student_id, course_id)
) ENGINE=InnoDB;

-- course_grades updated
CREATE TABLE IF NOT EXISTS grade_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_grade_id INT NOT NULL,
    period ENUM('prelim', 'midterm', 'semi_final', 'final') NOT NULL,
    component_type ENUM('class_standing', 'exam', 'activity', 'performance') NOT NULL,
    component_name VARCHAR(100),
    score DECIMAL(5,2),
    max_score DECIMAL(5,2),
    weight DECIMAL(5,2), -- percentage weight
    date_recorded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (course_grade_id) REFERENCES course_grades(id) ON DELETE CASCADE,
    INDEX idx_period (course_grade_id, period)
) ENGINE=InnoDB;

-- for course syllabus
CREATE TABLE IF NOT EXISTS course_syllabi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    professor_id INT,
    file_path VARCHAR(500),
    grading_breakdown JSON, -- stores weight percentages
    topics JSON, -- course topics for AI analysis
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_course_year (course_id, school_year, semester)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_specific_remarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    professor_id INT NOT NULL,
    remark_type ENUM('warning', 'improvement', 'encouragement', 'concern') NOT NULL,
    remark_text TEXT NOT NULL,
    action_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_course (student_id, course_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT,
    insight_type ENUM('performance_trend', 'risk_alert', 'study_recommendation', 'pathway_suggestion') NOT NULL,
    insight_text TEXT NOT NULL,
    confidence_score DECIMAL(3,2),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_acknowledged BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_student_type (student_id, insight_type)
) ENGINE=InnoDB;


-- AI processing log (for debugging)
CREATE TABLE IF NOT EXISTS ai_processing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT,
    operation_type VARCHAR(50),
    status ENUM('success', 'failed', 'pending'),
    error_message TEXT,
    processing_time INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Table to track which professor teaches which course/section
CREATE TABLE IF NOT EXISTS professor_course_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    professor_id INT NOT NULL,
    course_id INT NOT NULL,
    section VARCHAR(50),
    semester VARCHAR(20),
    school_year INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY unique_assignment (professor_id, course_id, section, semester, school_year)
);

-- Copilot VSC suggestion: Add year_level column to student_profiles table
-- Run this SQL query in phpMyAdmin or your MySQL client

ALTER TABLE student_profiles 
ADD COLUMN year_level ENUM('freshman', 'sophomore', 'junior', 'senior', 'graduate') 
AFTER major;

ALTER TABLE advising_sessions 
ADD COLUMN feedback TEXT NULL,
ADD COLUMN performance_rating INT NULL,
ADD COLUMN risk_level ENUM('low', 'medium', 'high') NULL DEFAULT NULL;

ALTER TABLE course_specific_remarks 
MODIFY COLUMN course_id INT NULL;

-- From VS Code to Github
-- Git add ., git commit -m "message", git push

-- Update local from github repo
-- Git pull




--Sample data

USE advising_system;

-- Sample Users (password: "password123" for all)
INSERT INTO users (email, password_hash, role, first_name, last_name, is_verified) VALUES
('admin@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', TRUE),
('prof.smith@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'professor', 'John', 'Smith', TRUE),
('prof.johnson@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'professor', 'Emily', 'Johnson', TRUE),
('alice.student@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Alice', 'Williams', TRUE),
('bob.student@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Bob', 'Davis', TRUE);

-- Student Profiles
INSERT INTO student_profiles (user_id, student_id, major, gpa, credits_completed, enrollment_year, expected_graduation) VALUES
(4, 'STU001', 'Computer Science', 3.45, 45, 2022, 2026),
(5, 'STU002', 'Computer Science', 3.78, 60, 2021, 2025);

-- Professor Profiles
INSERT INTO professor_profiles (user_id, employee_id, department, specialization, office_location) VALUES
(2, 'PROF001', 'Computer Science', 'Artificial Intelligence', 'Building A, Room 301'),
(3, 'PROF002', 'Computer Science', 'Software Engineering', 'Building A, Room 305');

-- Sample Courses
INSERT INTO courses (course_code, course_name, credits, department, level, prerequisites, description) VALUES
('CS101', 'Introduction to Programming', 3, 'Computer Science', 'freshman', '[]', 'Basic programming concepts using Python'),
('CS102', 'Data Structures', 3, 'Computer Science', 'sophomore', '["CS101"]', 'Fundamental data structures and algorithms'),
('CS201', 'Object-Oriented Programming', 3, 'Computer Science', 'sophomore', '["CS101"]', 'OOP concepts using Java'),
('CS202', 'Database Systems', 3, 'Computer Science', 'sophomore', '["CS102"]', 'Relational database design and SQL'),
('CS301', 'Web Development', 3, 'Computer Science', 'junior', '["CS201", "CS202"]', 'Full-stack web development'),
('CS302', 'Software Engineering', 3, 'Computer Science', 'junior', '["CS201"]', 'Software development lifecycle and methodologies'),
('CS401', 'Machine Learning', 3, 'Computer Science', 'senior', '["CS102", "CS202"]', 'Introduction to ML algorithms'),
('CS402', 'Computer Networks', 3, 'Computer Science', 'senior', '["CS202"]', 'Network protocols and architecture'),
('MATH101', 'Calculus I', 4, 'Mathematics', 'freshman', '[]', 'Differential calculus'),
('MATH102', 'Calculus II', 4, 'Mathematics', 'freshman', '["MATH101"]', 'Integral calculus'),
('MATH201', 'Linear Algebra', 3, 'Mathematics', 'sophomore', '["MATH101"]', 'Matrices and vector spaces');

-- Sample Course Enrollments
INSERT INTO course_enrollments (student_id, course_id, semester, grade, status) VALUES
(4, 1, 'Fall 2022', 'A', 'completed'),
(4, 9, 'Fall 2022', 'B+', 'completed'),
(4, 2, 'Spring 2023', 'A-', 'completed'),
(4, 3, 'Spring  2023', 'B', 'completed'),
(4, 10, 'Spring 2023', 'A', 'completed'),
(4, 4, 'Fall 2023', 'A', 'completed'),
(4, 11, 'Fall 2023', 'B+', 'completed'),
(5, 1, 'Fall 2021', 'A', 'completed'),
(5, 2, 'Spring 2022', 'A', 'completed'),
(5, 3, 'Spring 2022', 'A-', 'completed'),
(5, 4, 'Fall 2022', 'A', 'completed'),
(5, 5, 'Spring 2023', 'B+', 'completed'),
(5, 6, 'Spring 2023', 'A', 'completed');

-- Sample Advising Sessions
INSERT INTO advising_sessions (student_id, professor_id, session_date, notes, recommendations, status) VALUES
(4, 2, '2024-01-15 10:00:00', 'Discussed course selection for Spring 2024. Student interested in AI track.', '["CS301", "CS302"]', 'completed'),
(5, 3, '2024-01-20 14:00:00', 'Final year planning. Recommended capstone project topics.', '["CS401", "CS402"]', 'completed');

-- Sample courses
INSERT INTO courses (course_code, course_name, credits, department, level, prerequisites, description, is_active) VALUES
('CS105', 'Foundations of Computing', 3, 'Computer Science', 'freshman', '[]', 'Problem solving and computational thinking basics.', TRUE),
('CS210', 'Algorithms I', 3, 'Computer Science', 'sophomore', '["CS102"]', 'Core algorithm design and analysis.', TRUE),
('CS260', 'Databases I', 3, 'Computer Science', 'sophomore', '["CS101"]', 'Relational modeling and SQL fundamentals.', TRUE),
('CS320', 'Web Application Development', 3, 'Computer Science', 'junior', '["CS201", "CS260"]', 'Building full-stack web apps with modern frameworks.', TRUE),
('CS410', 'Machine Learning Fundamentals', 3, 'Computer Science', 'senior', '["CS210", "CS260"]', 'Supervised and unsupervised learning techniques.', TRUE);

-- Change ? with the user_id of your account.
INSERT INTO student_profiles (user_id, student_id, major, gpa, credits_completed, enrollment_year, expected_graduation, academic_standing)
VALUES (?, 'STU014', 'Computer Science', 3.45, 45, 2022, 2026, 'good')
AS new
ON DUPLICATE KEY UPDATE major = new.major;

-- 2. Add some course enrollments (make sure these course IDs exist first)
INSERT INTO course_enrollments (student_id, course_id, semester, grade, status) VALUES
(14, 1, '1st', 'A', 'completed'),
(14, 2, '2nd', 'A-', 'completed'),
(14, 3, '2nd', 'B+', 'completed'),
(14, 4, '2nd', 'A', 'completed'),
(14, 5, '1st', NULL, 'enrolled');

-- 3. Add an advising session, change qustion mark again
INSERT INTO advising_sessions (student_id, professor_id, session_date, notes, recommendations, status)
VALUES (14, ?, NOW(), 'Discussed Spring 2025 courses', '["CS301", "CS302"]', 'completed');

commit; -- saving

INSERT INTO course_grades (student_id, course_id, school_year, semester)
VALUES 
		(14, 1, '2024-2025', '1st'),
       (14, 2, '2024-2025', '2nd'),
       (14, 3, '2024-2025', '2nd'),
       (14, 4, '2024-2025', '2nd'),
       (14, 5, '2024-2025', '1st');



-- Install Composer
Download from: https://getcomposer.org/download/
or manually(Suggested by Claude):
bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"