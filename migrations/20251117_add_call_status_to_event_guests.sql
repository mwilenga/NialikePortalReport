-- Migration: add call_status column to event_guests
ALTER TABLE `event_guests`
ADD COLUMN `call_status` VARCHAR(50) NOT NULL DEFAULT 'NOT CALLED' AFTER `call_attendance_feedback`;

-- Optional: seed existing rows with NOT CALLED where NULL
UPDATE `event_guests` SET `call_status` = 'NOT CALLED' WHERE `call_status` IS NULL OR `call_status` = '';
