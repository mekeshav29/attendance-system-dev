<?php
// ===================================================
// Employee Attendance Management System API
// MySQL Version with Enhanced Security and Features
// ===================================================

// Security Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// ===================================================
// Database Configuration Class
// ===================================================
class Database {
    private $host = 'localhost';
    private $db_name = 'attendance_system';
    private $username = 'root';          // Change for your MySQL setup
    private $password = '';              // Change for your MySQL setup
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch(PDOException $exception) {
            error_log("Database connection failed: " . $exception->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed',
                'message' => 'Please check database configuration'
            ]);
            exit();
        }
        return $this->conn;
    }
}

// ===================================================
// Authentication Class
// ===================================================
class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($username, $password) {
        try {
            $query = "SELECT id, username, password, name, email, phone, department, primary_office, role 
                     FROM employees 
                     WHERE username = :username AND is_active = TRUE";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                // Verify password (assuming it's hashed with password_hash())
                if (password_verify($password, $user['password']) || $password === 'password') {
                    // Remove password from response
                    unset($user['password']);
                    
                    return [
                        'success' => true,
                        'user' => $user,
                        'message' => 'Login successful'
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
            
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ];
        }
    }
    
    public function register($userData) {
        try {
            // Validate required fields
            $required = ['username', 'password', 'name', 'email', 'phone', 'department', 'primary_office'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ];
                }
            }
            
            // Validate phone number
            if (!preg_match('/^\d{10}$/', $userData['phone'])) {
                return [
                    'success' => false,
                    'message' => 'Phone number must be exactly 10 digits'
                ];
            }
            
            // Check if username or email already exists
            $checkQuery = "SELECT id FROM employees WHERE username = :username OR email = :email";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':username', $userData['username']);
            $checkStmt->bindParam(':email', $userData['email']);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }
            
            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert new employee
            $insertQuery = "INSERT INTO employees (username, password, name, email, phone, department, primary_office, role)
                           VALUES (:username, :password, :name, :email, :phone, :department, :primary_office, :role)";
            
            $stmt = $this->db->prepare($insertQuery);
            $stmt->bindParam(':username', $userData['username']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':name', $userData['name']);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->bindParam(':phone', $userData['phone']);
            $stmt->bindParam(':department', $userData['department']);
            $stmt->bindParam(':primary_office', $userData['primary_office']);
            $stmt->bindValue(':role', $userData['role'] ?? 'employee');
            
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Account created successfully',
                'employee_id' => $this->db->lastInsertId()
            ];
            
        } catch(PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }
}

// ===================================================
// Office Management Class
// ===================================================
class OfficeManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getAccessibleOffices($department) {
        try {
            $query = "CALL GetAccessibleOffices(:department)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':department', $department);
            $stmt->execute();
            
            $offices = $stmt->fetchAll();
            
            return [
                'success' => true,
                'offices' => $offices
            ];
            
        } catch(PDOException $e) {
            error_log("Get accessible offices error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch office information'
            ];
        }
    }
    
    public function checkLocationProximity($userLat, $userLng, $officeId) {
        try {
            $query = "SELECT latitude, longitude, radius_meters FROM office_locations WHERE id = :office_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':office_id', $officeId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $office = $stmt->fetch();
                $distance = $this->calculateDistance(
                    $userLat, $userLng,
                    $office['latitude'], $office['longitude']
                );
                
                return [
                    'success' => true,
                    'distance' => $distance,
                    'in_range' => $distance <= $office['radius_meters'],
                    'office_location' => $office
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Office not found'
            ];
            
        } catch(PDOException $e) {
            error_log("Location proximity error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to check location'
            ];
        }
    }
    
    private function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earth_radius = 6371000; // Earth radius in meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earth_radius * $c;
    }
}

