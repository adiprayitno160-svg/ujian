-- Database Structure Export
-- Generated: 2025-11-15 20:33:44
-- Database: ujian

SET FOREIGN_KEY_CHECKS=0;

-- Table: absensi_ujian
DROP TABLE IF EXISTS `absensi_ujian`;
CREATE TABLE `absensi_ujian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) DEFAULT NULL COMMENT 'ID sesi ujian (NULL jika PR)',
  `id_pr` int(11) DEFAULT NULL COMMENT 'ID PR (NULL jika ujian)',
  `id_siswa` int(11) NOT NULL,
  `status_absen` enum('hadir','tidak_hadir','izin','sakit','retake') DEFAULT 'tidak_hadir',
  `waktu_absen` timestamp NULL DEFAULT NULL,
  `metode_absen` enum('auto','manual') DEFAULT 'manual',
  `created_by` int(11) DEFAULT NULL COMMENT 'User yang membuat absensi (NULL jika auto)',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `retake_sesi_id` int(11) DEFAULT NULL COMMENT 'ID sesi retake untuk siswa ini',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_absensi_sesi` (`id_sesi`,`id_siswa`),
  UNIQUE KEY `unique_absensi_pr` (`id_pr`,`id_siswa`),
  KEY `created_by` (`created_by`),
  KEY `idx_sesi` (`id_sesi`),
  KEY `idx_pr` (`id_pr`),
  KEY `idx_siswa` (`id_siswa`),
  KEY `idx_status` (`status_absen`),
  KEY `idx_waktu_absen` (`waktu_absen`),
  KEY `idx_retake_sesi` (`retake_sesi_id`),
  CONSTRAINT `absensi_ujian_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `absensi_ujian_ibfk_2` FOREIGN KEY (`id_pr`) REFERENCES `pr` (`id`) ON DELETE CASCADE,
  CONSTRAINT `absensi_ujian_ibfk_3` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `absensi_ujian_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ai_correction_log
DROP TABLE IF EXISTS `ai_correction_log`;
CREATE TABLE `ai_correction_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_nilai` int(11) DEFAULT NULL,
  `id_pr_submission` int(11) DEFAULT NULL,
  `tipe` enum('ujian','pr') NOT NULL,
  `prompt` text NOT NULL,
  `response` text NOT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_nilai` (`id_nilai`),
  KEY `id_pr_submission` (`id_pr_submission`),
  KEY `idx_tipe` (`tipe`),
  CONSTRAINT `ai_correction_log_ibfk_1` FOREIGN KEY (`id_nilai`) REFERENCES `nilai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_correction_log_ibfk_2` FOREIGN KEY (`id_pr_submission`) REFERENCES `pr_submission` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ai_settings
DROP TABLE IF EXISTS `ai_settings`;
CREATE TABLE `ai_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) DEFAULT 'gemini',
  `api_key` varchar(255) DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT 0,
  `model` varchar(100) DEFAULT 'gemini-pro',
  `temperature` decimal(3,2) DEFAULT 0.70,
  `max_tokens` int(11) DEFAULT 1000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: analisis_butir
