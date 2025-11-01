-- ===================================================
-- Employee Attendance Management System Database
-- MySQL Version with Enhanced Features
-- ===================================================

CREATE DATABASE IF NOT EXISTS attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance_system;

-- ===================================================
-- Users/Employees Table
-- ===================================================
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    department ENUM('IT', 'HR', 'Surveyors', 'Accounts', 'Growth', 'Others') NOT NULL,
    primary_office ENUM('79', '105') NOT NULL,
    role ENUM('employee', 'admin') DEFAULT 'employee',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ===================================================
-- Office Locations Table
-- ===================================================
CREATE TABLE office_locations (
    id VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    radius_meters INT DEFAULT 50,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===================================================
-- Department Office Access Table
-- ===================================================
CREATE TABLE department_office_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department ENUM('IT', 'HR', 'Surveyors', 'Accounts', 'Growth', 'Others') NOT NULL,
    office_id VARCHAR(10) NOT NULL,
    FOREIGN KEY (office_id) REFERENCES office_locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dept_office (department, office_id)
);

-- ===================================================
-- Attendance Records Table
-- ===================================================
CREATE TABLE attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    check_in_time TIME NULL,
    check_out_time TIME NULL,
    type ENUM('office', 'wfh', 'client') NOT NULL,
    status ENUM('present', 'half_day', 'wfh', 'client', 'absent') NOT NULL,
    office_id VARCHAR(10) NULL,
    check_in_location JSON NULL,
    check_out_location JSON NULL,
    check_in_photo LONGTEXT NULL,
    check_out_photo LONGTEXT NULL,
    total_hours DECIMAL(4,2) DEFAULT 0.00,
    is_half_day BOOLEAN DEFAULT FALSE,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (office_id) REFERENCES office_locations(id) ON DELETE SET NULL,
    UNIQUE KEY unique_employee_date (employee_id, date),
    
    INDEX idx_employee_date (employee_id, date),
    INDEX idx_date (date),
    INDEX idx_type (type),
    INDEX idx_status (status)
);

-- ===================================================
-- WFH Requests Table (for approval workflow)
-- ===================================================
CREATE TABLE wfh_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    requested_date DATE NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT NULL,
    admin_response TEXT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES employees(id) ON DELETE SET NULL,
    UNIQUE KEY unique_employee_request_date (employee_id, requested_date),
    
    INDEX idx_employee (employee_id),
    INDEX idx_status (status),
    INDEX idx_requested_date (requested_date)
);

-- ===================================================
-- Insert Default Data
-- ===================================================

-- Insert Office Locations
INSERT INTO office_locations (id, name, address, latitude, longitude, radius_meters) VALUES
('79', '79 Office', 'Sector 79, Mohali, Punjab, India', 30.680897, 76.718099, 50),
('105', '105 Office', 'Sector 105, Mohali, Punjab, India', 30.655895, 76.682552, 50);

-- Insert Department Office Access Rules
INSERT INTO department_office_access (department, office_id) VALUES
('IT', '79'),
('IT', '105'),
('HR', '79'),
('HR', '105'),
('Surveyors', '79'),  -- Only 79 office
('Accounts', '79'),
('Accounts', '105'),
('Growth', '79'),
('Growth', '105'),
('Others', '79'),
('Others', '105');