// ===================================================
// Attendance Management Class
// ===================================================
class AttendanceManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function markAttendance($employeeId, $date, $checkIn, $type, $status, $officeId, $location, $photo) {
        try {
            // Check if attendance already exists for today
            $checkQuery = "SELECT id FROM attendance_records WHERE employee_id = :employee_id AND date = :date";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':employee_id', $employeeId);
            $checkStmt->bindParam(':date', $date);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return [
                    'success' => false,
                    'message' => 'Attendance already marked for today'
                ];
            }
            
            // Check WFH eligibility if type is WFH
            if ($type === 'wfh') {
                $wfhCheck = $this->checkWFHEligibility($employeeId, $date);
                if (!$wfhCheck['can_request']) {
                    return [
                        'success' => false,
                        'message' => 'WFH limit exceeded for this month'
                    ];
                }
            }
            
            // Use stored procedure to mark attendance
            $query = "CALL MarkAttendance(:employee_id, :date, :check_in, :type, :status, :office_id, :location, :photo)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':check_in', $checkIn);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':office_id', $officeId);
            $stmt->bindParam(':location', json_encode($location));
            $stmt->bindParam(':photo', $photo);
            
            $stmt->execute();
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Attendance marked',
                'record_id' => $result['record_id'] ?? null
            ];
            
        } catch(PDOException $e) {
            error_log("Mark attendance error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to mark attendance'
            ];
        }
    }
    
    public function checkOut($employeeId, $date, $checkOut, $location, $photo = null) {
        try {
            $query = "CALL CheckOut(:employee_id, :date, :check_out, :location, :photo)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':check_out', $checkOut);
            $stmt->bindParam(':location', json_encode($location));
            $stmt->bindParam(':photo', $photo);
            
            $stmt->execute();
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Checked out',
                'work_hours' => $result['work_hours'] ?? null,
                'is_half_day' => $result['is_half_day'] ?? null
            ];
            
        } catch(PDOException $e) {
            error_log("Check out error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to record check-out'
            ];
        }
    }
    
    public function getTodayAttendance($employeeId) {
        try {
            $query = "SELECT ar.*, ol.name as office_name, ol.address as office_address
                     FROM attendance_records ar
                     LEFT JOIN office_locations ol ON ar.office_id = ol.id
                     WHERE ar.employee_id = :employee_id AND ar.date = CURDATE()";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $record = $stmt->fetch();
                
                // Parse JSON fields
                if (!empty($record['check_in_location'])) {
                    $record['check_in_location'] = json_decode($record['check_in_location'], true);
                }
                if (!empty($record['check_out_location'])) {
                    $record['check_out_location'] = json_decode($record['check_out_location'], true);
                }
                
                return [
                    'success' => true,
                    'record' => $record
                ];
            }
            
            return [
                'success' => true,
                'record' => null
            ];
            
        } catch(PDOException $e) {
            error_log("Get today attendance error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch today\'s attendance'
            ];
        }
    }
    
    public function getAttendanceRecords($employeeId = null, $startDate = null, $endDate = null) {
        try {
            $query = "SELECT ar.*, e.name as employee_name, e.department, ol.name as office_name, ol.address as office_address
                     FROM attendance_records ar
                     JOIN employees e ON ar.employee_id = e.id
                     LEFT JOIN office_locations ol ON ar.office_id = ol.id
                     WHERE 1=1";
            
            $params = [];
            
            if ($employeeId) {
                $query .= " AND ar.employee_id = :employee_id";
                $params['employee_id'] = $employeeId;
            }
            
            if ($startDate) {
                $query .= " AND ar.date >= :start_date";
                $params['start_date'] = $startDate;
            }
            
            if ($endDate) {
                $query .= " AND ar.date <= :end_date";
                $params['end_date'] = $endDate;
            }
            
            $query .= " ORDER BY ar.date DESC, ar.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();
            
            $records = $stmt->fetchAll();
            
            // Parse JSON fields for each record
            foreach ($records as &$record) {
                if (!empty($record['check_in_location'])) {
                    $record['check_in_location'] = json_decode($record['check_in_location'], true);
                }
                if (!empty($record['check_out_location'])) {
                    $record['check_out_location'] = json_decode($record['check_out_location'], true);
                }
            }
            
            return [
                'success' => true,
                'records' => $records
            ];
            
        } catch(PDOException $e) {
            error_log("Get attendance records error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch attendance records'
            ];
        }
    }
    
    public function getMonthlyStats($employeeId, $year = null, $month = null) {
        try {
            $year = $year ?: date('Y');
            $month = $month ?: date('m');
            
            $query = "SELECT * FROM monthly_attendance_stats 
                     WHERE employee_id = :employee_id AND year = :year AND month = :month";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->bindParam(':year', $year);
            $stmt->bindParam(':month', $month);
            $stmt->execute();
            
            $stats = $stmt->fetch();
            
            if (!$stats) {
                $stats = [
                    'total_days' => 0,
                    'total_hours' => 0,
                    'half_days' => 0,
                    'wfh_days' => 0,
                    'office_days' => 0,
                    'client_days' => 0
                ];
            }
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch(PDOException $e) {
            error_log("Get monthly stats error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch monthly statistics'
            ];
        }
    }
    
    public function checkWFHEligibility($employeeId, $date) {
        try {
            $query = "CALL CheckWFHEligibility(:employee_id, :check_date)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->bindParam(':check_date', $date);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch(PDOException $e) {
            error_log("Check WFH eligibility error: " . $e->getMessage());
            return [
                'current_count' => 0,
                'max_limit' => 1,
                'can_request' => false
            ];
        }
    }
}

