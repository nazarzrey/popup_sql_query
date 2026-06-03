<?php
session_start();

// Ambil database config dari cookie
if (isset($_COOKIE['rquery_db_config'])) {
    $db_config = json_decode($_COOKIE['rquery_db_config'], true);
    if ($db_config && is_array($db_config)) {
        define('DB_HOST', $db_config['host']);
        define('DB_USER', $db_config['user']);
        define('DB_PASS', $db_config['pass']);
        define('DB_NAME', $db_config['dbname']);
        define('DB_PORT', isset($db_config['port']) ? $db_config['port'] : 3306);
        define('DB_CHARSET', isset($db_config['charset']) ? $db_config['charset'] : 'utf8mb4');
    }
}

// Fallback config
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'test');
    define('DB_PORT', 3306);
    define('DB_CHARSET', 'utf8mb4');
}

define('MAX_RESULTS', 1000);

header('Content-Type: application/json');

// Verify token
$token = isset($_POST['token']) ? $_POST['token'] : (isset($_GET['token']) ? $_GET['token'] : '');
if ($token !== $_SESSION['rquery_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// Database connection
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action === 'execute') {
    $encodedQuery = isset($_POST['query']) ? $_POST['query'] : '';
    $query = base64_decode($encodedQuery);
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Empty query']);
        exit;
    }
    
    $pdo = getDBConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    try {
        $queryType = strtoupper(trim(explode(' ', $query)[0]));
        
        if (in_array($queryType, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'])) {
            $stmt = $pdo->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > MAX_RESULTS) {
                $rows = array_slice($rows, 0, MAX_RESULTS);
            }
            
            $columns = [];
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
            }
            
            echo json_encode([
                'success' => true,
                'type' => 'select',
                'columns' => $columns,
                'rows' => $rows,
                'rowCount' => count($rows)
            ]);
        } else {
            $affectedRows = $pdo->exec($query);
            $lastInsertId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'type' => 'affected',
                'affectedRows' => $affectedRows,
                'insertId' => $lastInsertId
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>