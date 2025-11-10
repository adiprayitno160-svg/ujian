<?php
/**
 * Export Nilai to PDF - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$ujian_id = intval($_GET['ujian_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

// Get nilai
$stmt = $pdo->prepare("SELECT n.*, u.nama as nama_siswa, u.username, s.nama_sesi
                      FROM nilai n
                      INNER JOIN users u ON n.id_siswa = u.id
                      LEFT JOIN sesi_ujian s ON n.id_sesi = s.id
                      WHERE n.id_ujian = ?
                      ORDER BY n.nilai DESC, u.nama ASC");
$stmt->execute([$ujian_id]);
$nilai_list = $stmt->fetchAll();

// Get sekolah info
$sekolah = get_sekolah_info();

// Simple PDF generation using HTML to PDF (requires TCPDF or similar library)
// For now, we'll use a simple HTML export that can be printed as PDF

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Nilai - <?php echo escape($ujian['judul']); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            .no-print {
                display: none;
            }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header h2 {
            margin: 10px 0;
            font-size: 16px;
        }
        .info {
            margin-bottom: 20px;
        }
        .info table {
            width: 100%;
            border-collapse: collapse;
        }
        .info table td {
            padding: 5px;
        }
        .info table td:first-child {
            width: 150px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
        }
        .no-print {
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #0066cc;
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background-color: #0052a3;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
        <a href="<?php echo base_url('guru/nilai/list.php?ujian_id=' . $ujian_id); ?>" class="btn" style="background-color: #6c757d;">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="header">
        <h1><?php echo escape($sekolah['nama_sekolah'] ?? 'Sekolah'); ?></h1>
        <h2>Laporan Nilai Ujian</h2>
        <p><?php echo escape($ujian['judul']); ?></p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td>Mata Pelajaran</td>
                <td><?php 
                    $stmt = $pdo->prepare("SELECT nama_mapel FROM mapel WHERE id = ?");
                    $stmt->execute([$ujian['id_mapel']]);
                    $mapel = $stmt->fetch();
                    echo escape($mapel['nama_mapel'] ?? '-');
                ?></td>
            </tr>
            <tr>
                <td>Durasi</td>
                <td><?php echo $ujian['durasi']; ?> menit</td>
            </tr>
            <tr>
                <td>Total Peserta</td>
                <td><?php echo count($nilai_list); ?></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td><?php echo date('d/m/Y H:i', strtotime($ujian['created_at'])); ?></td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50">No</th>
                <th>Nama Siswa</th>
                <th>Username</th>
                <th>Sesi</th>
                <th width="100" class="text-center">Nilai</th>
                <th width="100" class="text-center">Status</th>
                <th width="150">Waktu Submit</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_nilai = 0;
            $selesai_count = 0;
            foreach ($nilai_list as $nilai): 
                if ($nilai['status'] === 'selesai' && $nilai['nilai'] !== null) {
                    $total_nilai += $nilai['nilai'];
                    $selesai_count++;
                }
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td><?php echo escape($nilai['nama_siswa']); ?></td>
                <td><?php echo escape($nilai['username']); ?></td>
                <td><?php echo escape($nilai['nama_sesi'] ?? '-'); ?></td>
                <td class="text-center">
                    <?php if ($nilai['nilai'] !== null): ?>
                        <strong><?php echo number_format($nilai['nilai'], 2); ?></strong>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($nilai['status'] === 'selesai'): ?>
                        <span style="color: green;">Selesai</span>
                    <?php elseif ($nilai['status'] === 'mulai'): ?>
                        <span style="color: orange;">Sedang Mengerjakan</span>
                    <?php else: ?>
                        <span style="color: red;"><?php echo ucfirst($nilai['status']); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($nilai['waktu_submit']): ?>
                        <?php 
                        try {
                            $dt = new DateTime($nilai['waktu_submit']);
                            echo $dt->format('d/m/Y H:i');
                        } catch (Exception $e) {
                            echo date('d/m/Y H:i', strtotime($nilai['waktu_submit']));
                        }
                        ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <td colspan="4" class="text-right">Rata-rata (<?php echo $selesai_count; ?> peserta):</td>
                <td class="text-center">
                    <?php echo $selesai_count > 0 ? number_format($total_nilai / $selesai_count, 2) : '-'; ?>
                </td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?></p>
        <p>Oleh: <?php echo escape($_SESSION['nama']); ?></p>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>
