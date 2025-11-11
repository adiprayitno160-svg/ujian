<?php
/**
 * API: Request Token - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'request') {
    $sesi_id = intval($_POST['sesi_id'] ?? 0);
    
    if ($sesi_id <= 0) {
        $response['message'] = 'ID sesi tidak valid';
        echo json_encode($response);
        exit;
    }
    
    // Verify sesi exists and student has access
    $sesi = get_sesi($sesi_id);
    if (!$sesi) {
        $response['message'] = 'Sesi tidak ditemukan';
        echo json_encode($response);
        exit;
    }
    
    // Get ujian info to check if this is an assessment
    $ujian = get_ujian($sesi['id_ujian']);
    if (!$ujian) {
        $response['message'] = 'Ujian tidak ditemukan';
        echo json_encode($response);
        exit;
    }
    
    // Hanya izinkan request token untuk assessment yang dikelola operator
    $is_assessment = !empty($ujian['tipe_asesmen']);
    $token_required_setting = isset($sesi['token_required']) && ($sesi['token_required'] == 1 || $sesi['token_required'] === '1');
    
    if (!$is_assessment || !$token_required_setting) {
        $response['message'] = 'Request token hanya tersedia untuk assessment yang dikelola operator';
        echo json_encode($response);
        exit;
    }
    
    // Check if there's already a pending request
    try {
        $stmt = $pdo->prepare("SELECT id, status FROM token_request 
                              WHERE id_sesi = ? AND id_siswa = ? 
                              AND status IN ('pending', 'approved')
                              ORDER BY requested_at DESC LIMIT 1");
        $stmt->execute([$sesi_id, $_SESSION['user_id']]);
        $existing_request = $stmt->fetch();
        
        if ($existing_request) {
            if ($existing_request['status'] === 'pending') {
                $response['message'] = 'Anda sudah memiliki request token yang sedang menunggu persetujuan';
                echo json_encode($response);
                exit;
            } elseif ($existing_request['status'] === 'approved') {
                $response['message'] = 'Request token Anda sudah disetujui. Silakan cek token yang diberikan.';
                echo json_encode($response);
                exit;
            }
        }
        
        // Create new request
        $stmt = $pdo->prepare("INSERT INTO token_request 
                              (id_sesi, id_siswa, status, ip_address, device_info) 
                              VALUES (?, ?, 'pending', ?, ?)");
        $stmt->execute([
            $sesi_id, 
            $_SESSION['user_id'], 
            get_client_ip(), 
            get_device_info()
        ]);
        
        $response['success'] = true;
        $response['message'] = 'Request token berhasil dikirim. Silakan tunggu persetujuan dari operator.';
        $response['request_id'] = $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Error creating token request: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan saat mengirim request: ' . $e->getMessage();
    }
    
} elseif ($action === 'check_status') {
    $sesi_id = intval($_POST['sesi_id'] ?? 0);
    
    if ($sesi_id <= 0) {
        $response['message'] = 'ID sesi tidak valid';
        echo json_encode($response);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT tr.*, t.token, u.nama as approved_by_name
                              FROM token_request tr
                              LEFT JOIN token_ujian t ON tr.id_token = t.id
                              LEFT JOIN users u ON tr.approved_by = u.id
                              WHERE tr.id_sesi = ? AND tr.id_siswa = ?
                              ORDER BY tr.requested_at DESC LIMIT 1");
        $stmt->execute([$sesi_id, $_SESSION['user_id']]);
        $request = $stmt->fetch();
        
        if ($request) {
            $response['success'] = true;
            $response['request'] = [
                'id' => $request['id'],
                'status' => $request['status'],
                'requested_at' => $request['requested_at'],
                'approved_at' => $request['approved_at'],
                'token' => $request['token'] ?? null,
                'approved_by' => $request['approved_by_name'] ?? null,
                'notes' => $request['notes'] ?? null
            ];
        } else {
            $response['success'] = true;
            $response['request'] = null;
        }
    } catch (PDOException $e) {
        error_log("Error checking token request status: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan saat memeriksa status request';
    }
}

echo json_encode($response);

