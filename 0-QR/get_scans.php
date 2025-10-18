<?php
require_once 'config.php';

header('Content-Type: application/json');

$response = [
    'total_employees' => 0,
    'today_scans' => 0,
    'most_active_employee' => 'N/A',
    'employees_daily_data' => []
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

   
    $stmt = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM employees");
    $response['total_employees'] = $stmt->fetchColumn();

    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE DATE(scan_time) = ?");
    $stmt->execute([$today]);
    $response['today_scans'] = $stmt->fetchColumn();

   
    $stmt = $pdo->prepare("
        SELECT e.name, COUNT(s.id) as scan_count
        FROM scans s
        JOIN employees e ON s.employee_id = e.employee_id
        WHERE DATE(s.scan_time) = ?
        GROUP BY e.name
        ORDER BY scan_count DESC
        LIMIT 1
    ");
    $stmt->execute([$today]);
    $most_active = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($most_active) {
        $response['most_active_employee'] = $most_active['name'];
    }

    
    $employeesData = [];
    $stmt = $pdo->query("SELECT employee_id, name FROM employees ORDER BY name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($employees as $employee) {
        $employee_id = $employee['employee_id'];
        $employee_name = $employee['name'];

        
        $stmt_scans = $pdo->prepare("SELECT scan_time FROM scans WHERE employee_id = ? ORDER BY scan_time ASC");
        $stmt_scans->execute([$employee_id]);
        $employee_scans = $stmt_scans->fetchAll(PDO::FETCH_ASSOC);

        $daily_scans = [];
        foreach ($employee_scans as $scan) {
            $scan_datetime = new DateTime($scan['scan_time']);
            $scan_date = $scan_datetime->format('Y-m-d');
            $scan_time_str = $scan_datetime->format('H:i');

            if (!isset($daily_scans[$scan_date])) {
                $daily_scans[$scan_date] = [
                    'date' => $scan_date,
                    'day_of_week' => $scan_datetime->format('l'), 
                    'scan_times' => []
                ];
            }
            $daily_scans[$scan_date]['scan_times'][] = $scan_time_str;
        }

        
        $processed_daily_data = [];
        foreach ($daily_scans as $date => $data) {
            sort($data['scan_times']); 
            $num_scans = count($data['scan_times']);

            $heure_depart_morning = $num_scans > 0 ? $data['scan_times'][0] : '';
            $heure_avant_pause = $num_scans > 1 ? $data['scan_times'][1] : '';
            $pause_out = $num_scans > 2 ? $data['scan_times'][2] : '';
            $heure_apres_pause = $num_scans > 3 ? $data['scan_times'][3] : '';
            $heure_depart_evening = $num_scans > 4 ? $data['scan_times'][4] : '';

            $processed_daily_data[] = [
                'date' => $data['date'],
                'day_of_week' => $data['day_of_week'],
                'heure_depart_morning' => $heure_depart_morning,
                'heure_avant_pause' => $heure_avant_pause,
                'pause_out' => $pause_out,
                'heure_apres_pause' => $heure_apres_pause,
                'heure_depart_evening' => $heure_depart_evening,
                'total_scans_day' => $num_scans 
            ];
        }

        $employeesData[] = [
            'employee_id' => $employee_id,
            'name' => $employee_name,
            'daily_attendance' => $processed_daily_data
        ];
    }

    $response['employees_daily_data'] = $employeesData;

} catch(PDOException $e) {
    http_response_code(500);
    $response = ['error' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($response);
?> 