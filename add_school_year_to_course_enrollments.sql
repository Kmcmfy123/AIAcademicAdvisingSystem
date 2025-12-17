-- Add school_year column to course_enrollments table
-- Run this SQL query in phpMyAdmin or your MySQL client

ALTER TABLE course_enrollments
ADD COLUMN school_year VARCHAR(20) AFTER semester;