<?php
// ajax_motivos.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
if (!currentUser()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión requerida.']);
    exit;
}

// Asegurar que la tabla exista
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS motivos_egreso (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Sembrar los 5 motivos por defecto si la tabla está vacía
    $count = (int)$pdo->query("SELECT COUNT(*) FROM motivos_egreso")->fetchColumn();
    if ($count === 0) {
        $defaults = ['Renuncia', 'Despido', 'Jubilación', 'Obrero', 'Empleado'];
        $stmt_seed = $pdo->prepare("INSERT IGNORE INTO motivos_egreso (nombre) VALUES (?)");
        foreach ($defaults as $d) {
            $stmt_seed->execute([$d]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de BD: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $pdo->query("SELECT id, nombre FROM motivos_egreso ORDER BY nombre ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $items]);
    exit;
}

if ($action === 'add') {
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre === '') {
        echo json_encode(['success' => false, 'error' => 'El nombre del motivo no puede estar vacío.']);
        exit;
    }
    
    // Verificar si ya existe (insensible a mayúsculas)
    $stmt_chk = $pdo->prepare("SELECT id, nombre FROM motivos_egreso WHERE LOWER(nombre) = LOWER(?)");
    $stmt_chk->execute([$nombre]);
    $existing = $stmt_chk->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        echo json_encode(['success' => true, 'data' => $existing, 'message' => 'El motivo ya existía.']);
        exit;
    }

    try {
        $stmt_ins = $pdo->prepare("INSERT INTO motivos_egreso (nombre) VALUES (?)");
        $stmt_ins->execute([$nombre]);
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'data' => ['id' => (int)$id, 'nombre' => $nombre]]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    if ($id <= 0 || $nombre === '') {
        echo json_encode(['success' => false, 'error' => 'ID o nombre inválido.']);
        exit;
    }

    try {
        $stmt_upd = $pdo->prepare("UPDATE motivos_egreso SET nombre = ? WHERE id = ?");
        $stmt_upd->execute([$nombre, $id]);
        echo json_encode(['success' => true, 'data' => ['id' => $id, 'nombre' => $nombre]]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido.']);
        exit;
    }

    try {
        $stmt_del = $pdo->prepare("DELETE FROM motivos_egreso WHERE id = ?");
        $stmt_del->execute([$id]);
        echo json_encode(['success' => true, 'deleted_id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
