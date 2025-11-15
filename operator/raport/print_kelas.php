<?php
/**
 * Print Raport Per Kelas - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk mencetak raport semua siswa dalam satu kelas
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$id_kelas = intval($_GET['id_kelas'] ?? 0);
$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';

if (!$id_kelas) {
    redirect('operator/raport/list.php');
}

// Get kelas data
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = ? AND status = 'active'");
$stmt->execute([$id_kelas]);
$kelas = $stmt->fetch();

if (!$kelas) {
    redirect('operator/raport/list.php');
}

// Get all siswa in kelas
$stmt = $pdo->prepare("SELECT u.*, k.nama_kelas, k.tingkat
                      FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.role = 'siswa' 
                      AND u.status = 'active'
                      AND uk.id_kelas = ?
                      AND uk.tahun_ajaran = ?
                      AND uk.semester = ?
                      ORDER BY u.nama ASC");
$stmt->execute([$id_kelas, $tahun_ajaran, $semester]);
$siswa_list = $stmt->fetchAll();

if (empty($siswa_list)) {
    die('Tidak ada siswa di kelas ini untuk semester yang dipilih.');
}

// Get sekolah data
$stmt = $pdo->query("SELECT * FROM sekolah LIMIT 1");
$sekolah = $stmt->fetch();

// Get active template
$stmt = $pdo->query("SELECT * FROM template_raport WHERE is_active = 1 LIMIT 1");
$template = $stmt->fetch();

// Urutan mapel sesuai template raport
$mapel_order = [
    'PA&PBP' => 1,
    'P.PANQ' => 2,
    'B.INDO' => 3,
    'MAT' => 4,
    'IPA' => 5,
    'IPS' => 6,
    'B.INGG' => 7,
    'PRAK' => 8,
    'PJOK' => 9,
    'INFOR' => 10,
    'B.JAWA' => 11
];

// Check if columns exist
$aktif_column_exists = false;
$nip_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM penilaian_manual LIKE 'aktif'");
    $aktif_column_exists = $check_stmt->rowCount() > 0;
    $check_stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'nip'");
    $nip_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Ignore
}

// Get logo HTML
$logo_raport_html = '';
if ($template && !empty($template['logo_raport']) && file_exists(UPLOAD_PROFILE . '/' . $template['logo_raport'])) {
    $logo_raport_url = asset_url('uploads/profile/' . $template['logo_raport']);
    $logo_raport_html = '<img src="' . escape($logo_raport_url) . '" alt="Logo Raport" class="logo-kop-surat" />';
} elseif ($sekolah && !empty($sekolah['logo']) && file_exists(UPLOAD_PROFILE . '/' . $sekolah['logo'])) {
    $logo_url = asset_url('uploads/profile/' . $sekolah['logo']);
    $logo_raport_html = '<img src="' . escape($logo_url) . '" alt="Logo Sekolah" class="logo-kop-surat" />';
}

$logo_html = '';
if ($sekolah && !empty($sekolah['logo']) && file_exists(UPLOAD_PROFILE . '/' . $sekolah['logo'])) {
    $logo_url = asset_url('uploads/profile/' . $sekolah['logo']);
    $logo_html = '<img src="' . escape($logo_url) . '" alt="Logo Sekolah" class="logo-sekolah" />';
}

// Function to build tabel nilai for a student
function build_tabel_nilai($id_siswa, $tahun_ajaran, $semester, $pdo, $aktif_column_exists, $mapel_order) {
    if ($aktif_column_exists) {
        $stmt = $pdo->prepare("SELECT m.id as id_mapel, m.nama_mapel, m.kode_mapel,
                              COALESCE(pm.nilai_uts, 0) as nilai_uts,
                              COALESCE(pm.nilai_akhir, 0) as nilai_akhir,
                              pm.status
                              FROM mapel m
                              LEFT JOIN penilaian_manual pm ON pm.id_mapel = m.id
                                AND pm.id_siswa = ?
                                AND pm.tahun_ajaran = ?
                                AND pm.semester = ?
                                AND pm.status = 'approved'
                                AND pm.aktif = 1");
    } else {
        $stmt = $pdo->prepare("SELECT m.id as id_mapel, m.nama_mapel, m.kode_mapel,
                              COALESCE(pm.nilai_uts, 0) as nilai_uts,
                              COALESCE(pm.nilai_akhir, 0) as nilai_akhir,
                              pm.status
                              FROM mapel m
                              LEFT JOIN penilaian_manual pm ON pm.id_mapel = m.id
                                AND pm.id_siswa = ?
                                AND pm.tahun_ajaran = ?
                                AND pm.semester = ?
                                AND pm.status = 'approved'");
    }
    $stmt->execute([$id_siswa, $tahun_ajaran, $semester]);
    $penilaian_list_all = $stmt->fetchAll();
    
    // Sort berdasarkan urutan template
    usort($penilaian_list_all, function($a, $b) use ($mapel_order) {
        $order_a = $mapel_order[$a['kode_mapel']] ?? 999;
        $order_b = $mapel_order[$b['kode_mapel']] ?? 999;
        if ($order_a == $order_b) {
            return strcmp($a['nama_mapel'], $b['nama_mapel']);
        }
        return $order_a - $order_b;
    });
    
    $tabel_nilai = '';
    if (empty($penilaian_list_all)) {
        $tabel_nilai = '<tr><td colspan="3" style="text-align: center;">Belum ada data penilaian</td></tr>';
    } else {
        $no = 1;
        foreach ($penilaian_list_all as $penilaian) {
            $nilai_display = $penilaian['nilai_uts'] > 0 ? number_format($penilaian['nilai_uts'], 0) : '-';
            $tabel_nilai .= '<tr>';
            $tabel_nilai .= '<td>' . $no++ . '</td>';
            $tabel_nilai .= '<td>' . escape($penilaian['nama_mapel']) . '</td>';
            $tabel_nilai .= '<td>' . $nilai_display . '</td>';
            $tabel_nilai .= '</tr>';
        }
    }
    return $tabel_nilai;
}

// Function to get wali kelas
function get_wali_kelas($id_kelas, $tahun_ajaran, $semester, $pdo, $nip_column_exists) {
    $nama_wali_kelas = '-';
    $nip_wali_kelas = '-';
    
    if ($nip_column_exists) {
        $stmt = $pdo->prepare("SELECT u.nama, u.nip
                              FROM wali_kelas wk
                              INNER JOIN users u ON wk.id_guru = u.id
                              WHERE wk.id_kelas = ? 
                              AND wk.tahun_ajaran = ? 
                              AND wk.semester = ?
                              LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT u.nama, u.username as nip
                              FROM wali_kelas wk
                              INNER JOIN users u ON wk.id_guru = u.id
                              WHERE wk.id_kelas = ? 
                              AND wk.tahun_ajaran = ? 
                              AND wk.semester = ?
                              LIMIT 1");
    }
    $stmt->execute([$id_kelas, $tahun_ajaran, $semester]);
    $wali_kelas = $stmt->fetch();
    if ($wali_kelas) {
        $nama_wali_kelas = $wali_kelas['nama'] ?? '-';
        $nip_wali_kelas = $wali_kelas['nip'] ?? '-';
    }
    return [$nama_wali_kelas, $nip_wali_kelas];
}

// Get wali kelas
list($nama_wali_kelas, $nip_wali_kelas) = get_wali_kelas($id_kelas, $tahun_ajaran, $semester, $pdo, $nip_column_exists);

// If template exists, use it; otherwise use default
if ($template) {
    $html_template = $template['html_content'];
    $css_content = $template['css_content'];
} else {
    // Use default template
    require_once __DIR__ . '/../../includes/template_raport_laporan_hasil_belajar.php';
    $template_data = get_template_raport_laporan_hasil_belajar();
    $html_template = $template_data['html_content'];
    $css_content = $template_data['css_content'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Kelas <?php echo escape($kelas['nama_kelas']); ?> - Semester <?php echo ucfirst($semester); ?></title>
    <style>
        <?php echo $css_content; ?>
        .page-break {
            page-break-after: always;
            margin-bottom: 50px;
        }
        .raport-page {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php foreach ($siswa_list as $index => $siswa): ?>
        <div class="raport-page <?php echo $index > 0 ? 'page-break' : ''; ?>">
            <?php
            // Build tabel nilai for this student
            $tabel_nilai = build_tabel_nilai($siswa['id'], $tahun_ajaran, $semester, $pdo, $aktif_column_exists, $mapel_order);
            
            // Replace variables in template
            $html_content = $html_template;
            $html_content = str_replace('{{LOGO_RAPORT}}', $logo_raport_html, $html_content);
            $html_content = str_replace('{{LOGO_SEKOLAH}}', $logo_html, $html_content);
            $html_content = str_replace('{{LOGO_KOP_SURAT}}', $logo_html, $html_content);
            $html_content = str_replace('{{PEMERINTAH_KABUPATEN}}', escape($sekolah['pemerintah_kabupaten'] ?? 'PEMERINTAH KABUPATEN TULUNGAGUNG'), $html_content);
            $html_content = str_replace('{{DINAS_PENDIDIKAN}}', escape($sekolah['dinas_pendidikan'] ?? 'DINAS PENDIDIKAN'), $html_content);
            $html_content = str_replace('{{NAMA_SEKOLAH}}', escape($sekolah['nama_sekolah'] ?? 'NAMA SEKOLAH'), $html_content);
            $html_content = str_replace('{{NSS}}', escape($sekolah['nss'] ?? ''), $html_content);
            $html_content = str_replace('{{NPSN}}', escape($sekolah['npsn'] ?? ''), $html_content);
            $html_content = str_replace('{{ALAMAT_SEKOLAH}}', escape($sekolah['alamat'] ?? ''), $html_content);
            $html_content = str_replace('{{NO_TELP_SEKOLAH}}', escape($sekolah['no_telp'] ?? ''), $html_content);
            $html_content = str_replace('{{KODE_POS}}', escape($sekolah['kode_pos'] ?? ''), $html_content);
            $html_content = str_replace('{{NAMA_SISWA}}', escape($siswa['nama']), $html_content);
            $html_content = str_replace('{{NIS}}', escape($siswa['nis'] ?? $siswa['username']), $html_content);
            $html_content = str_replace('{{KELAS}}', escape($siswa['nama_kelas']), $html_content);
            $html_content = str_replace('{{TAHUN_PELAJARAN}}', escape($tahun_ajaran), $html_content);
            $html_content = str_replace('{{SEMESTER}}', ucfirst($semester), $html_content);
            $html_content = str_replace('{{NAMA_WALI_KELAS}}', escape($nama_wali_kelas), $html_content);
            $html_content = str_replace('{{NIP_WALI_KELAS}}', escape($nip_wali_kelas), $html_content);
            $html_content = str_replace('{{TABEL_NILAI}}', $tabel_nilai, $html_content);
            
            echo $html_content;
            ?>
        </div>
    <?php endforeach; ?>
</body>
</html>