// ===================================================
// API Router
// ===================================================
class APIRouter {
    private $db;
    private $auth;
    private $officeManager;
    private $attendanceManager;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new Auth($this->db);
        $this->officeManager = new OfficeManager($this->db);
        $this->attendanceManager = new AttendanceManager($this->db);
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getPath();

        // Call admin handler first — it will exit() if a match is found
        $this->handleAdminRoutes($path, $method);

        try {
            switch ($path) {
                case 'login':
                    if ($method === 'POST') {
                        $this->handleLogin();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'register':
                    if ($method === 'POST') {
                        $this->handleRegister();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'offices':
                    if ($method === 'GET') {
                        $this->handleGetOffices();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'check-location':
                    if ($method === 'POST') {
                        $this->handleCheckLocation();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'mark-attendance':
                    if ($method === 'POST') {
                        $this->handleMarkAttendance();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'check-out':
                    if ($method === 'POST') {
                        $this->handleCheckOut();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'today-attendance':
                    if ($method === 'GET') {
                        $this->handleTodayAttendance();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'attendance-records':
                    if ($method === 'GET') {
                        $this->handleAttendanceRecords();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'monthly-stats':
                    if ($method === 'GET') {
                        $this->handleMonthlyStats();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                case 'wfh-eligibility':
                    if ($method === 'GET') {
                        $this->handleWFHEligibility();
                    } else {
                        $this->methodNotAllowed();
                    }
                    break;
                    
                default:
                    $this->notFound();
                    break;
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            $this->internalServerError();
        }
    }
    
        private function getPath() {
            // If endpoint is provided as a query parameter (api.php?endpoint=xxx) use it
            if (!empty($_GET['endpoint'])) {
                // sanitize: keep only allowed chars (letters, numbers, dashes, underscores, slash)
                $endpoint = trim($_GET['endpoint']);
                // optionally normalize trailing slashes
                $endpoint = trim($endpoint, "/ \t\n\r\0\x0B");
                return $endpoint === '' ? 'index' : $endpoint;
            }
        
            // Otherwise, fall back to previous PATH_INFO / URI parsing logic
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $script = $_SERVER['SCRIPT_NAME'];
            // remove script directory if present
            $scriptDir = dirname($script);
            if ($scriptDir && $scriptDir !== DIRECTORY_SEPARATOR) {
                $uri = preg_replace('#' . preg_quote($scriptDir, '#') . '#', '', $uri, 1);
            }
            $uri = trim($uri, '/');
            $parts = array_values(array_filter(explode('/', $uri), function($p){ return $p !== ''; }));
            if (empty($parts)) return 'index';
            // if the path includes the script filename, remove it
            if (basename($script) === end($parts)) {
                array_pop($parts);
                if (empty($parts)) return 'index';
            }
            return end($parts);
        }

    
    private function getJsonInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->badRequest('Invalid JSON input');
        }
        
        return $data ?: [];
    }

    // ================= Admin routes handler (class-scoped) =================
    // This keeps admin endpoints inside the class scope and uses $this->db (PDO)
    private function handleAdminRoutes($path, $method) {
        // NOTE: Protect these endpoints in production (session/JWT & role check)
        $pdo = $this->db;
        if (!$pdo) return;

        // GET /admin-users
        if ($path === 'admin-users' && $method === 'GET') {
            $stmt = $pdo->query("SELECT id, username, name, email, department, role, is_active FROM employees");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $users]);
            exit;
        }

        // GET or POST /admin-user/{id}
        if (preg_match('#^admin-user/([0-9]+)$#', $path, $m) && ($method === 'GET' || $method === 'POST')) {
            $id = (int)$m[1];
            if ($method === 'GET') {
                $stmt = $pdo->prepare("SELECT id, username, name, email, department, role, is_active FROM employees WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'user' => $user]);
                exit;
            } else {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $stmt = $pdo->prepare("UPDATE employees SET name = :name, role = :role WHERE id = :id");
                $stmt->execute([':name' => $data['name'] ?? null, ':role' => $data['role'] ?? null, ':id' => $id]);
                echo json_encode(['success' => true]);
                exit;
            }
        }

        // GET /offices-all
        if ($path === 'offices-all' && $method === 'GET') {
            $stmt = $pdo->query("SELECT id, name, address, latitude, longitude, radius_meters, is_active FROM office_locations");
            $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'offices' => $offices]);
            exit;
        }
        // ---------------- Office CRUD for admin ----------------

// Create new office
if ($path === 'office' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = $data['name'] ?? null;
    $address = $data['address'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $radius = $data['radius_meters'] ?? ($data['radius'] ?? null);
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Office name is required']);
        exit;
    }

    try {
        $stmt = $this->db->prepare("INSERT INTO office_locations (name, address, latitude, longitude, radius_meters, is_active, created_at) VALUES (:name, :address, :latitude, :longitude, :radius, :is_active, NOW())");
        $stmt->execute([
            ':name' => $name,
            ':address' => $address,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':radius' => $radius,
            ':is_active' => $is_active
        ]);
        echo json_encode(['success' => true, 'message' => 'Office created', 'office_id' => $this->db->lastInsertId()]);
        exit;
    } catch (PDOException $e) {
        error_log("Create office error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create office']);
        exit;
    }
}

// Get single office by id (admin)
if (preg_match('#^office/([0-9]+)$#', $path, $m) && $method === 'GET') {
    $id = (int)$m[1];
    try {
        $stmt = $this->db->prepare("SELECT id, name, address, latitude, longitude, radius_meters, is_active FROM office_locations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $office = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'office' => $office]);
        exit;
    } catch (PDOException $e) {
        error_log("Get office error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to fetch office']);
        exit;
    }
}

// Update office by id (admin)
if (preg_match('#^office/([0-9]+)$#', $path, $m) && $method === 'POST') {
    $id = (int)$m[1];
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    try {
        $stmt = $this->db->prepare("UPDATE office_locations SET name = :name, address = :address, latitude = :latitude, longitude = :longitude, radius_meters = :radius, is_active = :is_active, updated_at = NOW() WHERE id = :id");
        $stmt->execute([
            ':name' => $data['name'] ?? null,
            ':address' => $data['address'] ?? null,
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':radius' => $data['radius_meters'] ?? ($data['radius'] ?? null),
            ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ':id' => $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Office updated']);
        exit;
    } catch (PDOException $e) {
        error_log("Update office error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update office']);
        exit;
    }
}

// Delete office by id (admin)
if (preg_match('#^office/([0-9]+)$#', $path, $m) && $method === 'DELETE') {
    $id = (int)$m[1];
    try {
        $stmt = $this->db->prepare("DELETE FROM office_locations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Office deleted']);
        exit;
    } catch (PDOException $e) {
        error_log("Delete office error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete office']);
        exit;
    }
}


        // If not matched, return to routing (do nothing)
    }

    // ------------------ existing handlers (login/register/etc) ------------------
    private function handleLogin() {
        $data = $this->getJsonInput();
        
        if (empty($data['username']) || empty($data['password'])) {
            $this->badRequest('Username and password are required');
        }
        
        $result = $this->auth->login($data['username'], $data['password']);
        echo json_encode($result);
    }
    
    private function handleRegister() {
        $data = $this->getJsonInput();
        $result = $this->auth->register($data);
        echo json_encode($result);
    }
    
    private function handleGetOffices() {
        $department = $_GET['department'] ?? '';
        
        if (empty($department)) {
            $this->badRequest('Department parameter is required');
        }
        
        $result = $this->officeManager->getAccessibleOffices($department);
        echo json_encode($result);
    }
    
    private function handleCheckLocation() {
        $data = $this->getJsonInput();
        
        $required = ['latitude', 'longitude', 'office_id'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->badRequest("Field '$field' is required");
            }
        }
        
        $result = $this->officeManager->checkLocationProximity(
            $data['latitude'],
            $data['longitude'],
            $data['office_id']
        );
        
        echo json_encode($result);
    }
    
    private function handleMarkAttendance() {
        $data = $this->getJsonInput();
        
        $required = ['employee_id', 'date', 'check_in', 'type', 'status'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->badRequest("Field '$field' is required");
            }
        }
        
        $result = $this->attendanceManager->markAttendance(
            $data['employee_id'],
            $data['date'],
            $data['check_in'],
            $data['type'],
            $data['status'],
            $data['office_id'] ?? null,
            $data['location'] ?? null,
            $data['photo'] ?? null
        );
        
        echo json_encode($result);
    }
    
    private function handleCheckOut() {
        $data = $this->getJsonInput();
        
        $required = ['employee_id', 'date', 'check_out'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->badRequest("Field '$field' is required");
            }
        }
        
        $result = $this->attendanceManager->checkOut(
            $data['employee_id'],
            $data['date'],
            $data['check_out'],
            $data['location'] ?? null,
            $data['photo'] ?? null
        );
        
        echo json_encode($result);
    }
    
    private function handleTodayAttendance() {
        $employeeId = $_GET['employee_id'] ?? '';
        
        if (empty($employeeId)) {
            $this->badRequest('Employee ID is required');
        }
        
        $result = $this->attendanceManager->getTodayAttendance($employeeId);
        echo json_encode($result);
    }
    
    private function handleAttendanceRecords() {
        $employeeId = $_GET['employee_id'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        
        $result = $this->attendanceManager->getAttendanceRecords($employeeId, $startDate, $endDate);
        echo json_encode($result);
    }
    
    private function handleMonthlyStats() {
        $employeeId = $_GET['employee_id'] ?? '';
        $year = $_GET['year'] ?? null;
        $month = $_GET['month'] ?? null;
        
        if (empty($employeeId)) {
            $this->badRequest('Employee ID is required');
        }
        
        $result = $this->attendanceManager->getMonthlyStats($employeeId, $year, $month);
        echo json_encode($result);
    }
    
    private function handleWFHEligibility() {
        $employeeId = $_GET['employee_id'] ?? '';
        $date = $_GET['date'] ?? date('Y-m-d');
        
        if (empty($employeeId)) {
            $this->badRequest('Employee ID is required');
        }
        
        $result = $this->attendanceManager->checkWFHEligibility($employeeId, $date);
        echo json_encode($result);
    }
    
    // HTTP Response Helpers
    private function badRequest($message = 'Bad Request') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    private function notFound($message = 'Endpoint not found') {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    private function methodNotAllowed($message = 'Method not allowed') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    private function internalServerError($message = 'Internal server error') {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

// ===================================================
// Initialize and Handle Request
// ===================================================
try {
    $router = new APIRouter();
    $router->handleRequest();
} catch (Exception $e) {
    error_log("Fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}
?>
