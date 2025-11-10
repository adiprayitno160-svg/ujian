<?php
/**
 * Export Absensi Ujian ke PDF - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_role('guru');
check_session_timeout();

global $pdo;

$sesi_id = intval($_GET['sesi_id'] ?? 0);

if (!$sesi_id) {
    redirect('guru/absensi/list.php');
}

// Get sesi info
$stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, u.durasi, m.nama_mapel, m.kode_mapel,
                      u2.nama as nama_guru
                      FROM sesi_ujian s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      LEFT JOIN users u2 ON u.id_guru = u2.id
                      WHERE s.id = ? AND u.id_guru = ?");
$stmt->execute([$sesi_id, $_SESSION['user_id']]);
$sesi_info = $stmt->fetch();

if (!$sesi_info) {
    redirect('guru/absensi/list.php');
}

$tahun_ajaran = get_tahun_ajaran_aktif();

// Get all peserta dari sesi (individual dan kelas)
// First, get individual assignments
$sql_individual = "SELECT DISTINCT 
        u.id as id_siswa, 
        u.nama as nama_siswa, 
        u.username as nis
        FROM sesi_peserta sp
        INNER JOIN users u ON sp.id_user = u.id
        WHERE sp.id_sesi = ?
        AND sp.tipe_assign = 'individual'
        AND u.role = 'siswa'
        AND u.status = 'active'";

$stmt = $pdo->prepare($sql_individual);
$stmt->execute([$sesi_id]);
$peserta_individual = $stmt->fetchAll();

// Get kelas assignments
$sql_kelas = "SELECT DISTINCT 
        u.id as id_siswa, 
        u.nama as nama_siswa, 
        u.username as nis
        FROM sesi_peserta sp
        INNER JOIN user_kelas uk ON sp.id_kelas = uk.id_kelas
        INNER JOIN users u ON uk.id_user = u.id
        WHERE sp.id_sesi = ?
        AND sp.tipe_assign = 'kelas'
        AND uk.tahun_ajaran = ?
        AND u.role = 'siswa'
        AND u.status = 'active'";

$stmt = $pdo->prepare($sql_kelas);
$stmt->execute([$sesi_id, $tahun_ajaran]);
$peserta_kelas = $stmt->fetchAll();

// Merge and get unique siswa
$all_siswa_ids = [];
$siswa_map = [];

foreach ($peserta_individual as $p) {
    if (!in_array($p['id_siswa'], $all_siswa_ids)) {
        $all_siswa_ids[] = $p['id_siswa'];
        $siswa_map[$p['id_siswa']] = $p;
    }
}

foreach ($peserta_kelas as $p) {
    if (!in_array($p['id_siswa'], $all_siswa_ids)) {
        $all_siswa_ids[] = $p['id_siswa'];
        $siswa_map[$p['id_siswa']] = $p;
    }
}

$absensi_list = [];

if (!empty($all_siswa_ids)) {
    // Get detailed info for each siswa
    $placeholders = implode(',', array_fill(0, count($all_siswa_ids), '?'));
    $sql = "SELECT 
            u.id as id_siswa,
            u.nama as nama_siswa,
            u.username as nis,
            k.nama_kelas,
            k.id as id_kelas,
            n.status as status_nilai,
            n.waktu_mulai,
            n.waktu_selesai,
            n.waktu_submit,
            n.nilai,
            a.status_absen,
            a.waktu_absen,
            a.metode_absen
            FROM users u
            LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
            LEFT JOIN kelas k ON uk.id_kelas = k.id
            LEFT JOIN nilai n ON n.id_sesi = ? AND n.id_siswa = u.id
            LEFT JOIN absensi_ujian a ON a.id_sesi = ? AND a.id_siswa = u.id
            WHERE u.id IN ($placeholders)
            ORDER BY k.nama_kelas ASC, u.nama ASC";
    
    $params = array_merge([$tahun_ajaran, $sesi_id, $sesi_id], $all_siswa_ids);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $absensi_list = $stmt->fetchAll();
}

// Calculate stats
$stats = [
    'total' => count($absensi_list),
    'hadir' => 0,
    'tidak_hadir' => 0,
    'selesai' => 0,
    'sedang_mengerjakan' => 0
];

foreach ($absensi_list as $absensi) {
    if ($absensi['status_nilai']) {
        if ($absensi['status_nilai'] === 'selesai') {
            $stats['selesai']++;
            $stats['hadir']++;
        } elseif ($absensi['status_nilai'] === 'sedang_mengerjakan') {
            $stats['sedang_mengerjakan']++;
            $stats['hadir']++;
        } else {
            $stats['tidak_hadir']++;
        }
    } elseif ($absensi['status_absen'] === 'hadir') {
        $stats['hadir']++;
    } else {
        $stats['tidak_hadir']++;
    }
}

// Get sekolah info
$stmt = $pdo->query("SELECT * FROM sekolah LIMIT 1");
$sekolah = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Ujian - <?php echo escape($sesi_info['judul_ujian']); ?></title>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 15px;
            }
            @page {
                margin: 1.5cm;
                size: A4;
            }
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 10pt;
            margin: 2px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table th, .info-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            font-size: 10pt;
        }
        .info-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            width: 25%;
        }
        .absensi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9pt;
        }
        .absensi-table th, .absensi-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        .absensi-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .absensi-table td {
            text-align: left;
        }
        .absensi-table .text-center {
            text-align: center;
        }
        .absensi-table .text-right {
            text-align: right;
        }
        .tidak-hadir {
            background-color: #ffe6e6 !important;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 250px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 10pt;
        }
        .stats-box {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        .stats-box p {
            margin: 3px 0;
            font-size: 10pt;
        }
        hr {
            border: 1px solid #000;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center; padding: 10px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px;">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 4px; margin-left: 10px;">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>

    <div class="header">
        <?php if ($sekolah): ?>
            <h1><?php echo escape($sekolah['nama_sekolah']); ?></h1>
            <?php if ($sekolah['alamat']): ?>
                <p><?php echo escape($sekolah['alamat']); ?></p>
            <?php endif; ?>
            <?php if ($sekolah['no_telp']): ?>
                <p>Telp: <?php echo escape($sekolah['no_telp']); ?></p>
            <?php endif; ?>
        <?php else: ?>
            <h1>Sekolah Menengah Pertama</h1>
        <?php endif; ?>
        <hr>
        <h2>DAFTAR HADIR UJIAN</h2>
        <h2><?php echo escape($sesi_info['judul_ujian']); ?></h2>
    </div>

    <table class="info-table">
        <tr>
            <th>Mata Pelajaran</th>
            <td><?php echo escape($sesi_info['nama_mapel']); ?> (<?php echo escape($sesi_info['kode_mapel']); ?>)</td>
        </tr>
        <tr>
            <th>Nama Sesi</th>
            <td><?php echo escape($sesi_info['nama_sesi']); ?></td>
        </tr>
        <tr>
            <th>Guru Pengampu</th>
            <td><?php echo escape($sesi_info['nama_guru'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Tanggal Ujian</th>
            <td><?php echo format_date($sesi_info['waktu_mulai'], 'd F Y'); ?></td>
        </tr>
        <tr>
            <th>Waktu Mulai</th>
            <td><?php echo format_date($sesi_info['waktu_mulai'], 'H:i'); ?> WIB</td>
        </tr>
        <tr>
            <th>Waktu Selesai</th>
            <td><?php echo format_date($sesi_info['waktu_selesai'], 'H:i'); ?> WIB</td>
        </tr>
        <tr>
            <th>Durasi</th>
            <td><?php echo $sesi_info['durasi']; ?> menit</td>
        </tr>
    </table>

    <div class="stats-box">
        <p><strong>RINGKASAN:</strong></p>
        <p>Total Peserta: <?php echo $stats['total']; ?> orang</p>
        <p>Hadir: <?php echo $stats['hadir']; ?> orang</p>
        <p>Tidak Hadir: <?php echo $stats['tidak_hadir']; ?> orang</p>
        <p>Selesai: <?php echo $stats['selesai']; ?> orang</p>
        <p>Sedang Mengerjakan: <?php echo $stats['sedang_mengerjakan']; ?> orang</p>
    </div>

    <h3 style="margin-top: 20px; margin-bottom: 10px; font-size: 12pt;">DAFTAR ABSENSI SISWA</h3>
    <table class="absensi-table">
        <thead>
            <tr>
                <th width="30" class="text-center">No</th>
                <th width="80">NIS</th>
                <th>Nama Siswa</th>
                <th width="100">Kelas</th>
                <th width="80" class="text-center">Status</th>
                <th width="120" class="text-center">Waktu Login</th>
                <th width="120" class="text-center">Waktu Selesai</th>
                <th width="60" class="text-center">Nilai</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($absensi_list as $absensi): 
                // Determine status
                $status_kehadiran = 'tidak_hadir';
                $status_label = 'Tidak Hadir';
                
                if ($absensi['status_nilai'] === 'selesai') {
                    $status_kehadiran = 'selesai';
                    $status_label = 'Selesai';
                } elseif ($absensi['status_nilai'] === 'sedang_mengerjakan') {
                    $status_kehadiran = 'sedang_mengerjakan';
                    $status_label = 'Sedang Mengerjakan';
                } elseif ($absensi['status_absen'] === 'hadir' || $absensi['status_nilai']) {
                    $status_kehadiran = 'hadir';
                    $status_label = 'Hadir';
                }
                
                // Get waktu login
                $waktu_login = '-';
                if ($absensi['waktu_mulai']) {
                    $waktu_login = format_date($absensi['waktu_mulai'], 'd/m/Y H:i');
                } elseif ($absensi['waktu_absen']) {
                    $waktu_login = format_date($absensi['waktu_absen'], 'd/m/Y H:i');
                }
                
                // Get waktu selesai
                $waktu_selesai = '-';
                if ($absensi['waktu_selesai']) {
                    $waktu_selesai = format_date($absensi['waktu_selesai'], 'd/m/Y H:i');
                } elseif ($absensi['waktu_submit']) {
                    $waktu_selesai = format_date($absensi['waktu_submit'], 'd/m/Y H:i');
                }
            ?>
                <tr class="<?php echo $status_kehadiran === 'tidak_hadir' ? 'tidak-hadir' : ''; ?>">
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td><?php echo escape($absensi['nis']); ?></td>
                    <td><strong><?php echo escape($absensi['nama_siswa']); ?></strong></td>
                    <td><?php echo escape($absensi['nama_kelas'] ?? '-'); ?></td>
                    <td class="text-center"><?php echo $status_label; ?></td>
                    <td class="text-center"><?php echo $waktu_login; ?></td>
                    <td class="text-center"><?php echo $waktu_selesai; ?></td>
                    <td class="text-center">
                        <?php if ($absensi['nilai'] !== null): ?>
                            <?php echo number_format($absensi['nilai'], 2); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php 
    $tidak_hadir_list = array_filter($absensi_list, function($item) {
        return !$item['status_nilai'] && $item['status_absen'] !== 'hadir';
    });
    if (!empty($tidak_hadir_list)): 
    ?>
        <div style="margin-top: 20px; padding: 10px; border: 1px solid #000; background-color: #ffe6e6;">
            <p style="margin: 0; font-weight: bold; font-size: 10pt;">Siswa yang Tidak Hadir:</p>
            <p style="margin: 5px 0 0 0; font-size: 9pt;">
                <?php 
                $tidak_hadir_names = array_map(function($item) {
                    return escape($item['nama_siswa']) . ' (NIS: ' . escape($item['nis']) . ')';
                }, $tidak_hadir_list);
                echo implode(', ', $tidak_hadir_names);
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Mengetahui,<br>
                Kepala Sekolah<br><br><br><br>
                ___________________<br>
                (<?php echo $sekolah ? escape($sekolah['nama_kepala_sekolah'] ?? '') : ''; ?>)
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Guru Mata Pelajaran<br><br><br><br>
                ___________________<br>
                (<?php echo escape($sesi_info['nama_guru'] ?? ''); ?>)
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <?php echo date('d F Y'); ?><br><br><br><br>
                Operator<br><br>
                ___________________
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 9pt; color: #666;">
        <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
        <p>Sistem Ujian dan Pekerjaan Rumah (UJAN)</p>
    </div>

    <script>
        // Optional: Auto trigger print dialog
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>

