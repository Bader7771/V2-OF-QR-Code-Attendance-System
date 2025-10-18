<?php
header('Content-Type: application/json');
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $employeeId = $data['employeeId'] ?? '';
    
    if (empty($employeeId)) {
        $response['message'] = 'Employee ID is required';
        echo json_encode($response);
        exit;
    }

    try {

        $stmt = $pdo->prepare("INSERT IGNORE INTO employees (employee_id, name) VALUES (?, ?)");
        $stmt->execute([$employeeId, $employeeId]);

 
        $today = date('Y-m-d');
        

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE employee_id = ? AND DATE(scan_time) = ?");
        $stmt->execute([$employeeId, $today]);
        $scanCount = $stmt->fetchColumn();

        if ($scanCount >= 4) {
            $response['message'] = "⚠️ Employee $employeeId already scanned 4 times today.";
        } else {
 
            $stmt = $pdo->prepare("INSERT INTO scans (employee_id, scan_time) VALUES (?, NOW())");
            $stmt->execute([$employeeId]);
            
            $scanCount++;
            $response['success'] = true;
            $response['message'] = "✅ Scan #$scanCount recorded at " . date('H:i:s') . " for $employeeId";
        }
    } catch (PDOException $e) {
        $response['message'] = "Database error: " . $e->getMessage();
    }
}

echo json_encode($response);
?> 