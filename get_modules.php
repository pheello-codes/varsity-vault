<?php
include 'includes/config.php';

header('Content-Type: application/json');

if (isset($_GET['university_id']) && is_numeric($_GET['university_id'])) {
    $university_id = (int)$_GET['university_id'];
    
    $stmt = $conn->prepare("SELECT module_code, module_name FROM modules WHERE university_id = ? ORDER BY module_code");
    $stmt->bind_param("i", $university_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $modules = [];
    while ($row = $result->fetch_assoc()) {
        $modules[] = $row;
    }
    
    echo json_encode($modules);
} else {
    echo json_encode([]);
}
?>