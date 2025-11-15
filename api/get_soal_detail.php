<?php
/**
 * Get Soal Detail API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Returns soal detail as JSON for AJAX requests
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

global $pdo;

try {
    // Get soal
    $stmt = $pdo->prepare("SELECT s.*, m.nama_mapel, u.judul as judul_ujian, u2.nama as nama_guru
                           FROM soal s
                           INNER JOIN ujian u ON s.id_ujian = u.id
                           INNER JOIN mapel m ON u.id_mapel = m.id
                           INNER JOIN users u2 ON u.id_guru = u2.id
                           WHERE s.id = ?");
    $stmt->execute([$id]);
    $soal = $stmt->fetch();
    
    if (!$soal) {
        echo json_encode(['success' => false, 'message' => 'Soal not found']);
        exit;
    }
    
    // Get matching items if exists
    $matching_items = [];
    if ($soal['tipe_soal'] === 'matching') {
        $stmt = $pdo->prepare("SELECT * FROM soal_matching WHERE id_soal = ? ORDER BY urutan ASC");
        $stmt->execute([$id]);
        $matching_items = $stmt->fetchAll();
    }
    
    // Parse opsi
    $opsi = [];
    if ($soal['opsi_json']) {
        $opsi = json_decode($soal['opsi_json'], true) ?? [];
    }
    
    // Build HTML
    $html = '<div class="soal-detail">';
    $html .= '<h5>' . escape($soal['pertanyaan']) . '</h5>';
    
    $html .= '<table class="table table-bordered mt-3">';
    $html .= '<tr><th width="150">Mata Pelajaran</th><td>' . escape($soal['nama_mapel']) . '</td></tr>';
    $html .= '<tr><th>Tipe Soal</th><td>' . ucfirst(str_replace('_', ' ', $soal['tipe_soal'])) . '</td></tr>';
    $html .= '<tr><th>Bobot</th><td>' . $soal['bobot'] . '</td></tr>';
    
    if ($soal['gambar']) {
        $html .= '<tr><th>Media</th><td>';
        if ($soal['media_type'] === 'gambar') {
            $html .= '<img src="' . escape(base_url('uploads/' . $soal['gambar'])) . '" class="img-thumbnail" style="max-width: 300px;">';
        } elseif ($soal['media_type'] === 'video') {
            $html .= '<video controls class="img-thumbnail" style="max-width: 300px;"><source src="' . escape(base_url('uploads/' . $soal['gambar'])) . '"></video>';
        }
        $html .= '</td></tr>';
    }
    
    if (!empty($opsi)) {
        $html .= '<tr><th>Opsi Jawaban</th><td>';
        $html .= '<ul>';
        foreach ($opsi as $key => $value) {
            $html .= '<li><strong>' . escape($key) . ':</strong> ' . escape($value) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</td></tr>';
    }
    
    if ($soal['tipe_soal'] === 'matching' && !empty($matching_items)) {
        $html .= '<tr><th>Item Matching</th><td>';
        $html .= '<table class="table table-sm table-bordered">';
        $html .= '<thead><tr><th>Item Kiri</th><th>Item Kanan</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($matching_items as $item) {
            $html .= '<tr><td>' . escape($item['item_kiri']) . '</td><td>' . escape($item['item_kanan']) . '</td></tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</td></tr>';
    }
    
    if ($soal['kunci_jawaban']) {
        $html .= '<tr><th>Kunci Jawaban</th><td><strong>' . escape($soal['kunci_jawaban']) . '</strong></td></tr>';
    }
    
    $html .= '</table>';
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
} catch (PDOException $e) {
    error_log("Get soal detail error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}







