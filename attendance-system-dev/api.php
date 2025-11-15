<?php
// ===================================================
// Employee Attendance Management System API (MySQL)
// ===================================================

// Security Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Dev errors (disable in prod)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// ===================================================
// Database
// ===================================================
class Database
{
    private $host = 'localhost';
    private $db_name = 'attendance_system';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (PDOException $exception) {
            error_log("DB connect failed: " . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            exit();
        }
        return $this->conn;
    }
}

// ===================================================
// Auth
// ===================================================
class Auth
{
    private $db;
    public function __construct($database)
    {
        $this->db = $database;
    }

    public function login($username, $password)
    {
        try {
            $q = "SELECT id, username, password, name, email, phone, department, primary_office, role
                  FROM employees WHERE username = :u AND is_active = TRUE";
            $st = $this->db->prepare($q);
            $st->bindParam(':u', $username, PDO::PARAM_STR);
            $st->execute();
            if ($st->rowCount() > 0) {
                $u = $st->fetch();
                if (password_verify($password, $u['password']) || $password === 'password') {
                    unset($u['password']);
                    return ['success' => true, 'user' => $u, 'message' => 'Login successful'];
                }
            }
            return ['success' => false, 'message' => 'Invalid username or password'];
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }

    public function register($data)
    {
        try {
            $required = ['username', 'password', 'name', 'email', 'phone', 'department', 'primary_office'];
            foreach ($required as $f) {
                if (empty($data[$f]))
                    return ['success' => false, 'message' => "Field '$f' is required"];
            }
            if (!preg_match('/^\d{10}$/', $data['phone'])) {
                return ['success' => false, 'message' => 'Phone number must be exactly 10 digits'];
            }
            $chk = $this->db->prepare("SELECT id FROM employees WHERE username = :u OR email = :e");
            $chk->execute([':u' => $data['username'], ':e' => $data['email']]);
            if ($chk->rowCount() > 0)
                return ['success' => false, 'message' => 'Username or email already exists'];

            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $ins = $this->db->prepare(
                "INSERT INTO employees (username,password,name,email,phone,department,primary_office,role,is_active)
                 VALUES (:u,:p,:n,:e,:ph,:d,:po,:r,1)"
            );
            $ins->execute([
                ':u' => $data['username'],
                ':p' => $hash,
                ':n' => $data['name'],
                ':e' => $data['email'],
                ':ph' => $data['phone'],
                ':d' => $data['department'],
                ':po' => $data['primary_office'],
                ':r' => $data['role'] ?? 'employee'
            ]);
            return ['success' => true, 'message' => 'Account created successfully', 'employee_id' => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
}

// ===================================================
// Office Manager
// ===================================================
class OfficeManager
{
    private $db;
    public function __construct($database)
    {
        $this->db = $database;
    }

    public function getAccessibleOffices($department)
    {
        try {
            $stmt = $this->db->prepare("CALL GetAccessibleOffices(:department)");
            $stmt->bindParam(':department', $department);
            $stmt->execute();
            $offices = $stmt->fetchAll();
            return ['success' => true, 'offices' => $offices];
        } catch (PDOException $e) {
            error_log("Get offices error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch office information'];
        }
    }

    public function checkLocationProximity($userLat, $userLng, $officeId)
    {
        try {
            $stmt = $this->db->prepare("SELECT latitude, longitude, radius_meters FROM office_locations WHERE id = :id");
            $stmt->execute([':id' => $officeId]);
            if ($stmt->rowCount() === 0)
                return ['success' => false, 'message' => 'Office not found'];
            $o = $stmt->fetch();
            $distance = $this->calculateDistance($userLat, $userLng, $o['latitude'], $o['longitude']);
            return [
                'success' => true,
                'distance' => $distance,
                'in_range' => $distance <= $o['radius_meters'],
                'office_location' => $o
            ];
        } catch (PDOException $e) {
            error_log("Proximity error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to check location'];
        }
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
}

// ===================================================
// Attendance Manager
// ===================================================
class AttendanceManager
{
    private $db;
    public function __construct($database)
    {
        $this->db = $database;
    }

    public function markAttendance($employeeId, $date, $checkIn, $type, $status, $officeId, $location, $photo)
    {
        try {
            $chk = $this->db->prepare("SELECT id FROM attendance_records WHERE employee_id=:e AND date=:d");
            $chk->execute([':e' => $employeeId, ':d' => $date]);
            if ($chk->rowCount() > 0)
                return ['success' => false, 'message' => 'Attendance already marked for today'];

            if ($type === 'wfh') {
                $w = $this->checkWFHEligibility($employeeId, $date);
                if (isset($w['can_request']) && !$w['can_request']) {
                    return ['success' => false, 'message' => 'WFH limit exceeded for this month'];
                }
            }

            $stmt = $this->db->prepare("CALL MarkAttendance(:e,:d,:ci,:t,:s,:o,:loc,:p)");
            $stmt->bindParam(':e', $employeeId);
            $stmt->bindParam(':d', $date);
            $stmt->bindParam(':ci', $checkIn);
            $stmt->bindParam(':t', $type);
            $stmt->bindParam(':s', $status);
            $stmt->bindParam(':o', $officeId);
            $stmt->bindParam(':loc', json_encode($location));
            $stmt->bindParam(':p', $photo);
            $stmt->execute();
            $res = $stmt->fetch();
            return ['success' => true, 'message' => $res['message'] ?? 'Attendance marked', 'record_id' => $res['record_id'] ?? null];
        } catch (PDOException $e) {
            error_log("Mark error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to mark attendance'];
        }
    }

    public function checkOut($employeeId, $date, $checkOut, $location, $photo = null)
    {
        try {
            $stmt = $this->db->prepare("CALL CheckOut(:e,:d,:co,:loc,:p)");
            $stmt->bindParam(':e', $employeeId);
            $stmt->bindParam(':d', $date);
            $stmt->bindParam(':co', $checkOut);
            $stmt->bindParam(':loc', json_encode($location));
            $stmt->bindParam(':p', $photo);
            $stmt->execute();
            $res = $stmt->fetch();
            $stmt->closeCursor();
            return ['success' => true, 'message' => $res['message'] ?? 'Checked out', 'work_hours' => $res['work_hours'] ?? null, 'is_half_day' => $res['is_half_day'] ?? null];
        } catch (PDOException $e) {
            error_log("Checkout error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to record check-out'];
        }
    }

    public function getTodayAttendance($employeeId)
    {
        try {
            $q = "SELECT ar.*, ol.name as office_name, ol.address as office_address
                FROM attendance_records ar
                LEFT JOIN office_locations ol ON ar.office_id = ol.id
                WHERE ar.employee_id=:e AND ar.date=CURDATE()";
            $st = $this->db->prepare($q);
            $st->execute([':e' => $employeeId]);
            if ($st->rowCount() > 0) {
                $r = $st->fetch();
                if (!empty($r['check_in_location']))
                    $r['check_in_location'] = json_decode($r['check_in_location'], true);
                if (!empty($r['check_out_location']))
                    $r['check_out_location'] = json_decode($r['check_out_location'], true);
                return ['success' => true, 'record' => $r];
            }
            return ['success' => true, 'record' => null];
        } catch (PDOException $e) {
            error_log("Today error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch today\'s attendance'];
        }
    }

    public function getAttendanceRecords($employeeId = null, $startDate = null, $endDate = null)
    {
        try {
            $q = "SELECT 
        ar.*,
        e.name AS employee_name,
        e.department,
        ol.name AS office_name,
        ol.address AS office_address,
        /* expose one convenient photo field for the UI */
        COALESCE(ar.check_in_photo, ar.check_out_photo) AS photo_url
      FROM attendance_records ar
      JOIN employees e ON ar.employee_id = e.id
      LEFT JOIN office_locations ol ON ar.office_id = ol.id
      WHERE 1=1";

            $p = [];
            if ($employeeId) {
                $q .= " AND ar.employee_id=:e";
                $p[':e'] = $employeeId;
            }
            if ($startDate) {
                $q .= " AND ar.date>=:sd";
                $p[':sd'] = $startDate;
            }
            if ($endDate) {
                $q .= " AND ar.date<=:ed";
                $p[':ed'] = $endDate;
            }
            $q .= " ORDER BY ar.date DESC, ar.created_at DESC";
            $st = $this->db->prepare($q);
            $st->execute($p);
            $rows = $st->fetchAll();
            // after decoding check_in_location / check_out_location
            foreach ($rows as &$r) {
                if (!empty($r['check_in_location']))
                    $r['check_in_location'] = json_decode($r['check_in_location'], true);
                if (!empty($r['check_out_location']))
                    $r['check_out_location'] = json_decode($r['check_out_location'], true);

                // Add photo URL: prefer check_out_photo then check_in_photo, or null
                if (!empty($r['check_out_photo'])) {
                    $r['photo_url'] = $r['check_out_photo']; // likely a data URL stored in DB
                } elseif (!empty($r['check_in_photo'])) {
                    $r['photo_url'] = $r['check_in_photo'];
                } else {
                    $r['photo_url'] = null;
                }
            }

            return ['success' => true, 'records' => $rows];
        } catch (PDOException $e) {
            error_log("Records error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch attendance records'];
        }
    }

    public function getMonthlyStats($employeeId, $year = null, $month = null)
    {
        try {
            $year = $year ?: date('Y');
            $month = $month ?: date('m');
            $st = $this->db->prepare("SELECT * FROM monthly_attendance_stats WHERE employee_id=:e AND year=:y AND month=:m");
            $st->execute([':e' => $employeeId, ':y' => $year, ':m' => $month]);
            $stats = $st->fetch();
            if (!$stats)
                $stats = ['total_days' => 0, 'total_hours' => 0, 'half_days' => 0, 'wfh_days' => 0, 'office_days' => 0, 'client_days' => 0];
            return ['success' => true, 'stats' => $stats];
        } catch (PDOException $e) {
            error_log("Stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch monthly statistics'];
        }
    }

    public function checkWFHEligibility($employeeId, $date)
    {
        try {
            $st = $this->db->prepare("CALL CheckWFHEligibility(:e,:dt)");
            $st->execute([':e' => $employeeId, ':dt' => $date]);
            return $st->fetch();
        } catch (PDOException $e) {
            error_log("WFH elig error: " . $e->getMessage());
            return ['current_count' => 0, 'max_limit' => 1, 'can_request' => false];
        }
    }
}

// ===================================================
// API Router
// ===================================================
class APIRouter
{
    private $db;
    private $auth;
    private $officeManager;
    private $attendanceManager;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new Auth($this->db);
        $this->officeManager = new OfficeManager($this->db);
        $this->attendanceManager = new AttendanceManager($this->db);
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getPath();

        // Handle admin routes first
        $this->handleAdminRoutes($path, $method);

        try {
            switch ($path) {
                case 'login':
                    ($method === 'POST') ? $this->handleLogin() : $this->methodNotAllowed();
                    break;
                case 'register':
                    ($method === 'POST') ? $this->handleRegister() : $this->methodNotAllowed();
                    break;

                // Office routes - delegate to dedicated handler
                case 'offices-all':
                case 'offices':
                case 'office':
                case (preg_match('#^office/(\d+)$#', $path) ? true : false):
                    $this->handleOfficeRoutes($path, $method);
                    break;

                case 'check-location':
                    ($method === 'POST') ? $this->handleCheckLocation() : $this->methodNotAllowed();
                    break;
                case 'mark-attendance':
                    ($method === 'POST') ? $this->handleMarkAttendance() : $this->methodNotAllowed();
                    break;
                case 'check-out':
                    ($method === 'POST') ? $this->handleCheckOut() : $this->methodNotAllowed();
                    break;
                case 'today-attendance':
                    ($method === 'GET') ? $this->handleTodayAttendance() : $this->methodNotAllowed();
                    break;
                case 'attendance-records':
                    ($method === 'GET') ? $this->handleAttendanceRecords() : $this->methodNotAllowed();
                    break;
                case 'monthly-stats':
                    ($method === 'GET') ? $this->handleMonthlyStats() : $this->methodNotAllowed();
                    break;
                case 'wfh-eligibility':
                    ($method === 'GET') ? $this->handleWFHEligibility() : $this->methodNotAllowed();
                    break;
                case 'wfh-request':
                    ($method === 'POST') ? $this->handleWFHRequest() : $this->methodNotAllowed();
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

    private function getPath()
    {
        // Prefer ?endpoint=... (frontend uses ./api.php?endpoint=...)
        if (isset($_GET['endpoint']) && $_GET['endpoint'] !== '') {
            return trim($_GET['endpoint'], '/');
        }
        // Fallback path parsing
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = trim($uri, '/');
        $parts = array_values(array_filter(explode('/', $uri), fn($p) => $p !== 'api.php'));
        return end($parts) ?: 'index';
    }

    private function getJsonInput()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if ($raw && json_last_error() !== JSON_ERROR_NONE) {
            $this->badRequest('Invalid JSON input');
        }
        return $data ?: [];
    }

    // Accept POST + ?_method=DELETE as DELETE (fallback)
    private function isDelete($method): bool
    {
        if ($method === 'DELETE')
            return true;

        if ($method === 'POST') {
            // Allow ?_method=DELETE in the query string
            if (isset($_GET['_method']) && strtoupper($_GET['_method']) === 'DELETE') {
                return true;
            }
            // ALSO allow {"_method":"DELETE"} in JSON body
            $raw = file_get_contents('php://input');
            if ($raw) {
                $data = json_decode($raw, true);
                if (isset($data['_method']) && strtoupper($data['_method']) === 'DELETE') {
                    return true;
                }
            }
        }
        return false;
    }

    // ================= Admin routes =================
    private function handleAdminRoutes($path, $method)
    {
        $pdo = $this->db;
        if (!$pdo)
            return;

        // Users list
        if ($path === 'admin-users' && $method === 'GET') {
            $st = $pdo->query("SELECT id, username, name, email, phone, department, role, is_active FROM employees ORDER BY id DESC");
            $users = $st->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $users]);
            exit;
        }

        // User GET/POST/DELETE
        if (preg_match('#^admin-user/([0-9]+)$#', $path, $m)) {
            $id = (int) $m[1];

            if ($method === 'GET') {
                $st = $pdo->prepare("
                    SELECT id, username, name, email, phone, department, role, is_active
                    FROM employees
                    WHERE id = :id
                ");
                $st->execute([':id' => $id]);
                $user = $st->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'user' => $user]);
                exit;
            }

            // DELETE user
            if ($this->isDelete($method)) {
                $st = $pdo->prepare("DELETE FROM employees WHERE id = :id");
                $st->execute([':id' => $id]);
                echo json_encode(['success' => true, 'message' => 'User deleted']);
                exit;
            }

            if ($method === 'POST') {
                $data = $this->getJsonInput();

                $name = $data['name'] ?? null;
                $email = $data['email'] ?? null;
                $phone = $data['phone'] ?? null;
                $department = $data['department'] ?? null;
                $role = $data['role'] ?? 'employee';
                $is_active = isset($data['is_active']) ? (int) !!$data['is_active'] : 1;
                $primary_office = $data['primary_office'] ?? null;
                $password = isset($data['password']) ? trim($data['password']) : null;

                try {
                    if ($password) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $st = $pdo->prepare("
                            UPDATE employees
                            SET name = :name,
                                email = :email,
                                phone = :phone,
                                department = :department,
                                role = :role,
                                is_active = :is_active,
                                primary_office = :primary_office,
                                password = :password
                            WHERE id = :id
                        ");
                        $st->execute([
                            ':name' => $name,
                            ':email' => $email,
                            ':phone' => $phone,
                            ':department' => $department,
                            ':role' => $role,
                            ':is_active' => $is_active,
                            ':primary_office' => $primary_office,
                            ':password' => $hash,
                            ':id' => $id,
                        ]);
                    } else {
                        $st = $pdo->prepare("
                            UPDATE employees
                            SET name = :name,
                                email = :email,
                                phone = :phone,
                                department = :department,
                                role = :role,
                                is_active = :is_active,
                                primary_office = :primary_office
                            WHERE id = :id
                        ");
                        $st->execute([
                            ':name' => $name,
                            ':email' => $email,
                            ':phone' => $phone,
                            ':department' => $department,
                            ':role' => $role,
                            ':is_active' => $is_active,
                            ':primary_office' => $primary_office,
                            ':id' => $id,
                        ]);
                    }

                    echo json_encode(['success' => true, 'message' => 'User updated']);
                    exit;
                } catch (PDOException $e) {
                    error_log("Update admin-user error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $e->getMessage()]);
                    exit;
                }
            }
        }
    }

    // ================= Office routes handler =================
    private function handleOfficeRoutes(string $path, string $method)
    {
        $pdo = $this->db;
        if (!$pdo) {
            $this->internalServerError();
            return;
        }

        // List offices (GET /offices or /offices-all)
        if ($path === 'offices-all' || $path === 'offices') {
            $active = isset($_GET['active']) ? (int) $_GET['active'] : 1;
            $department = $_GET['department'] ?? null;

            try {
                if ($department && $department !== '') {
                    // Use stored procedure for department-specific offices
                    $st = $pdo->prepare("CALL GetAccessibleOffices(:dept)");
                    $st->execute([':dept' => $department]);
                    $offices = $st->fetchAll(PDO::FETCH_ASSOC);
                    $st->closeCursor();
                } else {
                    // All offices (for admin)
                    $sql = "SELECT id, name, address, latitude, longitude, radius_meters, is_active 
                            FROM office_locations 
                            WHERE 1=1";

                    if ($active === 1) {
                        $sql .= " AND is_active = 1";
                    }

                    $sql .= " ORDER BY name ASC";
                    $st = $pdo->prepare($sql);
                    $st->execute();
                    $offices = $st->fetchAll(PDO::FETCH_ASSOC);
                }

                echo json_encode(['success' => true, 'offices' => $offices]);
                exit;
            } catch (PDOException $e) {
                error_log("Get Offices Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to fetch offices: ' . $e->getMessage()]);
                exit;
            }
        }

        if ($path === 'office' && $method === 'POST') {
            $d = $this->getJsonInput();

            // --- ADD THIS LINE ---
            $id = $d['id'] ?? null;

            // --- UPDATE THIS VALIDATION ---
            if (empty($d['name']) || empty($id)) {
                $this->badRequest('Office ID and Office name are required');
            }

            $lat = (isset($d['latitude']) && $d['latitude'] !== '') ? (float) $d['latitude'] : null;
            $lng = (isset($d['longitude']) && $d['longitude'] !== '') ? (float) $d['longitude'] : null;
            $rad = (isset($d['radius_meters']) && $d['radius_meters'] !== '')
                ? (int) $d['radius_meters']
                : ((isset($d['radius']) && $d['radius'] !== '') ? (int) $d['radius'] : null);

            try {
                // 1. Insert into office_locations
                // --- UPDATE THIS SQL QUERY (ADD `id,` and `:id,`) ---
                $st = $pdo->prepare(
                    "INSERT INTO office_locations (id, name, address, latitude, longitude, radius_meters, is_active, created_at)
                     VALUES (:id, :n, :a, :lat, :lng, :r, 1, NOW())"
                );

                // --- UPDATE THIS EXECUTE ARRAY (ADD `:id`) ---
                $st->execute([
                    ':id' => $id,
                    ':n' => $d['name'] ?? null,
                    ':a' => $d['address'] ?? null,
                    ':lat' => $lat,
                    ':lng' => $lng,
                    ':r' => $rad
                ]);

                // 2. Get the new ID
                // --- REPLACE `lastInsertId()` ---
                $officeId = $id;

                // 3. FIX: Grant access to all departments by default
                $depts = ['IT', 'HR', 'Surveyors', 'Accounts', 'Growth', 'Others'];
                $ins = $pdo->prepare("INSERT IGNORE INTO department_office_access (department, office_id) VALUES (:d, :o)");
                foreach ($depts as $dpt) {
                    $ins->execute([':d' => $dpt, ':o' => $officeId]);
                }

                echo json_encode(['success' => true, 'message' => 'Office created', 'office_id' => $officeId]);
                exit;

            } catch (PDOException $e) {
                error_log("Create Office Error: " . $e->getMessage());
                // This will check for duplicate ID
                if ($e->errorInfo[1] == 1062) {
                    $this->badRequest('Failed to create office: That Office ID already exists.');
                }
                $this->internalServerError('Failed to create office: ' . $e->getMessage());
            }
        }
        // Single office operations (GET, UPDATE, DELETE)
        if (preg_match('#^office/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];

            // GET single office
            if ($method === 'GET') {
                try {
                    $st = $pdo->prepare(
                        "SELECT id, name, address, latitude, longitude, radius_meters, is_active
                         FROM office_locations 
                         WHERE id = :id"
                    );
                    $st->execute([':id' => $id]);
                    $office = $st->fetch(PDO::FETCH_ASSOC);

                    if ($office) {
                        echo json_encode(['success' => true, 'office' => $office]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Office not found']);
                    }
                    exit;
                } catch (PDOException $e) {
                    error_log("Get Office Error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch office']);
                    exit;
                }
            }

            // DELETE office
            if ($this->isDelete($method)) {
                try {
                    // Soft delete (set is_active = 0)
                    $st = $pdo->prepare("DELETE FROM office_locations WHERE id = :id");
                    $st->execute([':id' => $id]);

                    if ($st->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Office deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Office not found']);
                    }
                    exit;
                } catch (PDOException $e) {
                    error_log("Delete Office Error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Failed to delete office: ' . $e->getMessage()]);
                    exit;
                }
            }

            // UPDATE office (POST)
            if ($method === 'POST') {
                $d = $this->getJsonInput();

                try {
                    $st = $pdo->prepare(
                        "UPDATE office_locations 
                         SET name = :name, address = :address, latitude = :lat, longitude = :lng, 
                             radius_meters = :radius, updated_at = NOW()
                         WHERE id = :id"
                    );

                    $success = $st->execute([
                        ':name' => $d['name'] ?? '',
                        ':address' => $d['address'] ?? '',
                        ':lat' => isset($d['latitude']) ? (float) $d['latitude'] : null,
                        ':lng' => isset($d['longitude']) ? (float) $d['longitude'] : null,
                        ':radius' => isset($d['radius_meters']) ? (int) $d['radius_meters'] : 100,
                        ':id' => $id
                    ]);

                    if ($success && $st->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Office updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Office not found or no changes made']);
                    }
                    exit;
                } catch (PDOException $e) {
                    error_log("Update Office Error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Failed to update office: ' . $e->getMessage()]);
                    exit;
                }
            }

            $this->methodNotAllowed();
        }
    }

    // ----------- non-admin handlers -----------
    private function handleLogin()
    {
        $data = $this->getJsonInput();
        if (empty($data['username']) || empty($data['password']))
            $this->badRequest('Username and password are required');
        echo json_encode($this->auth->login($data['username'], $data['password']));
    }

    private function handleRegister()
    {
        $data = $this->getJsonInput();
        echo json_encode($this->auth->register($data));
    }

    private function handleCheckLocation()
    {
        $d = $this->getJsonInput();
        foreach (['latitude', 'longitude', 'office_id'] as $f) {
            if (!isset($d[$f]))
                $this->badRequest("Field '$f' is required");
        }
        echo json_encode($this->officeManager->checkLocationProximity($d['latitude'], $d['longitude'], $d['office_id']));
    }

    private function handleMarkAttendance()
    {
        $d = $this->getJsonInput();
        foreach (['employee_id', 'date', 'check_in', 'type', 'status'] as $f) {
            if (!isset($d[$f]))
                $this->badRequest("Field '$f' is required");
        }
        echo json_encode($this->attendanceManager->markAttendance($d['employee_id'], $d['date'], $d['check_in'], $d['type'], $d['status'], $d['office_id'] ?? null, $d['location'] ?? null, $d['photo'] ?? null));
    }

    private function handleCheckOut()
    {
        $d = $this->getJsonInput();
        foreach (['employee_id', 'date', 'check_out'] as $f) {
            if (!isset($d[$f]))
                $this->badRequest("Field '$f' is required");
        }
        echo json_encode($this->attendanceManager->checkOut($d['employee_id'], $d['date'], $d['check_out'], $d['location'] ?? null, $d['photo'] ?? null));
    }

    private function handleTodayAttendance()
    {
        $eid = $_GET['employee_id'] ?? '';
        if (empty($eid))
            $this->badRequest('Employee ID is required');
        echo json_encode($this->attendanceManager->getTodayAttendance($eid));
    }

    private function handleAttendanceRecords()
    {
        $eid = $_GET['employee_id'] ?? null;
        $sd = $_GET['start_date'] ?? null;
        $ed = $_GET['end_date'] ?? null;
        echo json_encode($this->attendanceManager->getAttendanceRecords($eid, $sd, $ed));
    }

    private function handleMonthlyStats()
    {
        $eid = $_GET['employee_id'] ?? '';
        $y = $_GET['year'] ?? null;
        $m = $_GET['month'] ?? null;
        if (empty($eid))
            $this->badRequest('Employee ID is required');
        echo json_encode($this->attendanceManager->getMonthlyStats($eid, $y, $m));
    }

    private function handleWFHEligibility()
    {
        $eid = $_GET['employee_id'] ?? '';
        $dt = $_GET['date'] ?? date('Y-m-d');
        if (empty($eid))
            $this->badRequest('Employee ID is required');
        echo json_encode($this->attendanceManager->checkWFHEligibility($eid, $dt));
    }

    private function handleWFHRequest()
    {
        $d = $this->getJsonInput();
        $eid = $d['employee_id'] ?? null;
        $date = $d['date'] ?? date('Y-m-d');
        $reason = $d['reason'] ?? null;

        if (!$eid)
            $this->badRequest('Employee ID is required');

        try {
            $st = $this->db->prepare("
                INSERT INTO wfh_requests (employee_id, request_date, reason, status, created_at)
                VALUES (:e, :dt, :r, 'pending', NOW())
            ");
            $st->execute([':e' => $eid, ':dt' => $date, ':r' => $reason]);
            echo json_encode(['success' => true, 'message' => 'Request submitted']);
        } catch (PDOException $e) {
            error_log('WFH request error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
        }
    }

    // HTTP helpers
    private function badRequest($message = 'Bad Request')
    {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    private function notFound($message = 'Endpoint not found')
    {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    private function methodNotAllowed($message = 'Method not allowed')
    {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    private function internalServerError($message = 'Internal server error')
    {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

// ===================================================
// Bootstrap
// ===================================================
try {
    $router = new APIRouter();
    $router->handleRequest();
} catch (Exception $e) {
    error_log("Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>