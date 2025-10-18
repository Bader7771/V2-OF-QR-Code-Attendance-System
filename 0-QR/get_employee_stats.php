<?php
require_once 'config.php';

header('Content-Type: application/json');

$employee_id = $_GET['employee_id'] ?? '';
$yearly = isset($_GET['yearly']) && $_GET['yearly'] === 'true';

if (empty($employee_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee ID is required']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $response = [
        'total_days' => 0,
        'avg_scans_per_day' => 0,
        'most_active_month' => '-',
        'attendance_rate' => 0,
        'yearly_attendance' => []
    ];


    $current_year = date('Y');

    
    $stmt = $pdo->prepare("
        SELECT DATE(scan_time) as date, TIME(scan_time) as time
        FROM scans 
        WHERE employee_id = ? 
        AND YEAR(scan_time) = ?
        ORDER BY scan_time ASC
    ");
    $stmt->execute([$employee_id, $current_year]);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

   
    $daily_attendance = [];
    foreach ($scans as $scan) {
        $date = $scan['date'];
        if (!isset($daily_attendance[$date])) {
            $daily_attendance[$date] = [
                'date' => $date,
                'day_of_week' => date('l', strtotime($date)),
                'scan_times' => []
            ];
        }
        $daily_attendance[$date]['scan_times'][] = $scan['time'];
    }

    
    $total_days = count($daily_attendance);
    $total_scans = count($scans);
    $avg_scans_per_day = $total_days > 0 ? round($total_scans / $total_days, 1) : 0;

   
    $working_days = 0;
    $current_date = new DateTime("$current_year-01-01");
    $end_date = new DateTime("$current_year-12-31");
    while ($current_date <= $end_date) {
        $day_of_week = $current_date->format('N');
        if ($day_of_week <= 5) { 
            $working_days++;
        }
        $current_date->modify('+1 day');
    }
    $attendance_rate = $working_days > 0 ? round(($total_days / $working_days) * 100, 1) : 0;

  
    $monthly_scans = [];
    foreach ($scans as $scan) {
        $month = date('F', strtotime($scan['date']));
        $monthly_scans[$month] = ($monthly_scans[$month] ?? 0) + 1;
    }
    arsort($monthly_scans);
    $most_active_month = !empty($monthly_scans) ? array_key_first($monthly_scans) : '-';

    
    foreach ($daily_attendance as $date => $data) {
        sort($data['scan_times']);
        $num_scans = count($data['scan_times']);

        $response['yearly_attendance'][] = [
            'date' => $data['date'],
            'day_of_week' => $data['day_of_week'],
            'heure_depart_morning' => $num_scans > 0 ? $data['scan_times'][0] : '',
            'heure_avant_pause' => $num_scans > 1 ? $data['scan_times'][1] : '',
            'pause_out' => $num_scans > 2 ? $data['scan_times'][2] : '',
            'heure_apres_pause' => $num_scans > 3 ? $data['scan_times'][3] : '',
            'total_scans_day' => $num_scans
        ];
    }

   
    usort($response['yearly_attendance'], function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $response['total_days'] = $total_days;
    $response['avg_scans_per_day'] = $avg_scans_per_day;
    $response['most_active_month'] = $most_active_month;
    $response['attendance_rate'] = $attendance_rate;

    echo json_encode($response);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>