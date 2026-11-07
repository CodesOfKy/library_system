# library_system
system po ng group naten para sa library


C:\xampp\php\php.exe C:\xampp\htdocs\000CAPSTONE\bridge.php
C:\xampp\php\php.exe C:\xampp\htdocs\library_system\bridge.php


ALTER TABLE `students`
  ADD COLUMN `first_name`  varchar(50) NOT NULL DEFAULT '' AFTER `student_id`,
  ADD COLUMN `middle_name` varchar(50) NOT NULL DEFAULT '' AFTER `first_name`,
  ADD COLUMN `last_name`   varchar(50) NOT NULL DEFAULT '' AFTER `middle_name`;

UPDATE `students` SET first_name = 'HATDOG',       middle_name = '',   last_name = 'FOOTLONG'    WHERE id = 22;
UPDATE `students` SET first_name = 'SPONGEBOB',    middle_name = '',   last_name = 'SQUAREPANTS' WHERE id = 23;
UPDATE `students` SET first_name = 'ELIZABETH',    middle_name = 'A.', last_name = 'FRANCISCO'   WHERE id = 24;
UPDATE `students` SET first_name = 'Dutch',        middle_name = '',   last_name = ''            WHERE id = 25;
UPDATE `students` SET first_name = 'NICOLE KYLIE', middle_name = 'A.', last_name = 'FRANCISCO'   WHERE id = 26;

UPDATE `students`
  SET name = CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), NULLIF(last_name, ''));

ALTER TABLE `students` ADD UNIQUE KEY `full_name` (`first_name`, `last_name`);