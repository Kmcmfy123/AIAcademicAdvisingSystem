-- Create table to track which professor teaches which course/section
CREATE TABLE IF NOT EXISTS professor_course_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    professor_id INT NOT NULL,
    course_id INT NOT NULL,
    section VARCHAR(50),
    semester VARCHAR(20),
    school_year INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (professor_id, course_id, section, semester, school_year)
);

-- Add index for faster lookups
CREATE INDEX idx_professor_id ON professor_course_assignments(professor_id);
CREATE INDEX idx_course_id ON professor_course_assignments(course_id);
CREATE INDEX idx_semester ON professor_course_assignments(semester, school_year);
