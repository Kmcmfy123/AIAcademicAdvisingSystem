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
    grade VARCHAR(5), -- A, B+, C, etc.
    status ENUM('enrolled', 'completed', 'dropped', 'failed') DEFAULT 'enrolled',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_student_semester (student_id, semester)
) ENGINE=InnoDB;

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




-- From VS Code to Github
-- Git add ., git commit -m "message", git push

-- Update local from github repo
-- Git pull
