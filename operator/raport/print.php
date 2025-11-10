<?php
/**
 * Print Raport - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk mencetak raport siswa dengan format yang ditentukan
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

$id_siswa = intval($_GET['id_siswa'] ?? 0);
$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';

if (!$id_siswa) {
    redirect('operator/raport/list.php');
}

// Get siswa data
$stmt = $pdo->prepare("SELECT u.*, k.nama_kelas, k.tingkat
                      FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.id = ? 
                      AND uk.tahun_ajaran = ? 
                      AND uk.semester = ?");
$stmt->execute([$id_siswa, $tahun_ajaran, $semester]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('operator/raport/list.php');
}

// Get penilaian dari nilai manual
$stmt = $pdo->prepare("SELECT pm.*, m.nama_mapel, m.kode_mapel
                      FROM penilaian_manual pm
                      INNER JOIN mapel m ON pm.id_mapel = m.id
                      WHERE pm.id_siswa = ?
                      AND pm.tahun_ajaran = ?
                      AND pm.semester = ?
                      AND pm.status = 'approved'
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$id_siswa, $tahun_ajaran, $semester]);
$penilaian_list = $stmt->fetchAll();

// Get sekolah data
$stmt = $pdo->query("SELECT * FROM sekolah LIMIT 1");
$sekolah = $stmt->fetch();

// Get active template
$stmt = $pdo->query("SELECT * FROM template_raport WHERE is_active = 1 LIMIT 1");
$template = $stmt->fetch();

// Build tabel nilai
$tabel_nilai = '';
if (empty($penilaian_list)) {
    $tabel_nilai = '<tr><td colspan="3" style="text-align: center;">Belum ada data penilaian</td></tr>';
} else {
    foreach ($penilaian_list as $index => $penilaian) {
        $tabel_nilai .= '<tr>';
        $tabel_nilai .= '<td>' . ($index + 1) . '</td>';
        $tabel_nilai .= '<td>' . escape($penilaian['nama_mapel']) . '</td>';
        $tabel_nilai .= '<td>' . ($penilaian['nilai_akhir'] !== null ? number_format($penilaian['nilai_akhir'], 2) : '-') . '</td>';
        $tabel_nilai .= '</tr>';
    }
}

// Get logo HTML
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
    $html_content = str_replace('{{LOGO_SEKOLAH}}', $logo_html, $html_content);
    $html_content = str_replace('{{NAMA_SEKOLAH}}', escape($sekolah['nama_sekolah'] ?? 'NAMA SEKOLAH'), $html_content);
    $html_content = str_replace('{{ALAMAT_SEKOLAH}}', escape($sekolah['alamat'] ?? ''), $html_content);
    $html_content = str_replace('{{NO_TELP_SEKOLAH}}', escape($sekolah['no_telp'] ?? ''), $html_content);
    $html_content = str_replace('{{NAMA_SISWA}}', escape($siswa['nama']), $html_content);
    $html_content = str_replace('{{NIS}}', escape($siswa['username']), $html_content);
    $html_content = str_replace('{{KELAS}}', escape($siswa['nama_kelas']), $html_content);
    $html_content = str_replace('{{TABEL_NILAI}}', $tabel_nilai, $html_content);
    
    $css_content = $template['css_content'];
} else {
    // Use default template (existing code)
    $html_content = null;
    $css_content = null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Tengah Semester - <?php echo escape($siswa['nama']); ?></title>
    <style>
        <?php if ($css_content): ?>
            <?php echo $css_content; ?>
        <?php else: ?>
        @media print {
            .no-print { display: none; }
            .page-break { page-break-after: always; }
            @page {
                margin: 1.5cm;
            }
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
            text-align: center;
            margin-bottom: 30px;
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
        .raport-info {
            margin-bottom: 20px;
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
        .raport-table td:nth-child(2) {
            text-align: left;
        }
        .raport-table td:last-child {
            text-align: center;
        }
        .logo-sekolah {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }
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
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="background: #007bff; color: white; border: none; border-radius: 4px; padding: 10px 20px; margin: 0 5px;">Cetak</button>
        <a href="<?php echo base_url('operator/raport/export_pdf.php?id_siswa=' . $id_siswa . '&tahun_ajaran=' . urlencode($tahun_ajaran) . '&semester=' . $semester); ?>" 
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
        // Optional: Auto print when page loads
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>
