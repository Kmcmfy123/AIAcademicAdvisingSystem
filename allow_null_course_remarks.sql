-- Allow general remarks without course association
-- Run this SQL query in phpMyAdmin or your MySQL client

ALTER TABLE course_specific_remarks 
MODIFY COLUMN course_id INT NULL;
