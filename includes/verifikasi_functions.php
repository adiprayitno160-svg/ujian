<?php
/**
 * Verifikasi Dokumen Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Fungsi untuk verifikasi dan validasi dokumen siswa kelas IX
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Validasi kesesuaian nama (EXACT MATCH - strict)
 * Tidak ada toleransi typo/alias
 * 
 * @param string $nama1 Nama pertama
 * @param string $nama2 Nama kedua
 * @param string|null $nama3 Nama ketiga (optional)
 * @return bool True jika semua nama sama persis
 */
function validate_nama_kesesuaian($nama1, $nama2, $nama3 = null) {
    // Normalisasi: trim, uppercase
    $n1 = strtoupper(trim($nama1 ?? ''));
    $n2 = strtoupper(trim($nama2 ?? ''));
    
    // Exact match (case-sensitive setelah uppercase)
    if ($n1 !== $n2 || empty($n1) || empty($n2)) {
        return false;
    }
    
    // Jika ada nama ketiga
    if ($nama3 !== null) {
        $n3 = strtoupper(trim($nama3 ?? ''));
        if ($n1 !== $n3 || $n2 !== $n3 || empty($n3)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Validasi semua dokumen siswa
 * Cek kesesuaian nama anak, ayah, ibu
 * 
 * @param int $id_siswa ID siswa
 * @return array Hasil validasi dengan detail
 */
function validate_all_dokumen($id_siswa) {
    global $pdo;
    
    try {
        // Get all documents
        $stmt = $pdo->prepare("SELECT * FROM siswa_dokumen_verifikasi WHERE id_siswa = ?");
        $stmt->execute([$id_siswa]);
        $dokumen_list = $stmt->fetchAll();
        
        if (count($dokumen_list) < 3) {
            return [
                'valid' => false,
                'message' => 'Belum lengkap: ' . count($dokumen_list) . ' dari 3 dokumen',
                'kesesuaian' => [
                    'nama_anak' => 'belum_dicek',
                    'nama_ayah' => 'belum_dicek',
                    'nama_ibu' => 'belum_dicek'
                ],
                'detail_ketidaksesuaian' => []
            ];
        }
        
        // Extract data from documents
        $ijazah = null;
        $kk = null;
        $akte = null;
        
        foreach ($dokumen_list as $doc) {
            if ($doc['jenis_dokumen'] === 'ijazah') {
                $ijazah = $doc;
            } elseif ($doc['jenis_dokumen'] === 'kk') {
                $kk = $doc;
            } elseif ($doc['jenis_dokumen'] === 'akte') {
                $akte = $doc;
            }
        }
        
        if (!$ijazah || !$kk || !$akte) {
            return [
                'valid' => false,
                'message' => 'Dokumen tidak lengkap',
                'kesesuaian' => [
                    'nama_anak' => 'belum_dicek',
                    'nama_ayah' => 'belum_dicek',
                    'nama_ibu' => 'belum_dicek'
                ],
                'detail_ketidaksesuaian' => []
            ];
        }
        
        // Validate nama anak (must match in all 3 documents)
        $nama_anak_ijazah = $ijazah['nama_anak'] ?? '';
        $nama_anak_kk = $kk['nama_anak'] ?? '';
        $nama_anak_akte = $akte['nama_anak'] ?? '';
        
        $kesesuaian_anak = validate_nama_kesesuaian($nama_anak_ijazah, $nama_anak_kk, $nama_anak_akte);
        
        // Validate nama ayah (must match in KK and Akte)
        $nama_ayah_kk = $kk['nama_ayah'] ?? '';
        $nama_ayah_akte = $akte['nama_ayah'] ?? '';
        
        $kesesuaian_ayah = validate_nama_kesesuaian($nama_ayah_kk, $nama_ayah_akte);
        
        // Validate nama ibu (must match in KK and Akte)
        $nama_ibu_kk = $kk['nama_ibu'] ?? '';
        $nama_ibu_akte = $akte['nama_ibu'] ?? '';
        
        $kesesuaian_ibu = validate_nama_kesesuaian($nama_ibu_kk, $nama_ibu_akte);
        
        // Determine overall status
        $all_valid = $kesesuaian_anak && $kesesuaian_ayah && $kesesuaian_ibu;
        
        // Build detail ketidaksesuaian
        $detail_ketidaksesuaian = [];
        
        if (!$kesesuaian_anak) {
            $detail_ketidaksesuaian[] = [
                'field' => 'nama_anak',
                'dokumen' => 'ijazah',
                'nilai' => $nama_anak_ijazah,
                'masalah' => 'Nama di Ijazah: "' . $nama_anak_ijazah . '" tidak sama dengan KK: "' . $nama_anak_kk . '" atau Akte: "' . $nama_anak_akte . '"'
            ];
            if ($nama_anak_ijazah !== $nama_anak_kk) {
                $detail_ketidaksesuaian[] = [
                    'field' => 'nama_anak',
                    'dokumen' => 'kk',
                    'nilai' => $nama_anak_kk,
                    'masalah' => 'Nama di KK: "' . $nama_anak_kk . '" tidak sama dengan Ijazah: "' . $nama_anak_ijazah . '"'
                ];
            }
            if ($nama_anak_ijazah !== $nama_anak_akte || $nama_anak_kk !== $nama_anak_akte) {
                $detail_ketidaksesuaian[] = [
                    'field' => 'nama_anak',
                    'dokumen' => 'akte',
                    'nilai' => $nama_anak_akte,
                    'masalah' => 'Nama di Akte: "' . $nama_anak_akte . '" tidak sama dengan Ijazah: "' . $nama_anak_ijazah . '" atau KK: "' . $nama_anak_kk . '"'
                ];
            }
        }
        
        if (!$kesesuaian_ayah) {
            $detail_ketidaksesuaian[] = [
                'field' => 'nama_ayah',
                'dokumen' => 'kk',
                'nilai' => $nama_ayah_kk,
                'masalah' => 'Nama ayah di KK: "' . $nama_ayah_kk . '" tidak sama dengan Akte: "' . $nama_ayah_akte . '"'
            ];
            $detail_ketidaksesuaian[] = [
                'field' => 'nama_ayah',
                'dokumen' => 'akte',
                'nilai' => $nama_ayah_akte,
                'masalah' => 'Nama ayah di Akte: "' . $nama_ayah_akte . '" tidak sama dengan KK: "' . $nama_ayah_kk . '"'
            ];
        }
        
        if (!$kesesuaian_ibu) {
            $detail_ketidaksesuaian[] = [
                'field' => 'nama_ibu',
                'dokumen' => 'kk',
                'nilai' => $nama_ibu_kk,
                'masalah' => 'Nama ibu di KK: "' . $nama_ibu_kk . '" tidak sama dengan Akte: "' . $nama_ibu_akte . '"'
            ];
            $detail_ketidaksesuaian[] = [
                'field' => 'nama_ibu',
                'dokumen' => 'akte',
                'nilai' => $nama_ibu_akte,
                'masalah' => 'Nama ibu di Akte: "' . $nama_ibu_akte . '" tidak sama dengan KK: "' . $nama_ibu_kk . '"'
            ];
        }
        
        return [
            'valid' => $all_valid,
            'message' => $all_valid ? 'Semua nama sesuai' : 'Ada nama yang tidak sesuai',
            'kesesuaian' => [
                'nama_anak' => $kesesuaian_anak ? 'sesuai' : 'tidak_sesuai',
                'nama_ayah' => $kesesuaian_ayah ? 'sesuai' : 'tidak_sesuai',
                'nama_ibu' => $kesesuaian_ibu ? 'sesuai' : 'tidak_sesuai'
            ],
            'detail_ketidaksesuaian' => $detail_ketidaksesuaian,
            'data' => [
                'nama_anak_ijazah' => $nama_anak_ijazah,
                'nama_anak_kk' => $nama_anak_kk,
                'nama_anak_akte' => $nama_anak_akte,
                'nama_ayah_kk' => $nama_ayah_kk,
                'nama_ayah_akte' => $nama_ayah_akte,
                'nama_ibu_kk' => $nama_ibu_kk,
                'nama_ibu_akte' => $nama_ibu_akte
            ]
        ];
    } catch (PDOException $e) {
        error_log("Validate all dokumen error: " . $e->getMessage());
        return [
            'valid' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'kesesuaian' => [
                'nama_anak' => 'belum_dicek',
                'nama_ayah' => 'belum_dicek',
                'nama_ibu' => 'belum_dicek'
            ],
            'detail_ketidaksesuaian' => []
        ];
    }
}

/**
 * Update verifikasi data siswa setelah upload dokumen
 * 
 * @param int $id_siswa ID siswa
 * @return bool Success
 */
function update_verifikasi_data_siswa($id_siswa) {
    global $pdo;
    
    try {
        // Validate all documents
        $validation = validate_all_dokumen($id_siswa);
        
        // Get all documents
        $stmt = $pdo->prepare("SELECT * FROM siswa_dokumen_verifikasi WHERE id_siswa = ?");
        $stmt->execute([$id_siswa]);
        $dokumen_list = $stmt->fetchAll();
        
        // Extract data
        $ijazah = null;
        $kk = null;
        $akte = null;
        
        foreach ($dokumen_list as $doc) {
            if ($doc['jenis_dokumen'] === 'ijazah') {
                $ijazah = $doc;
            } elseif ($doc['jenis_dokumen'] === 'kk') {
                $kk = $doc;
            } elseif ($doc['jenis_dokumen'] === 'akte') {
                $akte = $doc;
            }
        }
        
        // Determine status
        $status_overall = 'belum_lengkap';
        if (count($dokumen_list) >= 3) {
            if ($validation['valid']) {
                $status_overall = 'menunggu_verifikasi';
            } else {
                // Check if sudah pernah upload ulang
                $stmt = $pdo->prepare("SELECT jumlah_upload_ulang FROM verifikasi_data_siswa WHERE id_siswa = ?");
                $stmt->execute([$id_siswa]);
                $existing = $stmt->fetch();
                
                if ($existing && $existing['jumlah_upload_ulang'] >= VERIFIKASI_MAX_UPLOAD_ULANG) {
                    $status_overall = 'residu';
                } else {
                    $status_overall = 'tidak_valid';
                }
            }
        }
        
        // Check if record exists
        $stmt = $pdo->prepare("SELECT id FROM verifikasi_data_siswa WHERE id_siswa = ?");
        $stmt->execute([$id_siswa]);
        $existing = $stmt->fetch();
        
        $data = [
            'id_siswa' => $id_siswa,
            'status_overall' => $status_overall,
            'nama_anak_ijazah' => $ijazah['nama_anak'] ?? null,
            'nama_anak_kk' => $kk['nama_anak'] ?? null,
            'nama_ayah_kk' => $kk['nama_ayah'] ?? null,
            'nama_ibu_kk' => $kk['nama_ibu'] ?? null,
            'nama_anak_akte' => $akte['nama_anak'] ?? null,
            'nama_ayah_akte' => $akte['nama_ayah'] ?? null,
            'nama_ibu_akte' => $akte['nama_ibu'] ?? null,
            'kesesuaian_nama_anak' => $validation['kesesuaian']['nama_anak'],
            'kesesuaian_nama_ayah' => $validation['kesesuaian']['nama_ayah'],
            'kesesuaian_nama_ibu' => $validation['kesesuaian']['nama_ibu'],
            'detail_ketidaksesuaian' => json_encode($validation['detail_ketidaksesuaian'])
        ];
        
        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE verifikasi_data_siswa SET 
                status_overall = :status_overall,
                nama_anak_ijazah = :nama_anak_ijazah,
                nama_anak_kk = :nama_anak_kk,
                nama_ayah_kk = :nama_ayah_kk,
                nama_ibu_kk = :nama_ibu_kk,
                nama_anak_akte = :nama_anak_akte,
                nama_ayah_akte = :nama_ayah_akte,
                nama_ibu_akte = :nama_ibu_akte,
                kesesuaian_nama_anak = :kesesuaian_nama_anak,
                kesesuaian_nama_ayah = :kesesuaian_nama_ayah,
                kesesuaian_nama_ibu = :kesesuaian_nama_ibu,
                detail_ketidaksesuaian = :detail_ketidaksesuaian,
                updated_at = NOW()
                WHERE id_siswa = :id_siswa");
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO verifikasi_data_siswa (
                id_siswa, status_overall, nama_anak_ijazah, nama_anak_kk, nama_ayah_kk, nama_ibu_kk,
                nama_anak_akte, nama_ayah_akte, nama_ibu_akte,
                kesesuaian_nama_anak, kesesuaian_nama_ayah, kesesuaian_nama_ibu,
                detail_ketidaksesuaian
            ) VALUES (
                :id_siswa, :status_overall, :nama_anak_ijazah, :nama_anak_kk, :nama_ayah_kk, :nama_ibu_kk,
                :nama_anak_akte, :nama_ayah_akte, :nama_ibu_akte,
                :kesesuaian_nama_anak, :kesesuaian_nama_ayah, :kesesuaian_nama_ibu,
                :detail_ketidaksesuaian
            )");
        }
        
        $stmt->execute($data);
        
        return true;
    } catch (PDOException $e) {
        error_log("Update verifikasi data siswa error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log history perubahan
 * 
 * @param int $id_verifikasi ID verifikasi_data_siswa
 * @param int $id_siswa ID siswa
 * @param string $action Action yang dilakukan
 * @param string $status_sebelum Status sebelum
 * @param string $status_sesudah Status sesudah
 * @param int $dilakukan_oleh ID user yang melakukan
 * @param string $role_user Role user (siswa/admin)
 * @param array|null $data_sebelum Data sebelum (optional)
 * @param array|null $data_sesudah Data sesudah (optional)
 * @param string|null $keterangan Keterangan (optional)
 * @return bool Success
 */
function log_verifikasi_history($id_verifikasi, $id_siswa, $action, $status_sebelum, $status_sesudah, $dilakukan_oleh, $role_user, $data_sebelum = null, $data_sesudah = null, $keterangan = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO verifikasi_data_history (
            id_verifikasi, id_siswa, action, status_sebelum, status_sesudah,
            data_sebelum, data_sesudah, keterangan, dilakukan_oleh, role_user
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $id_verifikasi,
            $id_siswa,
            $action,
            $status_sebelum,
            $status_sesudah,
            $data_sebelum ? json_encode($data_sebelum) : null,
            $data_sesudah ? json_encode($data_sesudah) : null,
            $keterangan,
            $dilakukan_oleh,
            $role_user
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Log verifikasi history error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification
 * 
 * @param int $id_user ID user yang menerima notifikasi
 * @param int|null $id_verifikasi ID verifikasi (optional)
 * @param string $jenis Jenis notifikasi
 * @param string $judul Judul notifikasi
 * @param string $pesan Pesan notifikasi
 * @return bool Success
 */
function create_notifikasi_verifikasi($id_user, $id_verifikasi, $jenis, $judul, $pesan) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifikasi_verifikasi (
            id_user, id_verifikasi, jenis, judul, pesan
        ) VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $id_user,
            $id_verifikasi,
            $jenis,
            $judul,
            $pesan
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Create notifikasi verifikasi error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if student is in class IX
 * 
 * @param int $id_siswa ID siswa
 * @return bool True jika siswa kelas IX
 */
function is_siswa_kelas_IX($id_siswa) {
    global $pdo;
    
    try {
        $tahun_ajaran = get_tahun_ajaran_aktif();
        $stmt = $pdo->prepare("SELECT k.tingkat FROM user_kelas uk
                              INNER JOIN kelas k ON uk.id_kelas = k.id
                              WHERE uk.id_user = ? AND uk.tahun_ajaran = ?");
        $stmt->execute([$id_siswa, $tahun_ajaran]);
        $result = $stmt->fetch();
        
        return $result && $result['tingkat'] === 'IX';
    } catch (PDOException $e) {
        error_log("Check siswa kelas IX error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get verifikasi settings
 * 
 * @param string $key Setting key
 * @return string|null Setting value
 */
function get_verifikasi_setting($key) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM verifikasi_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        error_log("Get verifikasi setting error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if deadline has passed
 * 
 * @return bool True jika deadline sudah lewat
 */
function is_deadline_verifikasi_terlewat() {
    $deadline = get_verifikasi_setting('deadline_verifikasi');
    
    if (empty($deadline)) {
        return false; // No deadline set
    }
    
    $deadline_date = new DateTime($deadline);
    $now = new DateTime();
    
    return $now > $deadline_date;
}

/**
 * Check if menu verifikasi aktif untuk siswa
 * 
 * @param int $id_siswa ID siswa
 * @return bool True jika menu aktif
 */
function is_menu_verifikasi_aktif($id_siswa) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT menu_aktif FROM verifikasi_data_siswa WHERE id_siswa = ?");
        $stmt->execute([$id_siswa]);
        $result = $stmt->fetch();
        
        if ($result) {
            return (bool)$result['menu_aktif'];
        }
        
        // Default: check setting
        $default = get_verifikasi_setting('menu_aktif_default');
        return $default == '1';
    } catch (PDOException $e) {
        error_log("Check menu verifikasi aktif error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if file is readable
 * File bermasalah adalah file yang tidak bisa terbaca
 * 
 * @param string $file_path File path (relative to UPLOAD_VERIFIKASI)
 * @return bool True jika file bisa dibaca, false jika tidak
 */
function is_file_verifikasi_readable($file_path) {
    if (empty($file_path)) {
        return false;
    }
    
    $full_path = UPLOAD_VERIFIKASI . '/' . $file_path;
    
    // Check if file exists and is readable
    if (!file_exists($full_path)) {
        return false;
    }
    
    if (!is_readable($full_path)) {
        return false;
    }
    
    // Try to read first few bytes to verify file is not corrupted
    try {
        $handle = @fopen($full_path, 'rb');
        if ($handle === false) {
            return false;
        }
        $data = @fread($handle, 1024);
        @fclose($handle);
        return $data !== false;
    } catch (Exception $e) {
        error_log("Error checking file readability: " . $e->getMessage());
        return false;
    }
}

/**
 * Get list of unreadable files (file bermasalah) for a student
 * File bermasalah adalah file yang tidak bisa terbaca
 * 
 * @param int $id_siswa ID siswa
 * @return array List of documents with unreadable files
 */
function get_file_bermasalah($id_siswa) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, jenis_dokumen, file_path, status_verifikasi 
                               FROM siswa_dokumen_verifikasi 
                               WHERE id_siswa = ?");
        $stmt->execute([$id_siswa]);
        $dokumen_list = $stmt->fetchAll();
        
        $bermasalah = [];
        foreach ($dokumen_list as $doc) {
            if (!is_file_verifikasi_readable($doc['file_path'])) {
                $bermasalah[] = [
                    'id' => $doc['id'],
                    'jenis_dokumen' => $doc['jenis_dokumen'],
                    'file_path' => $doc['file_path'],
                    'status_verifikasi' => $doc['status_verifikasi']
                ];
            }
        }
        
        return $bermasalah;
    } catch (PDOException $e) {
        error_log("Get file bermasalah error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if student has any unreadable files (file bermasalah)
 * 
 * @param int $id_siswa ID siswa
 * @return bool True jika ada file bermasalah
 */
function has_file_bermasalah($id_siswa) {
    $bermasalah = get_file_bermasalah($id_siswa);
    return !empty($bermasalah);
}

