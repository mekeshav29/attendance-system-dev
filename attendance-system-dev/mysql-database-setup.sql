-- ===================================================
-- Employee Attendance Management System Database
-- CORRECTED VERSION
-- ===================================================

CREATE DATABASE IF NOT EXISTS attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance_system;

-- ===================================================
-- Users/Employees Table
-- ===================================================
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    department ENUM('IT', 'HR', 'Surveyors', 'Accounts', 'Growth', 'Others') NOT NULL,
    primary_office VARCHAR(10) NOT NULL,  -- <-- FIXED: Was ENUM, now VARCHAR
    role ENUM('employee', 'admin') DEFAULT 'employee',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ===================================================
-- Office Locations Table
-- ===================================================
CREATE TABLE IF NOT EXISTS office_locations (
    id VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    radius_meters INT DEFAULT 50,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- <-- FIXED: Added updated_at
);

-- ===================================================
-- Department Office Access Table
-- ===================================================
CREATE TABLE IF NOT EXISTS department_office_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department ENUM('IT','HR','Surveyors','Accounts','Growth','Others') NOT NULL,
    office_id VARCHAR(10) NOT NULL,
    CONSTRAINT department_office_access_office_fk
      FOREIGN KEY (office_id) REFERENCES office_locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dept_office (department, office_id)
);

-- ===================================================
-- Attendance Records Table
-- ===================================================
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    check_in_time TIME NULL,
    check_out_time TIME NULL,
    type ENUM('office','wfh','client') NOT NULL,
    status ENUM('present','half_day','wfh','client','absent') NOT NULL,
    office_id VARCHAR(10) NULL, -- <-- FIXED: Was INT, now VARCHAR
    check_in_location JSON NULL,
    check_out_location JSON NULL,
    check_in_photo LONGTEXT NULL,
    check_out_photo LONGTEXT NULL,
    total_hours DECIMAL(4,2) DEFAULT 0.00,
    is_half_day BOOLEAN DEFAULT FALSE,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT attendance_records_employee_fk
      FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT attendance_records_office_fk
      FOREIGN KEY (office_id) REFERENCES office_locations(id) ON DELETE SET NULL,
    UNIQUE KEY unique_employee_date (employee_id, date),
    INDEX idx_employee_date (employee_id, date),
    INDEX idx_date (date),
    INDEX idx_type (type),
    INDEX idx_status (status)
);

-- ===================================================
-- WFH Requests Table
-- ===================================================
CREATE TABLE IF NOT EXISTS wfh_requests (
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

-- Insert Office Locations (Using VARCHAR IDs)
INSERT IGNORE INTO office_locations (id, name, address, latitude, longitude, radius_meters, is_active) VALUES
('79',  '79 Office',  'Sector 79, Mohali, Punjab, India', 30.680834, 76.717933, 50, 1),
('105', '105 Office', 'Sector 105, Mohali, Punjab, India', 30.655991, 76.682795, 50, 1);

-- Insert Department Office Access Rules
INSERT IGNORE INTO department_office_access (department, office_id) VALUES
('IT', '79'), ('IT', '105'),
('HR', '79'), ('HR', '105'),
('Surveyors', '79'), ('Surveyors', '105'),
('Accounts', '79'), ('Accounts', '105'),
('Growth', '79'), ('Growth', '105'),
('Others', '79'), ('Others', '105');

-- Insert Default Admin User
INSERT IGNORE INTO employees (username, password, name, email, phone, department, primary_office, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR', 'admin@company.com', '9999999999', 'IT', '79', 'admin');
-- Password is: password

-- Insert Sample Employees
INSERT IGNORE INTO employees (username, password, name, email, phone, department, primary_office, role) VALUES
('john.doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'john.doe@company.com', '9876543210', 'IT', '79', 'employee'),
('jane.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'jane.smith@company.com', '9876543211', 'HR', '105', 'employee'),
('mike.wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Wilson', 'mike.wilson@company.com', '9876543212', 'Surveyors', '79', 'employee'),
('sarah.johnson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Johnson', 'sarah.johnson@company.com', '9876543213', 'Accounts', '105', 'employee');

-- ===================================================
-- Create Views
-- ===================================================
CREATE OR REPLACE VIEW employee_attendance_summary AS
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

CREATE OR REPLACE VIEW monthly_attendance_stats AS
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

CREATE PROCEDURE IF NOT EXISTS GetAccessibleOffices(IN dept_name VARCHAR(20))
BEGIN
    SELECT ol.id, ol.name, ol.address, ol.latitude, ol.longitude, ol.radius_meters
    FROM office_locations ol
    INNER JOIN department_office_access doa ON ol.id = doa.office_id
    WHERE doa.department = dept_name AND ol.is_active = TRUE
    ORDER BY ol.name;
END //

CREATE PROCEDURE IF NOT EXISTS CheckWFHEligibility(IN emp_id INT, IN check_date DATE)
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

CREATE PROCEDURE IF NOT EXISTS MarkAttendance(
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

CREATE PROCEDURE IF NOT EXISTS CheckOut(
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
    
    SELECT 
        ROUND(TIME_TO_SEC(TIMEDIFF(check_out, check_in_time)) / 3600, 2) INTO total_hrs
    FROM attendance_records 
    WHERE employee_id = emp_id AND date = att_date;

    IF total_hrs IS NULL THEN
        SET total_hrs = 0.00;
    END IF;
    
    IF total_hrs < 8.0 THEN
        SET is_half = TRUE;
        SET new_status = 'half_day';
    ELSE
        SET is_half = FALSE;
        SELECT status INTO new_status FROM attendance_records WHERE employee_id = emp_id AND date = att_date;
    END IF;
    
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
-- Create Indexes
-- ===================================================
CREATE INDEX IF NOT EXISTS idx_employees_username ON employees(username);
CREATE INDEX IF NOT EXISTS idx_employees_email ON employees(email);
CREATE INDEX IF NOT EXISTS idx_employees_department ON employees(department);
CREATE INDEX IF NOT EXISTS idx_employees_active ON employees(is_active);

-- ===================================================
-- Verify Installation
-- ===================================================
SELECT 'Database setup completed successfully!' as status;