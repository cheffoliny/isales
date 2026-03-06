<?php
include_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Нямате достъп.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$office = (int)($data['office'] ?? 0);
$info = trim($data['info'] ?? '');

if (!$name || !$office) {
    echo json_encode(['success' => false, 'message' => 'Липсват задължителни данни.']);
    exit;
}

// Генерираме случаен num между 100 и 9999
$num = rand(100, 9999);

$db = db_connect('sod');

$stmt = $db->prepare("INSERT INTO objects (id_status, id_office, num, name, operativ_info) VALUES (1, ?, ?, ?, ?)");
$stmt->bind_param('iiss', $office, $num, $name, $info);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$db->close();