-- Insert Default Admin User
INSERT INTO employees (username, password, name, email, phone, department, primary_office, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@company.com', '9999999999', 'IT', '79', 'admin');
-- Password is: password

-- Insert Sample Employees
INSERT INTO employees (username, password, name, email, phone, department, primary_office, role) VALUES
('john.doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'john.doe@company.com', '9876543210', 'IT', '79', 'employee'),
('jane.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'jane.smith@company.com', '9876543211', 'HR', '105', 'employee'),
('mike.wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Wilson', 'mike.wilson@company.com', '9876543212', 'Surveyors', '79', 'employee'),
('sarah.johnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Johnson', 'sarah.johnson@company.com', '9876543213', 'Accounts', '105', 'employee');

-- Insert Sample Attendance Records
INSERT INTO attendance_records (employee_id, date, check_in_time, check_out_time, type, status, office_id, total_hours, is_half_day) VALUES
(2, CURDATE() - INTERVAL 1 DAY, '09:00:00', '18:00:00', 'office', 'present', '79', 9.00, FALSE),
(3, CURDATE() - INTERVAL 1 DAY, '09:15:00', '17:45:00', 'office', 'present', '105', 8.50, FALSE),
(4, CURDATE() - INTERVAL 1 DAY, '10:00:00', NULL, 'wfh', 'wfh', NULL, 0.00, FALSE),
(5, CURDATE() - INTERVAL 2 DAY, '09:30:00', '16:30:00', 'office', 'half_day', '79', 7.00, TRUE);

-- ===================================================
-- Create Views for Easy Data Access
-- ===================================================

-- View: Employee Attendance Summary
CREATE VIEW employee_attendance_summary AS
SELECT 
    e.id as employee_id,
    e.name as employee_name,
    e.department,
    e.role,
    ar.date,
    ar.check_in_time,
    ar.check_out_time,
    ar.type,
    ar.status,
    ar.total_hours,
    ar.is_half_day,
    ol.name as office_name,
    ol.address as office_address
FROM employees e
LEFT JOIN attendance_records ar ON e.id = ar.employee_id
LEFT JOIN office_locations ol ON ar.office_id = ol.id
WHERE e.is_active = TRUE;

-- View: Monthly Attendance Stats
CREATE VIEW monthly_attendance_stats AS
SELECT 
    employee_id,
    YEAR(date) as year,
    MONTH(date) as month,
    COUNT(*) as total_days,
    SUM(total_hours) as total_hours,
    SUM(CASE WHEN is_half_day = TRUE THEN 1 ELSE 0 END) as half_days,
    SUM(CASE WHEN type = 'wfh' THEN 1 ELSE 0 END) as wfh_days,
    SUM(CASE WHEN type = 'office' THEN 1 ELSE 0 END) as office_days,
    SUM(CASE WHEN type = 'client' THEN 1 ELSE 0 END) as client_days
FROM attendance_records
GROUP BY employee_id, YEAR(date), MONTH(date);

-- ===================================================
-- Stored Procedures
-- ===================================================

DELIMITER //

-- Procedure: Get Accessible Offices for Department
DROP PROCEDURE IF EXISTS GetAccessibleOffices //
CREATE PROCEDURE GetAccessibleOffices(IN dept_name VARCHAR(20))
BEGIN
    SELECT ol.id, ol.name, ol.address, ol.latitude, ol.longitude, ol.radius_meters
    FROM office_locations ol
    INNER JOIN department_office_access doa ON ol.id = doa.office_id
    WHERE doa.department = dept_name AND ol.is_active = TRUE
    ORDER BY ol.name;
END //

-- Procedure: Check WFH Eligibility
CREATE PROCEDURE CheckWFHEligibility(IN emp_id INT, IN check_date DATE)
BEGIN
    DECLARE wfh_count INT DEFAULT 0;
    DECLARE monthly_limit INT DEFAULT 1;
    
    SELECT COUNT(*) INTO wfh_count
    FROM attendance_records 
    WHERE employee_id = emp_id 
    AND type = 'wfh'
    AND YEAR(date) = YEAR(check_date)
    AND MONTH(date) = MONTH(check_date);
    
    SELECT 
        emp_id as employee_id,
        wfh_count as current_count,
        monthly_limit as max_limit,
        (wfh_count < monthly_limit) as can_request;
END //

-- Procedure: Mark Attendance
CREATE PROCEDURE MarkAttendance(
    IN emp_id INT,
    IN att_date DATE,
    IN check_in TIME,
    IN att_type VARCHAR(20),
    IN att_status VARCHAR(20),
    IN off_id VARCHAR(10),
    IN location_data JSON,
    IN photo_data LONGTEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO attendance_records (
        employee_id, date, check_in_time, type, status, 
        office_id, check_in_location, check_in_photo
    ) VALUES (
        emp_id, att_date, check_in, att_type, att_status,
        off_id, location_data, photo_data
    );
    
    COMMIT;
    
    SELECT 'Attendance marked successfully' as message, LAST_INSERT_ID() as record_id;
END //

-- Procedure: Check Out
CREATE PROCEDURE CheckOut(
    IN emp_id INT,
    IN att_date DATE,
    IN check_out TIME,
    IN location_data JSON,
    IN photo_data LONGTEXT
)
BEGIN
    DECLARE total_hrs DECIMAL(4,2) DEFAULT 0.00;
    DECLARE is_half BOOLEAN DEFAULT FALSE;
    DECLARE new_status VARCHAR(20);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Calculate work hours
    SELECT 
        ROUND(TIME_TO_SEC(TIMEDIFF(check_out, check_in_time)) / 3600, 2) INTO total_hrs
    FROM attendance_records 
    WHERE employee_id = emp_id AND date = att_date;
    
    -- Determine if half day
    IF total_hrs < 8.0 THEN
        SET is_half = TRUE;
        SET new_status = 'half_day';
    ELSE
        SET is_half = FALSE;
        SELECT status INTO new_status FROM attendance_records WHERE employee_id = emp_id AND date = att_date;
    END IF;
    
    -- Update record
    UPDATE attendance_records 
    SET 
        check_out_time = check_out,
        check_out_location = location_data,
        check_out_photo = photo_data,
        total_hours = total_hrs,
        is_half_day = is_half,
        status = new_status,
        updated_at = CURRENT_TIMESTAMP
    WHERE employee_id = emp_id AND date = att_date;
    
    COMMIT;
    
    SELECT 
        'Check-out successful' as message,
        total_hrs as work_hours,
        is_half as is_half_day;
END //

DELIMITER ;

-- ===================================================
-- Create Indexes for Performance
-- ===================================================
CREATE INDEX idx_employees_username ON employees(username);
CREATE INDEX idx_employees_email ON employees(email);
CREATE INDEX idx_employees_department ON employees(department);
CREATE INDEX idx_employees_active ON employees(is_active);

-- ===================================================
-- Grant Permissions (Optional - for specific user)
-- ===================================================
-- CREATE USER 'attendance_user'@'localhost' IDENTIFIED BY 'secure_password_123';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON attendance_system.* TO 'attendance_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ===================================================
-- Verify Installation
-- ===================================================
SELECT 'Database setup completed successfully!' as status;
SELECT COUNT(*) as total_employees FROM employees;
SELECT COUNT(*) as total_offices FROM office_locations;
SELECT COUNT(*) as sample_records FROM attendance_records;
