<?php
/**
 * Print Raport - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk siswa mencetak raport mereka
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';

// Get siswa data
$stmt = $pdo->prepare("SELECT u.*, k.id as id_kelas, k.nama_kelas, k.tingkat
                      FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.id = ? 
                      AND uk.tahun_ajaran = ? 
                      AND uk.semester = ?");
$stmt->execute([$_SESSION['user_id'], $tahun_ajaran, $semester]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('siswa/raport/list.php');
}

// Get wali kelas data
$nama_wali_kelas = '-';
$nip_wali_kelas = '-';
if (!empty($siswa['id_kelas'])) {
    // Check if nip column exists
    $nip_column_exists = false;
    try {
        $check_stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'nip'");
        $nip_column_exists = $check_stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $nip_column_exists = false;
    }
    
    if ($nip_column_exists) {
        $stmt = $pdo->prepare("SELECT u.nama, u.nip
                              FROM wali_kelas wk
                              INNER JOIN users u ON wk.id_guru = u.id
                              WHERE wk.id_kelas = ? 
                              AND wk.tahun_ajaran = ? 
                              AND wk.semester = ?
                              LIMIT 1");
    } else {
        // Fallback: use username if nip column doesn't exist
        $stmt = $pdo->prepare("SELECT u.nama, u.username as nip
                              FROM wali_kelas wk
                              INNER JOIN users u ON wk.id_guru = u.id
                              WHERE wk.id_kelas = ? 
                              AND wk.tahun_ajaran = ? 
                              AND wk.semester = ?
                              LIMIT 1");
    }
    $stmt->execute([$siswa['id_kelas'], $tahun_ajaran, $semester]);
    $wali_kelas = $stmt->fetch();
    if ($wali_kelas) {
        $nama_wali_kelas = $wali_kelas['nama'] ?? '-';
        $nip_wali_kelas = $wali_kelas['nip'] ?? '-';
    }
}

// Get penilaian - tampilkan semua mapel
// Untuk raport tengah semester, gunakan nilai_uts
// Hanya yang sudah aktif (diterbitkan)
// Check if aktif column exists
$aktif_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM penilaian_manual LIKE 'aktif'");
    $aktif_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $aktif_column_exists = false;
}

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

if ($aktif_column_exists) {
    // Hanya ambil nilai yang sudah aktif (diterbitkan)
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
    // Fallback: ambil semua yang approved jika kolom aktif belum ada
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
$stmt->execute([$_SESSION['user_id'], $tahun_ajaran, $semester]);
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
$penilaian_list = $penilaian_list_all;

// Get sekolah data
$stmt = $pdo->query("SELECT * FROM sekolah LIMIT 1");
$sekolah = $stmt->fetch();

// Get active template
$stmt = $pdo->query("SELECT * FROM template_raport WHERE is_active = 1 LIMIT 1");
$template = $stmt->fetch();

// Build tabel nilai - tampilkan semua mapel
$tabel_nilai = '';
if (empty($penilaian_list)) {
    $tabel_nilai = '<tr><td colspan="3" style="text-align: center;">Belum ada data penilaian</td></tr>';
} else {
    $no = 1;
    foreach ($penilaian_list as $penilaian) {
        // Untuk raport tengah semester, gunakan nilai_uts
        $nilai_display = $penilaian['nilai_uts'] > 0 ? number_format($penilaian['nilai_uts'], 0) : '-';
        $tabel_nilai .= '<tr>';
        $tabel_nilai .= '<td>' . $no++ . '</td>';
        $tabel_nilai .= '<td>' . escape($penilaian['nama_mapel']) . '</td>';
        $tabel_nilai .= '<td>' . $nilai_display . '</td>';
        $tabel_nilai .= '</tr>';
    }
}

// Get logo HTML for raport
$logo_raport_html = '';
if ($template && !empty($template['logo_raport']) && file_exists(UPLOAD_PROFILE . '/' . $template['logo_raport'])) {
    $logo_raport_url = asset_url('uploads/profile/' . $template['logo_raport']);
    $logo_raport_html = '<img src="' . escape($logo_raport_url) . '" alt="Logo Raport" class="logo-kop-surat" />';
} elseif ($sekolah && !empty($sekolah['logo']) && file_exists(UPLOAD_PROFILE . '/' . $sekolah['logo'])) {
    $logo_url = asset_url('uploads/profile/' . $sekolah['logo']);
    $logo_raport_html = '<img src="' . escape($logo_url) . '" alt="Logo Sekolah" class="logo-kop-surat" />';
} else {
    $logo_raport_html = ''; // Empty if no logo
}

// Get logo HTML for sekolah (backward compatibility)
$logo_html = '';
if ($sekolah && !empty($sekolah['logo']) && file_exists(UPLOAD_PROFILE . '/' . $sekolah['logo'])) {
    $logo_url = asset_url('uploads/profile/' . $sekolah['logo']);
    $logo_html = '<img src="' . escape($logo_url) . '" alt="Logo Sekolah" class="logo-sekolah" />';
} else {
    $logo_html = ''; // Empty if no logo
}

// If template exists, use it; otherwise use default
if ($template) {
    // Replace variables in template
    $html_content = $template['html_content'];
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
    $html_content = str_replace('{{NIS}}', escape($siswa['username']), $html_content);
    $html_content = str_replace('{{KELAS}}', escape($siswa['nama_kelas']), $html_content);
    $html_content = str_replace('{{TAHUN_PELAJARAN}}', escape($tahun_ajaran), $html_content);
    $html_content = str_replace('{{SEMESTER}}', ucfirst($semester), $html_content);
    $html_content = str_replace('{{NAMA_WALI_KELAS}}', escape($nama_wali_kelas), $html_content);
    $html_content = str_replace('{{NIP_WALI_KELAS}}', escape($nip_wali_kelas), $html_content);
    $html_content = str_replace('{{TABEL_NILAI}}', $tabel_nilai, $html_content);
    
    $css_content = $template['css_content'];
} else {
    // Use default template (existing code)
    $html_content = null;
    $css_content = null;
}

// Calculate statistics
$total_nilai = 0;
$count_nilai = 0;
foreach ($penilaian_list as $p) {
    if ($p['nilai_akhir'] !== null) {
        $total_nilai += $p['nilai_akhir'];
        $count_nilai++;
    }
}
$rata_rata = $count_nilai > 0 ? $total_nilai / $count_nilai : 0;

// Predikat based on rata-rata
$predikat_akhir = '';
if ($rata_rata >= 85) {
    $predikat_akhir = 'A';
} elseif ($rata_rata >= 70) {
    $predikat_akhir = 'B';
} elseif ($rata_rata >= 55) {
    $predikat_akhir = 'C';
} elseif ($rata_rata >= 0) {
    $predikat_akhir = 'D';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport - <?php echo escape($siswa['nama']); ?></title>
    <style>
        <?php if ($css_content): ?>
            <?php echo $css_content; ?>
        <?php else: ?>
        @media print {
            .no-print { display: none; }
            .page-break { page-break-after: always; }
            @page { margin: 1.5cm; }
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 12pt;
            margin: 0;
            padding: 20px;
        }
        .raport-container {
            max-width: 21cm;
            margin: 0 auto;
        }
        .raport-header {
            margin-bottom: 30px;
        }
        .raport-header table {
            width: 100%;
            border-collapse: collapse;
        }
        .raport-header .logo-sekolah {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }
        .raport-header .pemerintah {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .raport-header .dinas {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .raport-header .nama-sekolah {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .raport-header .alamat {
            font-size: 11pt;
            margin-bottom: 2px;
        }
        .raport-header .separator {
            border-top: 3px solid #000;
            margin: 20px 0;
        }
        .raport-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }
        .raport-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .raport-info td {
            padding: 5px 10px;
            vertical-align: top;
        }
        .raport-info .label {
            width: 150px;
        }
        .raport-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .raport-table th,
        .raport-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .raport-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .raport-table td:first-child {
            text-align: center;
            width: 50px;
        }
        .raport-table td:last-child {
            text-align: center;
        }
        .logo-sekolah {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }
        <?php endif; ?>
        .no-print {
            margin-bottom: 20px;
            text-align: center;
        }
        .no-print button {
            padding: 10px 20px;
            margin: 0 5px;
            font-size: 14px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="background: #007bff; color: white; border: none; border-radius: 4px; padding: 10px 20px; margin: 0 5px;">Cetak</button>
        <a href="<?php echo base_url('siswa/raport/export_pdf.php?tahun_ajaran=' . urlencode($tahun_ajaran) . '&semester=' . $semester); ?>" 
           style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 10px 20px; margin: 0 5px; text-decoration: none; display: inline-block;">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; border-radius: 4px; padding: 10px 20px; margin: 0 5px;">Tutup</button>
    </div>

    <?php if ($html_content): ?>
        <!-- Use template -->
        <?php echo $html_content; ?>
    <?php else: ?>
        <!-- Default template (existing code) -->
    <div class="raport-container">
        <!-- Header -->
        <div class="raport-header">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 100px; vertical-align: top; padding-right: 20px;">
                        <?php echo $logo_html; ?>
                    </td>
                    <td style="text-align: center; vertical-align: middle;">
                        <div class="pemerintah">PEMERINTAH KABUPATEN TULUNGAGUNG</div>
                        <div class="dinas">DINAS PENDIDIKAN</div>
                        <div class="nama-sekolah"><?php echo escape($sekolah['nama_sekolah'] ?? 'NAMA SEKOLAH'); ?></div>
                        <?php if ($sekolah && $sekolah['alamat']): ?>
                            <div class="alamat"><?php echo escape($sekolah['alamat']); ?></div>
                        <?php endif; ?>
                        <?php if ($sekolah && $sekolah['no_telp']): ?>
                            <div class="alamat">Telp: <?php echo escape($sekolah['no_telp']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="width: 100px;"></td>
                </tr>
            </table>
            <div class="separator"></div>
        </div>

        <!-- Title -->
        <div class="raport-title">RAPORT TENGAH SEMESTER</div>

        <!-- Student Info -->
        <div class="raport-info">
            <table>
                <tr>
                    <td class="label">Nama Siswa</td>
                    <td>: <?php echo escape($siswa['nama']); ?></td>
                    <td class="label" style="width: 100px;">Kelas</td>
                    <td>: <?php echo escape($siswa['nama_kelas']); ?></td>
                </tr>
                <tr>
                    <td class="label">NIS</td>
                    <td>: <?php echo escape($siswa['username']); ?></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
        </div>

        <!-- Separator Line -->
        <div style="border-top: 1px solid #000; margin: 20px 0; width: 100%;"></div>

        <!-- Nilai Table -->
        <table class="raport-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Matapelajaran</th>
                    <th>Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($penilaian_list)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">Belum ada data penilaian</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($penilaian_list as $index => $penilaian): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo escape($penilaian['nama_mapel']); ?></td>
                            <td><?php echo $penilaian['nilai_akhir'] !== null ? number_format($penilaian['nilai_akhir'], 2) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>