DROP TABLE IF EXISTS `analisis_butir`;
CREATE TABLE `analisis_butir` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_soal` int(11) NOT NULL,
  `total_peserta` int(11) DEFAULT 0,
  `benar` int(11) DEFAULT 0,
  `salah` int(11) DEFAULT 0,
  `kosong` int(11) DEFAULT 0,
  `tingkat_kesukaran` decimal(5,3) DEFAULT 0.000,
  `daya_beda` decimal(5,3) DEFAULT 0.000,
  `efektivitas_distraktor` text DEFAULT NULL COMMENT 'JSON untuk analisis distraktor',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_analisis` (`id_ujian`,`id_soal`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_soal` (`id_soal`),
  CONSTRAINT `analisis_butir_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `analisis_butir_ibfk_2` FOREIGN KEY (`id_soal`) REFERENCES `soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: analisis_distraktor
DROP TABLE IF EXISTS `analisis_distraktor`;
CREATE TABLE `analisis_distraktor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_analisis` int(11) NOT NULL,
  `opsi` varchar(10) NOT NULL,
  `dipilih` int(11) DEFAULT 0,
  `persentase` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_analisis` (`id_analisis`),
  CONSTRAINT `analisis_distraktor_ibfk_1` FOREIGN KEY (`id_analisis`) REFERENCES `analisis_butir` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: announcements
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `konten` text NOT NULL,
  `target_role` enum('admin','guru','operator','siswa','all') DEFAULT 'all',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_target_role` (`target_role`),
  KEY `idx_status` (`status`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: answer_history
DROP TABLE IF EXISTS `answer_history`;
CREATE TABLE `answer_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_ujian` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_soal` int(11) NOT NULL,
  `old_answer` text DEFAULT NULL,
  `new_answer` text DEFAULT NULL,
  `old_answer_json` text DEFAULT NULL,
  `new_answer_json` text DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT 'user',
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sesi_ujian_siswa` (`id_sesi`,`id_ujian`,`id_siswa`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: answer_timing
DROP TABLE IF EXISTS `answer_timing`;
CREATE TABLE `answer_timing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_ujian` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_soal` int(11) NOT NULL,
  `time_taken_seconds` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sesi_ujian_siswa` (`id_sesi`,`id_ujian`,`id_siswa`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: anti_contek_logs
DROP TABLE IF EXISTS `anti_contek_logs`;
CREATE TABLE `anti_contek_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_sesi` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `warning_level` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_siswa` (`id_siswa`),
  KEY `idx_ujian_siswa` (`id_ujian`,`id_siswa`),
  KEY `idx_sesi` (`id_sesi`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `anti_contek_logs_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anti_contek_logs_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anti_contek_logs_ibfk_3` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: anti_contek_settings
DROP TABLE IF EXISTS `anti_contek_settings`;
CREATE TABLE `anti_contek_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `detect_tab_switch` tinyint(1) DEFAULT 1,
  `detect_copy_paste` tinyint(1) DEFAULT 1,
  `detect_screenshot` tinyint(1) DEFAULT 1,
  `detect_multiple_device` tinyint(1) DEFAULT 1,
  `detect_idle` tinyint(1) DEFAULT 1,
  `max_warnings` int(11) DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_settings` (`id_ujian`),
  CONSTRAINT `anti_contek_settings_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: app_lock_sessions
DROP TABLE IF EXISTS `app_lock_sessions`;
CREATE TABLE `app_lock_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `status` enum('locked','unlocked','force_unlocked') DEFAULT 'locked',
  PRIMARY KEY (`id`),
  KEY `id_user` (`id_user`),
  KEY `idx_sesi_user` (`id_sesi`,`id_user`),
  CONSTRAINT `app_lock_sessions_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_lock_sessions_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: arsip_soal
DROP TABLE IF EXISTS `arsip_soal`;
CREATE TABLE `arsip_soal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pool` varchar(255) NOT NULL COMMENT 'Contoh: Ujian A, Ujian B, dll',
  `id_mapel` int(11) NOT NULL,
  `tingkat_kelas` varchar(50) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `total_soal` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL COMMENT 'ID operator/guru yang membuat',
  `status` enum('draft','aktif','arsip') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_nama_pool` (`nama_pool`),
  CONSTRAINT `arsip_soal_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `arsip_soal_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: arsip_soal_item
DROP TABLE IF EXISTS `arsip_soal_item`;
CREATE TABLE `arsip_soal_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_arsip_soal` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `tipe_soal` enum('pilihan_ganda','benar_salah','essay','matching','isian_singkat') NOT NULL,
  `opsi_json` text DEFAULT NULL COMMENT 'JSON untuk opsi pilihan ganda',
  `kunci_jawaban` text DEFAULT NULL,
  `bobot` decimal(5,2) DEFAULT 1.00,
  `urutan` int(11) DEFAULT 0,
  `gambar` varchar(255) DEFAULT NULL,
  `media_type` enum('gambar','video') DEFAULT NULL,
  `tingkat_kesulitan` enum('mudah','sedang','sulit') DEFAULT 'sedang',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pool` (`id_arsip_soal`),
  KEY `idx_urutan` (`urutan`),
  KEY `idx_tipe` (`tipe_soal`),
  CONSTRAINT `arsip_soal_item_ibfk_1` FOREIGN KEY (`id_arsip_soal`) REFERENCES `arsip_soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: arsip_soal_matching
DROP TABLE IF EXISTS `arsip_soal_matching`;
CREATE TABLE `arsip_soal_matching` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_arsip_soal_item` int(11) NOT NULL,
  `item_kiri` varchar(255) NOT NULL,
  `item_kanan` varchar(255) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pool_item` (`id_arsip_soal_item`),
  KEY `idx_urutan` (`urutan`),
  CONSTRAINT `arsip_soal_matching_ibfk_1` FOREIGN KEY (`id_arsip_soal_item`) REFERENCES `arsip_soal_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: audit_logs
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: bank_soal
DROP TABLE IF EXISTS `bank_soal`;
CREATE TABLE `bank_soal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_soal` int(11) NOT NULL COMMENT 'Reference ke tabel soal',
  `id_mapel` int(11) NOT NULL,
  `tingkat_kelas` varchar(20) DEFAULT NULL COMMENT 'Tingkat kelas (contoh: VII, VIII, IX)',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_soal_bank` (`id_soal`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_tingkat` (`tingkat_kelas`),
  KEY `idx_status` (`status`),
  KEY `idx_approved_by` (`approved_by`),
  CONSTRAINT `bank_soal_ibfk_1` FOREIGN KEY (`id_soal`) REFERENCES `soal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_soal_ibfk_2` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_soal_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: berita_acara
DROP TABLE IF EXISTS `berita_acara`;
CREATE TABLE `berita_acara` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_sesi` int(11) DEFAULT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `id_jadwal_assessment` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `pengawas` text DEFAULT NULL COMMENT 'Nama pengawas (JSON array)',
  `total_peserta` int(11) DEFAULT 0,
  `total_hadir` int(11) DEFAULT 0,
  `total_tidak_hadir` int(11) DEFAULT 0,
  `total_izin` int(11) DEFAULT 0,
  `total_sakit` int(11) DEFAULT 0,
  `catatan` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_kelas` (`id_kelas`),
  KEY `id_jadwal_assessment` (`id_jadwal_assessment`),
  KEY `created_by` (`created_by`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_sesi` (`id_sesi`),
  CONSTRAINT `berita_acara_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `berita_acara_ibfk_2` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE SET NULL,
  CONSTRAINT `berita_acara_ibfk_3` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `berita_acara_ibfk_4` FOREIGN KEY (`id_jadwal_assessment`) REFERENCES `jadwal_assessment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `berita_acara_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: device_fingerprint
DROP TABLE IF EXISTS `device_fingerprint`;
CREATE TABLE `device_fingerprint` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `fingerprint` varchar(64) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `last_used` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_trusted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fingerprint` (`fingerprint`),
  KEY `idx_user` (`id_user`),
  KEY `idx_fingerprint` (`fingerprint`),
  CONSTRAINT `device_fingerprint_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: guru_mapel
DROP TABLE IF EXISTS `guru_mapel`;
CREATE TABLE `guru_mapel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_guru` int(11) NOT NULL,
  `id_mapel` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guru_mapel` (`id_guru`,`id_mapel`),
  KEY `id_mapel` (`id_mapel`),
  CONSTRAINT `guru_mapel_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guru_mapel_ibfk_2` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: guru_mapel_kelas
DROP TABLE IF EXISTS `guru_mapel_kelas`;
CREATE TABLE `guru_mapel_kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_guru` int(11) NOT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guru_mapel_kelas` (`id_guru`,`id_mapel`,`id_kelas`),
  KEY `idx_guru` (`id_guru`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_kelas` (`id_kelas`),
  KEY `idx_guru_mapel` (`id_guru`,`id_mapel`),
  CONSTRAINT `guru_mapel_kelas_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guru_mapel_kelas_ibfk_2` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guru_mapel_kelas_ibfk_3` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ip_rate_limits
DROP TABLE IF EXISTS `ip_rate_limits`;
CREATE TABLE `ip_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `request_count` int(11) DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_request` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action_ip` (`action`,`ip_address`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: jadwal_assessment
DROP TABLE IF EXISTS `jadwal_assessment`;
CREATE TABLE `jadwal_assessment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `tingkat` varchar(20) DEFAULT NULL COMMENT 'Tingkat kelas (contoh: VII, VIII, IX)',
  `tanggal` date NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `status` enum('aktif','nonaktif','selesai','dibatalkan') DEFAULT 'aktif',
  `is_susulan` tinyint(1) DEFAULT 0 COMMENT '1 = jadwal susulan',
  `id_jadwal_utama` int(11) DEFAULT NULL COMMENT 'ID jadwal utama jika ini susulan',
  `id_sesi` int(11) DEFAULT NULL COMMENT 'Link ke sesi_ujian yang dibuat',
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_sesi` (`id_sesi`),
  KEY `created_by` (`created_by`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_kelas` (`id_kelas`),
  KEY `idx_tingkat` (`tingkat`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_status` (`status`),
  KEY `idx_is_susulan` (`is_susulan`),
  KEY `idx_jadwal_utama` (`id_jadwal_utama`),
  CONSTRAINT `jadwal_assessment_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_assessment_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jadwal_assessment_ibfk_3` FOREIGN KEY (`id_jadwal_utama`) REFERENCES `jadwal_assessment` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jadwal_assessment_ibfk_4` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE SET NULL,
  CONSTRAINT `jadwal_assessment_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: jawaban_similarity
DROP TABLE IF EXISTS `jawaban_similarity`;
CREATE TABLE `jawaban_similarity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_soal` int(11) NOT NULL,
  `jawaban_hash` varchar(64) NOT NULL,
  `id_siswa_list` text NOT NULL COMMENT 'JSON array of student IDs',
  `similarity_group` int(11) DEFAULT NULL,
  `similarity_score` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_soal` (`id_soal`),
  KEY `idx_ujian_soal` (`id_ujian`,`id_soal`),
  KEY `idx_hash` (`jawaban_hash`),
  CONSTRAINT `jawaban_similarity_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jawaban_similarity_ibfk_2` FOREIGN KEY (`id_soal`) REFERENCES `soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: jawaban_siswa
DROP TABLE IF EXISTS `jawaban_siswa`;
CREATE TABLE `jawaban_siswa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_ujian` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_soal` int(11) NOT NULL,
  `jawaban` text DEFAULT NULL,
  `jawaban_json` text DEFAULT NULL COMMENT 'JSON untuk jawaban kompleks',
  `file_jawaban` varchar(255) DEFAULT NULL,
  `waktu_submit` timestamp NULL DEFAULT NULL,
  `waktu_pengerjaan` int(11) DEFAULT NULL COMMENT 'Waktu pengerjaan dalam detik',
  `waktu_mulai_jawab` timestamp NULL DEFAULT NULL COMMENT 'Waktu mulai mengerjakan soal',
  `waktu_selesai_jawab` timestamp NULL DEFAULT NULL COMMENT 'Waktu selesai mengerjakan soal',
  `is_auto_submit` tinyint(1) DEFAULT 0,
  `is_ragu` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `last_saved_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_jawaban` (`id_sesi`,`id_siswa`,`id_soal`),
  KEY `id_ujian` (`id_ujian`),
  KEY `id_siswa` (`id_siswa`),
  KEY `idx_sesi_siswa` (`id_sesi`,`id_siswa`),
  KEY `idx_soal` (`id_soal`),
  KEY `idx_is_locked` (`is_locked`),
  CONSTRAINT `jawaban_siswa_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jawaban_siswa_ibfk_2` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jawaban_siswa_ibfk_3` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jawaban_siswa_ibfk_4` FOREIGN KEY (`id_soal`) REFERENCES `soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: kelas
DROP TABLE IF EXISTS `kelas`;
CREATE TABLE `kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kelas` varchar(50) NOT NULL,
  `tingkat` varchar(20) DEFAULT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_tahun_ajaran` (`tahun_ajaran`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: logs
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: mapel
DROP TABLE IF EXISTS `mapel`;
CREATE TABLE `mapel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_mapel` varchar(100) NOT NULL,
  `kode_mapel` varchar(20) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_mapel` (`kode_mapel`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: mapel_tingkat
DROP TABLE IF EXISTS `mapel_tingkat`;
CREATE TABLE `mapel_tingkat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_mapel` int(11) NOT NULL,
  `tingkat` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mapel_tingkat` (`id_mapel`,`tingkat`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_tingkat` (`tingkat`),
  CONSTRAINT `mapel_tingkat_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: migrasi_history
DROP TABLE IF EXISTS `migrasi_history`;
CREATE TABLE `migrasi_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_kelas_lama` int(11) NOT NULL,
  `id_kelas_baru` int(11) NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `semester` enum('ganjil','genap') DEFAULT 'ganjil',
  `keterangan` varchar(100) DEFAULT NULL COMMENT 'Naik Kelas atau Tinggal Kelas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`id_user`),
  CONSTRAINT `migrasi_history_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: migrasi_kelas
DROP TABLE IF EXISTS `migrasi_kelas`;
CREATE TABLE `migrasi_kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_kelas_lama` int(11) NOT NULL,
  `id_kelas_baru` int(11) NOT NULL,
  `tahun_ajaran_lama` varchar(20) NOT NULL,
  `tahun_ajaran_baru` varchar(20) NOT NULL,
  `semester` enum('ganjil','genap') DEFAULT 'ganjil',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_kelas_lama` (`id_kelas_lama`),
  KEY `id_kelas_baru` (`id_kelas_baru`),
  KEY `created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`id_user`),
  CONSTRAINT `migrasi_kelas_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `migrasi_kelas_ibfk_2` FOREIGN KEY (`id_kelas_lama`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `migrasi_kelas_ibfk_3` FOREIGN KEY (`id_kelas_baru`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `migrasi_kelas_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: nilai
DROP TABLE IF EXISTS `nilai`;
CREATE TABLE `nilai` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_ujian` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `nilai` decimal(5,2) DEFAULT 0.00,
  `nilai_per_soal_json` text DEFAULT NULL COMMENT 'JSON untuk detail nilai per soal',
  `status` enum('belum_mulai','sedang_mengerjakan','selesai','terlambat','dibatalkan') DEFAULT 'belum_mulai',
  `waktu_mulai` timestamp NULL DEFAULT NULL,
  `waktu_selesai` timestamp NULL DEFAULT NULL,
  `waktu_submit` timestamp NULL DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `warning_count` int(11) DEFAULT 0,
  `is_suspicious` tinyint(1) DEFAULT 0,
  `is_fraud` tinyint(1) DEFAULT 0,
  `requires_relogin` tinyint(1) DEFAULT 0,
  `answers_locked` tinyint(1) DEFAULT 0,
  `requires_token` tinyint(1) DEFAULT 0,
  `fraud_reason` text DEFAULT NULL,
  `fraud_detected_at` datetime DEFAULT NULL,
  `disruption_reason` text DEFAULT NULL,
  `ai_corrected` tinyint(1) DEFAULT 0,
  `ai_feedback` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_nilai` (`id_sesi`,`id_siswa`),
  KEY `id_siswa` (`id_siswa`),
  KEY `idx_sesi_siswa` (`id_sesi`,`id_siswa`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_is_fraud` (`is_fraud`),
  KEY `idx_requires_relogin` (`requires_relogin`),
  KEY `idx_answers_locked` (`answers_locked`),
  CONSTRAINT `nilai_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nilai_ibfk_2` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nilai_ibfk_3` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: nilai_semua_mapel
DROP TABLE IF EXISTS `nilai_semua_mapel`;
CREATE TABLE `nilai_semua_mapel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_siswa` int(11) NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `semester` enum('ganjil','genap') NOT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_ujian` int(11) DEFAULT NULL COMMENT 'ID ujian/SUMATIP',
  `tipe_asesmen` varchar(50) DEFAULT NULL COMMENT 'Jenis asesmen',
  `nilai` decimal(5,2) DEFAULT 0.00,
  `nilai_akhir` decimal(5,2) DEFAULT NULL COMMENT 'Nilai akhir setelah agregasi',
  `is_sumatip` tinyint(1) DEFAULT 0 COMMENT '1 = nilai dari SUMATIP',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_siswa` (`id_siswa`),
  KEY `idx_tahun_semester` (`tahun_ajaran`,`semester`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_is_sumatip` (`is_sumatip`),
  CONSTRAINT `nilai_semua_mapel_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nilai_semua_mapel_ibfk_2` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nilai_semua_mapel_ibfk_3` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notification_preferences
DROP TABLE IF EXISTS `notification_preferences`;
CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('ujian','pr','tugas','nilai','system','reminder') NOT NULL,
  `email_enabled` tinyint(1) DEFAULT 1,
  `in_app_enabled` tinyint(1) DEFAULT 1,
  `push_enabled` tinyint(1) DEFAULT 0,
  `reminder_before_hours` int(11) DEFAULT 24,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_type` (`user_id`,`type`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('ujian','pr','tugas','nilai','system','reminder') DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notifikasi_verifikasi
DROP TABLE IF EXISTS `notifikasi_verifikasi`;
CREATE TABLE `notifikasi_verifikasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL COMMENT 'User yang menerima notifikasi',
  `id_verifikasi` int(11) DEFAULT NULL COMMENT 'ID dari verifikasi_data_siswa',
  `jenis` enum('upload_berhasil','verifikasi_valid','verifikasi_tidak_valid','deadline_mendekati','deadline_terlewat','upload_ulang_diperlukan') NOT NULL,
  `judul` varchar(255) NOT NULL,
  `pesan` text NOT NULL,
  `dibaca` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_verifikasi` (`id_verifikasi`),
  KEY `idx_user` (`id_user`),
  KEY `idx_dibaca` (`dibaca`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifikasi_verifikasi_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifikasi_verifikasi_ibfk_2` FOREIGN KEY (`id_verifikasi`) REFERENCES `verifikasi_data_siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: penilaian_manual
DROP TABLE IF EXISTS `penilaian_manual`;
CREATE TABLE `penilaian_manual` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_guru` int(11) NOT NULL COMMENT 'Guru mata pelajaran yang memberikan nilai',
  `id_siswa` int(11) NOT NULL COMMENT 'Siswa yang dinilai',
  `id_mapel` int(11) NOT NULL COMMENT 'Mata pelajaran',
  `id_kelas` int(11) NOT NULL COMMENT 'Kelas siswa',
  `tahun_ajaran` varchar(20) NOT NULL COMMENT 'Tahun ajaran',
  `semester` enum('ganjil','genap') NOT NULL COMMENT 'Semester',
  `nilai_tugas` decimal(5,2) DEFAULT NULL COMMENT 'Nilai tugas',
  `nilai_uts` decimal(5,2) DEFAULT NULL COMMENT 'Nilai UTS',
  `nilai_uas` decimal(5,2) DEFAULT NULL COMMENT 'Nilai UAS',
  `nilai_akhir` decimal(5,2) DEFAULT NULL COMMENT 'Nilai akhir (rata-rata atau sesuai kebijakan)',
  `predikat` varchar(20) DEFAULT NULL COMMENT 'Predikat (A, B, C, D)',
  `keterangan` text DEFAULT NULL COMMENT 'Keterangan tambahan dari guru',
  `status` enum('draft','submitted','approved') DEFAULT 'draft' COMMENT 'Status: draft = belum dikumpulkan, submitted = sudah dikumpulkan ke operator, approved = sudah disetujui operator',
  `aktif` tinyint(1) DEFAULT 0 COMMENT 'Status aktif: 0 = tidak aktif, 1 = aktif (diaktifkan oleh operator)',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu dikumpulkan ke operator',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu disetujui operator',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Operator yang menyetujui',
  `activated_by` int(11) DEFAULT NULL COMMENT 'Operator yang mengaktifkan',
  `activated_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu diaktifkan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_penilaian` (`id_guru`,`id_siswa`,`id_mapel`,`id_kelas`,`tahun_ajaran`,`semester`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_guru` (`id_guru`),
  KEY `idx_siswa` (`id_siswa`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_kelas` (`id_kelas`),
  KEY `idx_tahun_ajaran` (`tahun_ajaran`),
  KEY `idx_semester` (`semester`),
  KEY `idx_status` (`status`),
  KEY `idx_aktif` (`aktif`),
  KEY `activated_by` (`activated_by`),
  CONSTRAINT `penilaian_manual_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_manual_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_manual_ibfk_3` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_manual_ibfk_4` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_manual_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `penilaian_manual_ibfk_6` FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: plagiarisme_check
DROP TABLE IF EXISTS `plagiarisme_check`;
CREATE TABLE `plagiarisme_check` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_siswa1` int(11) NOT NULL,
  `id_siswa2` int(11) NOT NULL,
  `id_soal` int(11) NOT NULL,
  `similarity_score` decimal(5,2) DEFAULT 0.00,
  `status` enum('pending','reviewed','flagged','cleared') DEFAULT 'pending',
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checked_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_siswa1` (`id_siswa1`),
  KEY `id_siswa2` (`id_siswa2`),
  KEY `id_soal` (`id_soal`),
  KEY `checked_by` (`checked_by`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_similarity` (`similarity_score`),
  CONSTRAINT `plagiarisme_check_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plagiarisme_check_ibfk_2` FOREIGN KEY (`id_siswa1`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plagiarisme_check_ibfk_3` FOREIGN KEY (`id_siswa2`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plagiarisme_check_ibfk_4` FOREIGN KEY (`id_soal`) REFERENCES `soal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plagiarisme_check_ibfk_5` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: plagiarisme_settings
DROP TABLE IF EXISTS `plagiarisme_settings`;
CREATE TABLE `plagiarisme_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `similarity_threshold` decimal(5,2) DEFAULT 80.00 COMMENT 'threshold dalam persen',
  `auto_check` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_settings` (`id_ujian`),
  CONSTRAINT `plagiarisme_settings_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pool_soal
DROP TABLE IF EXISTS `pool_soal`;
CREATE TABLE `pool_soal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pool` varchar(255) NOT NULL COMMENT 'Contoh: Ujian A, Ujian B, dll',
  `id_mapel` int(11) NOT NULL,
  `tingkat_kelas` varchar(50) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `total_soal` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL COMMENT 'ID operator/guru yang membuat',
  `status` enum('draft','aktif','arsip') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_nama_pool` (`nama_pool`),
  CONSTRAINT `pool_soal_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pool_soal_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pool_soal_item
DROP TABLE IF EXISTS `pool_soal_item`;
CREATE TABLE `pool_soal_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pool_soal` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `tipe_soal` enum('pilihan_ganda','benar_salah','essay','matching','isian_singkat') NOT NULL,
  `opsi_json` text DEFAULT NULL COMMENT 'JSON untuk opsi pilihan ganda',
  `kunci_jawaban` text DEFAULT NULL,
  `bobot` decimal(5,2) DEFAULT 1.00,
  `urutan` int(11) DEFAULT 0,
  `gambar` varchar(255) DEFAULT NULL,
  `media_type` enum('gambar','video') DEFAULT NULL,
  `tingkat_kesulitan` enum('mudah','sedang','sulit') DEFAULT 'sedang',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pool` (`id_pool_soal`),
  KEY `idx_urutan` (`urutan`),
  KEY `idx_tipe` (`tipe_soal`),
  CONSTRAINT `pool_soal_item_ibfk_1` FOREIGN KEY (`id_pool_soal`) REFERENCES `pool_soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pool_soal_matching
DROP TABLE IF EXISTS `pool_soal_matching`;
CREATE TABLE `pool_soal_matching` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pool_soal_item` int(11) NOT NULL,
  `item_kiri` varchar(255) NOT NULL,
  `item_kanan` varchar(255) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pool_item` (`id_pool_soal_item`),
  KEY `idx_urutan` (`urutan`),
  CONSTRAINT `pool_soal_matching_ibfk_1` FOREIGN KEY (`id_pool_soal_item`) REFERENCES `pool_soal_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pr
DROP TABLE IF EXISTS `pr`;
CREATE TABLE `pr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `deadline` datetime NOT NULL,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `max_file_size` int(11) DEFAULT 10485760 COMMENT 'dalam bytes, default 10MB',
  `allowed_extensions` varchar(255) DEFAULT 'pdf,doc,docx,zip',
  `ai_correction_enabled` tinyint(1) DEFAULT 0,
  `ai_api_key` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_guru` (`id_guru`),
  KEY `idx_mapel` (`id_mapel`),
  CONSTRAINT `pr_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_ibfk_2` FOREIGN KEY (`id_guru`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pr_kelas
DROP TABLE IF EXISTS `pr_kelas`;
CREATE TABLE `pr_kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pr` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pr_kelas` (`id_pr`,`id_kelas`),
  KEY `id_kelas` (`id_kelas`),
  CONSTRAINT `pr_kelas_ibfk_1` FOREIGN KEY (`id_pr`) REFERENCES `pr` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_kelas_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: pr_submission
DROP TABLE IF EXISTS `pr_submission`;
CREATE TABLE `pr_submission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pr` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `file_jawaban` varchar(255) DEFAULT NULL,
  `komentar` text DEFAULT NULL,
  `jawaban_text` text DEFAULT NULL,
  `tipe_submission` enum('file','text','both') DEFAULT 'file',
  `nilai` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('belum_dikumpulkan','sudah_dikumpulkan','dinilai','terlambat') DEFAULT 'belum_dikumpulkan',
  `waktu_submit` timestamp NULL DEFAULT NULL,
  `waktu_dinilai` timestamp NULL DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `ai_corrected` tinyint(1) DEFAULT 0,
  `ai_feedback` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`id_pr`,`id_siswa`),
  KEY `idx_pr` (`id_pr`),
  KEY `idx_siswa` (`id_siswa`),
  CONSTRAINT `pr_submission_ibfk_1` FOREIGN KEY (`id_pr`) REFERENCES `pr` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_submission_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ragu_ragu
DROP TABLE IF EXISTS `ragu_ragu`;
CREATE TABLE `ragu_ragu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_nilai` int(11) NOT NULL,
  `id_soal` int(11) NOT NULL,
  `status` enum('ragu','yakin') DEFAULT 'ragu',
  `waktu_mark` timestamp NOT NULL DEFAULT current_timestamp(),
  `waktu_unmark` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nilai` (`id_nilai`),
  KEY `idx_soal` (`id_soal`),
  KEY `idx_status` (`status`),
  CONSTRAINT `ragu_ragu_ibfk_1` FOREIGN KEY (`id_nilai`) REFERENCES `nilai` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ragu_ragu_ibfk_2` FOREIGN KEY (`id_soal`) REFERENCES `soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rate_limits
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_count` int(11) DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_request` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action_user` (`action`,`user_id`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_logs
DROP TABLE IF EXISTS `security_logs`;
CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `id_sesi` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `is_suspicious` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`id_user`),
  KEY `idx_sesi` (`id_sesi`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `security_logs_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_logs_ibfk_2` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=279 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sekolah
DROP TABLE IF EXISTS `sekolah`;
CREATE TABLE `sekolah` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_sekolah` varchar(200) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sesi_peserta
DROP TABLE IF EXISTS `sesi_peserta`;
CREATE TABLE `sesi_peserta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_kelas` int(11) DEFAULT NULL,
  `tipe_assign` enum('individual','kelas') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sesi` (`id_sesi`),
  KEY `idx_user` (`id_user`),
  KEY `idx_kelas` (`id_kelas`),
  CONSTRAINT `sesi_peserta_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sesi_peserta_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sesi_peserta_ibfk_3` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sesi_ujian
DROP TABLE IF EXISTS `sesi_ujian`;
CREATE TABLE `sesi_ujian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `nama_sesi` varchar(100) NOT NULL,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime NOT NULL,
  `durasi` int(11) NOT NULL COMMENT 'dalam menit',
  `max_peserta` int(11) DEFAULT NULL,
  `token_required` tinyint(1) DEFAULT 0,
  `status` enum('draft','aktif','selesai','dibatalkan') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_retake` tinyint(1) DEFAULT 0 COMMENT 'Flag untuk sesi retake',
  `original_sesi_id` int(11) DEFAULT NULL COMMENT 'ID sesi original untuk retake',
  PRIMARY KEY (`id`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_waktu_mulai` (`waktu_mulai`),
  KEY `idx_status` (`status`),
  KEY `idx_is_retake` (`is_retake`),
  KEY `idx_original_sesi` (`original_sesi_id`),
  CONSTRAINT `sesi_ujian_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: session_activity
DROP TABLE IF EXISTS `session_activity`;
CREATE TABLE `session_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_user` (`id_user`),
  KEY `idx_sesi_user` (`id_sesi`,`id_user`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `session_activity_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `session_activity_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: siswa_dokumen_verifikasi
DROP TABLE IF EXISTS `siswa_dokumen_verifikasi`;
CREATE TABLE `siswa_dokumen_verifikasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_siswa` int(11) NOT NULL,
  `jenis_dokumen` enum('ijazah','kk','akte') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('pdf','jpg','jpeg','png') NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `ocr_text` text DEFAULT NULL,
  `ocr_confidence` int(11) DEFAULT 0 COMMENT '0-100, confidence score dari OCR',
  `nama_anak` varchar(255) DEFAULT NULL,
  `nama_ayah` varchar(255) DEFAULT NULL,
  `nama_ibu` varchar(255) DEFAULT NULL,
  `nik` varchar(50) DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `data_ekstrak_lainnya` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_ekstrak_lainnya`)),
  `status_ocr` enum('pending','success','failed') DEFAULT 'pending',
  `status_verifikasi` enum('belum','menunggu','valid','tidak_valid','residu') DEFAULT 'belum',
  `jumlah_upload_ulang` int(11) DEFAULT 0 COMMENT 'Maksimal 1',
  `keterangan_admin` text DEFAULT NULL,
  `diverifikasi_oleh` int(11) DEFAULT NULL,
  `tanggal_verifikasi` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_siswa_dokumen` (`id_siswa`,`jenis_dokumen`),
  KEY `diverifikasi_oleh` (`diverifikasi_oleh`),
  KEY `idx_siswa` (`id_siswa`),
  KEY `idx_jenis` (`jenis_dokumen`),
  KEY `idx_status` (`status_verifikasi`),
  KEY `idx_status_ocr` (`status_ocr`),
  CONSTRAINT `siswa_dokumen_verifikasi_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `siswa_dokumen_verifikasi_ibfk_2` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: soal
DROP TABLE IF EXISTS `soal`;
CREATE TABLE `soal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `tipe_soal` enum('pilihan_ganda','isian_singkat','benar_salah','matching','esai') NOT NULL,
  `opsi_json` text DEFAULT NULL COMMENT 'JSON untuk opsi jawaban',
  `kunci_jawaban` text DEFAULT NULL,
  `bobot` decimal(5,2) DEFAULT 1.00,
  `urutan` int(11) DEFAULT 0,
  `gambar` varchar(255) DEFAULT NULL,
  `media_type` enum('gambar','video') DEFAULT NULL,
  `tingkat_kesulitan` enum('easy','medium','hard') DEFAULT 'medium',
  `tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_tipe` (`tipe_soal`),
  CONSTRAINT `soal_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: soal_matching
DROP TABLE IF EXISTS `soal_matching`;
CREATE TABLE `soal_matching` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_soal` int(11) NOT NULL,
  `item_kiri` varchar(255) NOT NULL,
  `item_kanan` varchar(255) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_soal` (`id_soal`),
  CONSTRAINT `soal_matching_ibfk_1` FOREIGN KEY (`id_soal`) REFERENCES `soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: soal_tag_relations
DROP TABLE IF EXISTS `soal_tag_relations`;
CREATE TABLE `soal_tag_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `soal_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_soal_tag` (`soal_id`,`tag_id`),
  KEY `idx_soal_id` (`soal_id`),
  KEY `idx_tag_id` (`tag_id`),
  CONSTRAINT `soal_tag_relations_ibfk_1` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `soal_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `soal_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: soal_tags
DROP TABLE IF EXISTS `soal_tags`;
CREATE TABLE `soal_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('topik','kurikulum','kompetensi_dasar','tingkat_kesulitan','custom') DEFAULT 'custom',
  `color` varchar(20) DEFAULT '#007bff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: student_progress
DROP TABLE IF EXISTS `student_progress`;
CREATE TABLE `student_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `mapel_id` int(11) NOT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `tahun_ajaran` varchar(20) DEFAULT NULL,
  `total_ujian` int(11) DEFAULT 0,
  `total_nilai` decimal(5,2) DEFAULT 0.00,
  `rata_rata` decimal(5,2) DEFAULT 0.00,
  `total_soal` int(11) DEFAULT 0,
  `total_benar` int(11) DEFAULT 0,
  `total_salah` int(11) DEFAULT 0,
  `accuracy` decimal(5,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_mapel_semester` (`student_id`,`mapel_id`,`semester`,`tahun_ajaran`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_mapel_id` (`mapel_id`),
  KEY `idx_semester` (`semester`),
  KEY `idx_tahun_ajaran` (`tahun_ajaran`),
  CONSTRAINT `student_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_progress_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mapel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sumatip_kelas_target
DROP TABLE IF EXISTS `sumatip_kelas_target`;
CREATE TABLE `sumatip_kelas_target` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ujian_kelas` (`id_ujian`,`id_kelas`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_kelas` (`id_kelas`),
  CONSTRAINT `sumatip_kelas_target_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sumatip_kelas_target_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sumatip_log
DROP TABLE IF EXISTS `sumatip_log`;
CREATE TABLE `sumatip_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'create, update, publish, complete, cancel',
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_ujian` (`id_ujian`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `sumatip_log_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sumatip_log_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sumatip_template
DROP TABLE IF EXISTS `sumatip_template`;
CREATE TABLE `sumatip_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_template` varchar(200) NOT NULL,
  `jenis_sumatip` enum('sumatip_tengah_semester','sumatip_akhir_semester','sumatip_akhir_tahun') NOT NULL,
  `id_mapel` int(11) DEFAULT NULL COMMENT 'NULL = template universal untuk semua mapel',
  `deskripsi` text DEFAULT NULL,
  `durasi_default` int(11) NOT NULL COMMENT 'Durasi default dalam menit',
  `total_soal_default` int(11) DEFAULT 0,
  `bobot_per_soal` decimal(5,2) DEFAULT 1.00,
  `acak_soal_default` tinyint(1) DEFAULT 1,
  `acak_opsi_default` tinyint(1) DEFAULT 1,
  `anti_contek_default` tinyint(1) DEFAULT 1,
  `min_submit_minutes_default` int(11) DEFAULT 0,
  `struktur_soal` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON: struktur soal (jumlah per tipe)' CHECK (json_valid(`struktur_soal`)),
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_jenis` (`jenis_sumatip`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `sumatip_template_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sumatip_template_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: system_changelog
DROP TABLE IF EXISTS `system_changelog`;
CREATE TABLE `system_changelog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version_id` int(11) NOT NULL,
  `type` enum('feature','bugfix','improvement','security','other') NOT NULL DEFAULT 'other',
  `title` varchar(255) NOT NULL COMMENT 'Judul perubahan',
  `description` text DEFAULT NULL COMMENT 'Deskripsi detail perubahan',
  `category` varchar(100) DEFAULT NULL COMMENT 'Kategori: PR, Ujian, Admin, dll',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_version` (`version_id`),
  KEY `idx_type` (`type`),
  KEY `idx_category` (`category`),
  CONSTRAINT `system_changelog_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `system_version` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: system_settings
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: system_version
DROP TABLE IF EXISTS `system_version`;
CREATE TABLE `system_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT 'Format: X.Y.Z (semantic versioning)',
  `release_date` date NOT NULL,
  `release_notes` text DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0 COMMENT '1 = versi saat ini',
  `created_by` int(11) NOT NULL COMMENT 'ID admin yang membuat versi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`),
  KEY `created_by` (`created_by`),
  KEY `idx_version` (`version`),
  KEY `idx_is_current` (`is_current`),
  KEY `idx_release_date` (`release_date`),
  CONSTRAINT `system_version_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tahun_ajaran
DROP TABLE IF EXISTS `tahun_ajaran`;
CREATE TABLE `tahun_ajaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tahun_ajaran` varchar(20) NOT NULL COMMENT 'Format: 2024/2025',
  `tahun_mulai` int(4) NOT NULL COMMENT 'Tahun mulai: 2024',
  `tahun_selesai` int(4) NOT NULL COMMENT 'Tahun selesai: 2025',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = aktif, 0 = tidak aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tahun_ajaran` (`tahun_ajaran`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_tahun_mulai` (`tahun_mulai`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: template_raport
DROP TABLE IF EXISTS `template_raport`;
CREATE TABLE `template_raport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_template` varchar(255) NOT NULL,
  `html_content` longtext NOT NULL COMMENT 'HTML content untuk template',
  `css_content` text DEFAULT NULL COMMENT 'CSS styling untuk template',
  `logo_raport` varchar(255) DEFAULT NULL COMMENT 'Logo khusus untuk raport',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `template_raport_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: token_request
DROP TABLE IF EXISTS `token_request`;
CREATE TABLE `token_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `id_token` int(11) DEFAULT NULL COMMENT 'Token yang diberikan setelah approve',
  `notes` text DEFAULT NULL COMMENT 'Catatan dari operator',
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approved_by` (`approved_by`),
  KEY `id_token` (`id_token`),
  KEY `idx_sesi` (`id_sesi`),
  KEY `idx_siswa` (`id_siswa`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_at` (`requested_at`),
  CONSTRAINT `token_request_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `token_request_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `token_request_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `token_request_ibfk_4` FOREIGN KEY (`id_token`) REFERENCES `token_ujian` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: token_ujian
DROP TABLE IF EXISTS `token_ujian`;
CREATE TABLE `token_ujian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `token` varchar(50) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `max_usage` int(11) DEFAULT NULL,
  `current_usage` int(11) DEFAULT 0,
  `status` enum('active','expired','revoked') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `created_by` (`created_by`),
  KEY `idx_sesi` (`id_sesi`),
  KEY `idx_token` (`token`),
  KEY `idx_status` (`status`),
  CONSTRAINT `token_ujian_ibfk_1` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `token_ujian_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: token_usage
DROP TABLE IF EXISTS `token_usage`;
CREATE TABLE `token_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_token` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `sesi_id` int(11) DEFAULT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`id_token`),
  KEY `idx_user` (`id_user`),
  CONSTRAINT `token_usage_ibfk_1` FOREIGN KEY (`id_token`) REFERENCES `token_ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `token_usage_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas
DROP TABLE IF EXISTS `tugas`;
CREATE TABLE `tugas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `deadline` datetime NOT NULL,
  `poin_maksimal` decimal(5,2) DEFAULT 100.00,
  `tipe_tugas` enum('individu','kelompok') DEFAULT 'individu',
  `allow_late_submission` tinyint(1) DEFAULT 0 COMMENT 'Izinkan submit setelah deadline',
  `max_file_size` int(11) DEFAULT 10485760 COMMENT 'dalam bytes, default 10MB',
  `allowed_extensions` varchar(255) DEFAULT 'pdf,doc,docx,zip,rar,ppt,pptx,xls,xlsx',
  `max_files` int(11) DEFAULT 5 COMMENT 'Maksimal jumlah file yang bisa diupload',
  `allow_edit_after_submit` tinyint(1) DEFAULT 1 COMMENT 'Boleh edit setelah submit (sebelum deadline)',
  `status` enum('draft','published','archived') DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tipe_tugas_mode` enum('file','soal') DEFAULT 'file' COMMENT 'Tipe tugas: file submission atau soal',
  PRIMARY KEY (`id`),
  KEY `idx_guru` (`id_guru`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_status` (`status`),
  KEY `idx_deadline` (`deadline`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `tugas_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tugas_ibfk_2` FOREIGN KEY (`id_guru`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas_attachment
DROP TABLE IF EXISTS `tugas_attachment`;
CREATE TABLE `tugas_attachment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tugas` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tugas` (`id_tugas`),
  CONSTRAINT `tugas_attachment_ibfk_1` FOREIGN KEY (`id_tugas`) REFERENCES `tugas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas_kelas
DROP TABLE IF EXISTS `tugas_kelas`;
CREATE TABLE `tugas_kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tugas` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tugas_kelas` (`id_tugas`,`id_kelas`),
  KEY `idx_tugas` (`id_tugas`),
  KEY `idx_kelas` (`id_kelas`),
  CONSTRAINT `tugas_kelas_ibfk_1` FOREIGN KEY (`id_tugas`) REFERENCES `tugas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tugas_kelas_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas_soal
DROP TABLE IF EXISTS `tugas_soal`;
CREATE TABLE `tugas_soal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tugas` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `tipe_soal` enum('pilihan_ganda','isian_singkat','benar_salah','matching','esai') NOT NULL,
  `opsi_json` text DEFAULT NULL COMMENT 'JSON array untuk pilihan ganda, benar/salah, matching',
  `kunci_jawaban` text DEFAULT NULL COMMENT 'Jawaban benar',
  `bobot` decimal(5,2) DEFAULT 1.00,
  `urutan` int(11) DEFAULT 0,
  `gambar` varchar(255) DEFAULT NULL,
  `media_type` enum('gambar','video') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tugas` (`id_tugas`),
  KEY `idx_urutan` (`urutan`),
  KEY `idx_tipe` (`tipe_soal`),
  CONSTRAINT `tugas_soal_ibfk_1` FOREIGN KEY (`id_tugas`) REFERENCES `tugas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas_soal_jawaban
DROP TABLE IF EXISTS `tugas_soal_jawaban`;
CREATE TABLE `tugas_soal_jawaban` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tugas_soal` int(11) NOT NULL,
  `id_tugas_submission` int(11) NOT NULL,
  `jawaban` text DEFAULT NULL,
  `jawaban_json` text DEFAULT NULL COMMENT 'JSON untuk multiple answers (matching, etc)',
  `nilai` decimal(5,2) DEFAULT NULL COMMENT 'Nilai yang diberikan guru (untuk esai)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_jawaban` (`id_tugas_soal`,`id_tugas_submission`),
  KEY `idx_tugas_soal` (`id_tugas_soal`),
  KEY `idx_submission` (`id_tugas_submission`),
  CONSTRAINT `tugas_soal_jawaban_ibfk_1` FOREIGN KEY (`id_tugas_soal`) REFERENCES `tugas_soal` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tugas_soal_jawaban_ibfk_2` FOREIGN KEY (`id_tugas_submission`) REFERENCES `tugas_submission` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas_soal_matching
DROP TABLE IF EXISTS `tugas_soal_matching`;
CREATE TABLE `tugas_soal_matching` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tugas_soal` int(11) NOT NULL,
  `item_kiri` varchar(255) NOT NULL,
  `item_kanan` varchar(255) NOT NULL,
  `urutan` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tugas_soal` (`id_tugas_soal`),
  CONSTRAINT `tugas_soal_matching_ibfk_1` FOREIGN KEY (`id_tugas_soal`) REFERENCES `tugas_soal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas_submission
DROP TABLE IF EXISTS `tugas_submission`;
CREATE TABLE `tugas_submission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tugas` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `komentar` text DEFAULT NULL,
  `jawaban_text` text DEFAULT NULL,
  `tipe_submission` enum('file','text','both') DEFAULT 'file',
  `nilai` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('belum_dikumpulkan','draft','sudah_dikumpulkan','dinilai','terlambat','ditolak') DEFAULT 'belum_dikumpulkan',
  `waktu_submit` timestamp NULL DEFAULT NULL,
  `waktu_dinilai` timestamp NULL DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_late` tinyint(1) DEFAULT 0 COMMENT 'Apakah submit terlambat',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`id_tugas`,`id_siswa`),
  KEY `idx_tugas` (`id_tugas`),
  KEY `idx_siswa` (`id_siswa`),
  KEY `idx_status` (`status`),
  KEY `idx_waktu_submit` (`waktu_submit`),
  KEY `idx_waktu_dinilai` (`waktu_dinilai`),
  CONSTRAINT `tugas_submission_ibfk_1` FOREIGN KEY (`id_tugas`) REFERENCES `tugas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tugas_submission_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tugas_submission_file
DROP TABLE IF EXISTS `tugas_submission_file`;
CREATE TABLE `tugas_submission_file` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_submission` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `urutan` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_submission` (`id_submission`),
  CONSTRAINT `tugas_submission_file_ibfk_1` FOREIGN KEY (`id_submission`) REFERENCES `tugas_submission` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ujian
DROP TABLE IF EXISTS `ujian`;
CREATE TABLE `ujian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `id_mapel` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `tipe` varchar(50) DEFAULT NULL,
  `tipe_asesmen` enum('regular','sumatip','sumatip_tengah_semester','sumatip_akhir_semester','sumatip_akhir_tahun') DEFAULT 'regular' COMMENT 'Jenis asesmen: regular atau SUMATIP',
  `tahun_ajaran` varchar(20) DEFAULT NULL COMMENT 'Tahun ajaran (contoh: 2024/2025)',
  `semester` enum('ganjil','genap') DEFAULT NULL COMMENT 'Semester: ganjil atau genap',
  `periode_sumatip` varchar(50) DEFAULT NULL COMMENT 'Periode SUMATIP (contoh: Semester Ganjil 2024/2025)',
  `is_mandatory` tinyint(1) DEFAULT 0 COMMENT 'Apakah SUMATIP ini wajib untuk semua kelas',
  `id_template_sumatip` int(11) DEFAULT NULL COMMENT 'ID template SUMATIP yang digunakan (optional)',
  `tingkat_kelas` varchar(20) DEFAULT NULL COMMENT 'Tingkat kelas untuk filter (contoh: VII, VIII, IX)',
  `durasi` int(11) NOT NULL COMMENT 'dalam menit',
  `status` enum('draft','published','completed','cancelled') DEFAULT 'draft',
  `archived_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu ujian di-archive',
  `acak_soal` tinyint(1) DEFAULT 0,
  `acak_opsi` tinyint(1) DEFAULT 0,
  `show_result` tinyint(1) DEFAULT 1,
  `min_submit_minutes` int(11) DEFAULT 0 COMMENT 'minimum menit sebelum bisa submit',
  `ai_correction_enabled` tinyint(1) DEFAULT 0,
  `ai_api_key` varchar(255) DEFAULT NULL,
  `anti_contek_enabled` tinyint(1) DEFAULT 0,
  `show_review_mode` tinyint(1) DEFAULT 1 COMMENT 'Tampilkan mode review sebelum submit',
  `plagiarisme_check_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_guru` (`id_guru`),
  KEY `idx_mapel` (`id_mapel`),
  KEY `idx_status` (`status`),
  KEY `idx_tipe_asesmen` (`tipe_asesmen`),
  KEY `idx_tahun_semester` (`tahun_ajaran`,`semester`),
  KEY `idx_periode_sumatip` (`periode_sumatip`),
  KEY `idx_tingkat_kelas` (`tingkat_kelas`),
  CONSTRAINT `ujian_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ujian_ibfk_2` FOREIGN KEY (`id_guru`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ujian_kelas
DROP TABLE IF EXISTS `ujian_kelas`;
CREATE TABLE `ujian_kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ujian` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ujian_kelas` (`id_ujian`,`id_kelas`),
  KEY `id_kelas` (`id_kelas`),
  CONSTRAINT `ujian_kelas_ibfk_1` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ujian_kelas_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ujian_templates
DROP TABLE IF EXISTS `ujian_templates`;
CREATE TABLE `ujian_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `id_mapel` int(11) DEFAULT NULL,
  `durasi` int(11) DEFAULT 90,
  `acak_soal` tinyint(1) DEFAULT 1,
  `acak_opsi` tinyint(1) DEFAULT 1,
  `anti_contek_enabled` tinyint(1) DEFAULT 1,
  `min_submit_minutes` int(11) DEFAULT 0,
  `ai_correction_enabled` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_id_mapel` (`id_mapel`),
  CONSTRAINT `ujian_templates_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ujian_templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_kelas
DROP TABLE IF EXISTS `user_kelas`;
CREATE TABLE `user_kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_kelas` int(11) NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `semester` enum('ganjil','genap') DEFAULT 'ganjil',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_kelas` (`id_kelas`),
  KEY `idx_user_kelas` (`id_user`,`id_kelas`),
  CONSTRAINT `user_kelas_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_kelas_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `nis` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guru','operator','siswa') NOT NULL,
  `is_operator` tinyint(1) DEFAULT 0 COMMENT 'Akses operator untuk guru',
  `can_create_assessment_soal` tinyint(1) DEFAULT 0 COMMENT '1 = guru yang diizinkan membuat soal assessment (tengah semester, semester, tahunan)',
  `nama` varchar(100) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `tahun_masuk` year(4) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_tanggal_lahir` (`tanggal_lahir`),
  KEY `idx_can_create_assessment_soal` (`can_create_assessment_soal`),
  KEY `idx_nisn` (`nisn`),
  KEY `idx_nis` (`nis`)
) ENGINE=InnoDB AUTO_INCREMENT=1121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: verifikasi_data_history
DROP TABLE IF EXISTS `verifikasi_data_history`;
CREATE TABLE `verifikasi_data_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_verifikasi` int(11) NOT NULL COMMENT 'ID dari verifikasi_data_siswa',
  `id_siswa` int(11) NOT NULL,
  `action` enum('upload','upload_ulang','verifikasi_valid','verifikasi_tidak_valid','set_residu','edit_admin','scan_ocr') NOT NULL,
  `status_sebelum` varchar(50) DEFAULT NULL,
  `status_sesudah` varchar(50) DEFAULT NULL,
  `data_sebelum` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot data sebelum perubahan' CHECK (json_valid(`data_sebelum`)),
  `data_sesudah` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot data sesudah perubahan' CHECK (json_valid(`data_sesudah`)),
  `keterangan` text DEFAULT NULL,
  `dilakukan_oleh` int(11) NOT NULL COMMENT 'ID user (siswa atau admin)',
  `role_user` varchar(20) NOT NULL COMMENT 'siswa atau admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dilakukan_oleh` (`dilakukan_oleh`),
  KEY `idx_verifikasi` (`id_verifikasi`),
  KEY `idx_siswa` (`id_siswa`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `verifikasi_data_history_ibfk_1` FOREIGN KEY (`id_verifikasi`) REFERENCES `verifikasi_data_siswa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifikasi_data_history_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifikasi_data_history_ibfk_3` FOREIGN KEY (`dilakukan_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: verifikasi_data_siswa
DROP TABLE IF EXISTS `verifikasi_data_siswa`;
CREATE TABLE `verifikasi_data_siswa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_siswa` int(11) NOT NULL,
  `status_overall` enum('belum_lengkap','menunggu_verifikasi','valid','tidak_valid','upload_ulang','residu') DEFAULT 'belum_lengkap',
  `nama_anak_ijazah` varchar(255) DEFAULT NULL,
  `nama_anak_kk` varchar(255) DEFAULT NULL,
  `nama_ayah_kk` varchar(255) DEFAULT NULL,
  `nama_ibu_kk` varchar(255) DEFAULT NULL,
  `nama_anak_akte` varchar(255) DEFAULT NULL,
  `nama_ayah_akte` varchar(255) DEFAULT NULL,
  `nama_ibu_akte` varchar(255) DEFAULT NULL,
  `kesesuaian_nama_anak` enum('sesuai','tidak_sesuai','belum_dicek') DEFAULT 'belum_dicek',
  `kesesuaian_nama_ayah` enum('sesuai','tidak_sesuai','belum_dicek') DEFAULT 'belum_dicek',
  `kesesuaian_nama_ibu` enum('sesuai','tidak_sesuai','belum_dicek') DEFAULT 'belum_dicek',
  `detail_ketidaksesuaian` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Detail dokumen mana yang salah dan dimana salahnya' CHECK (json_valid(`detail_ketidaksesuaian`)),
  `menu_aktif` tinyint(1) DEFAULT 1,
  `catatan_admin` text DEFAULT NULL,
  `jumlah_upload_ulang` int(11) DEFAULT 0,
  `diverifikasi_oleh` int(11) DEFAULT NULL,
  `tanggal_verifikasi` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_siswa` (`id_siswa`),
  KEY `idx_status` (`status_overall`),
  KEY `idx_kesesuaian_anak` (`kesesuaian_nama_anak`),
  KEY `idx_kesesuaian_ayah` (`kesesuaian_nama_ayah`),
  KEY `idx_kesesuaian_ibu` (`kesesuaian_nama_ibu`),
  KEY `diverifikasi_oleh` (`diverifikasi_oleh`),
  CONSTRAINT `fk_verifikasi_diverifikasi_oleh` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_verifikasi_id_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifikasi_data_siswa_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifikasi_data_siswa_ibfk_2` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verifikasi_data_siswa_ibfk_3` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifikasi_data_siswa_ibfk_4` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verifikasi_data_siswa_ibfk_5` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifikasi_data_siswa_ibfk_6` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verifikasi_data_siswa_ibfk_7` FOREIGN KEY (`id_siswa`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verifikasi_data_siswa_ibfk_8` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: verifikasi_settings
DROP TABLE IF EXISTS `verifikasi_settings`;
CREATE TABLE `verifikasi_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_key` (`setting_key`),
  CONSTRAINT `verifikasi_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: wali_kelas
DROP TABLE IF EXISTS `wali_kelas`;
CREATE TABLE `wali_kelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_guru` int(11) NOT NULL COMMENT 'ID guru yang menjadi wali kelas',
  `id_kelas` int(11) NOT NULL COMMENT 'ID kelas yang diwalikan',
  `tahun_ajaran` varchar(20) NOT NULL COMMENT 'Tahun ajaran',
  `semester` enum('ganjil','genap') DEFAULT 'ganjil',
  `level_access` enum('admin','operator') DEFAULT 'operator' COMMENT 'Level akses wali kelas',
  `created_by` int(11) NOT NULL COMMENT 'ID user yang membuat assignment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wali_kelas` (`id_kelas`,`tahun_ajaran`,`semester`),
  KEY `created_by` (`created_by`),
  KEY `idx_guru` (`id_guru`),
  KEY `idx_kelas` (`id_kelas`),
  KEY `idx_tahun_ajaran` (`tahun_ajaran`),
  CONSTRAINT `wali_kelas_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wali_kelas_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wali_kelas_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
