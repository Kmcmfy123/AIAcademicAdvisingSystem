-- Add year_level column to student_profiles table
-- Run this SQL query in phpMyAdmin or your MySQL client

ALTER TABLE student_profiles 
ADD COLUMN year_level ENUM('freshman', 'sophomore', 'junior', 'senior', 'graduate') 
AFTER major;
