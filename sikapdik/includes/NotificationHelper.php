<?php
/**
 * Notification Helper - Auto-generate notifications across the system
 * SIKAPDIK
 */
class NotificationHelper {

    /**
     * Create a notification for a user
     */
    public static function create($userId, $title, $message, $type = 'info', $link = null) {
        try {
            $db = Database::getInstance();
            $db->insert('notifications', [
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'is_read' => 0
            ]);
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
    }

    /**
     * Notify when behavior is recorded (pelanggaran)
     * -> Notifies: Wali Kelas of that student's class
     */
    public static function onBehaviorRecorded($studentId, $type, $categoryName, $points, $recorderName) {
        $db = Database::getInstance();
        $student = $db->fetch("SELECT s.full_name, s.class_id, c.homeroom_teacher_id FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$studentId]);
        if (!$student) return;

        // Notify homeroom teacher if behavior recorded by guru_mapel
        if ($student['homeroom_teacher_id']) {
            $teacher = $db->fetch("SELECT user_id FROM teachers WHERE id = ?", [$student['homeroom_teacher_id']]);
            if ($teacher && $teacher['user_id']) {
                $label = $type === 'keteladanan' ? 'Keteladanan' : 'Pelanggaran';
                $icon = $type === 'keteladanan' ? 'success' : 'warning';
                self::create(
                    $teacher['user_id'],
                    "Catatan {$label} Baru",
                    "{$recorderName} mencatat {$label} untuk {$student['full_name']}: {$categoryName} ({$points} poin)",
                    $icon,
                    'modules/wali_kelas/teacher_notes.php'
                );
            }
        }
    }

    /**
     * Notify when follow-up is created
     * -> Notifies: Kepala Sekolah, Parent (if show_to_parent)
     */
    public static function onFollowUpCreated($studentId, $followUpType, $creatorName, $showToParent = false) {
        $db = Database::getInstance();
        $student = $db->fetch("SELECT full_name FROM students WHERE id = ?", [$studentId]);
        if (!$student) return;

        $typeLabel = ucfirst(str_replace('_', ' ', $followUpType));

        // Notify Kepala Sekolah
        $kepsekUsers = $db->fetchAll("SELECT id FROM users WHERE role = 'kepala_sekolah' AND is_active = 1");
        foreach ($kepsekUsers as $u) {
            self::create(
                $u['id'],
                "Tindak Lanjut Baru",
                "{$creatorName} membuat tindak lanjut ({$typeLabel}) untuk {$student['full_name']}",
                'warning',
                'modules/kepala_sekolah/follow_ups.php'
            );
        }

        // Notify parent if show_to_parent
        if ($showToParent) {
            $parents = $db->fetchAll("SELECT p.user_id FROM parent_student ps JOIN parents p ON ps.parent_id = p.id WHERE ps.student_id = ? AND p.user_id IS NOT NULL", [$studentId]);
            foreach ($parents as $p) {
                self::create(
                    $p['user_id'],
                    "Informasi Pembinaan",
                    "Sekolah telah memberikan tindak lanjut ({$typeLabel}) untuk anak Anda: {$student['full_name']}",
                    'info',
                    'modules/orang_tua/follow_ups.php'
                );
            }
        }
    }

    /**
     * Notify when achievement is recorded
     * -> Notifies: Parent, Kepala Sekolah
     */
    public static function onAchievementRecorded($studentId, $title, $level, $points) {
        $db = Database::getInstance();
        $student = $db->fetch("SELECT full_name FROM students WHERE id = ?", [$studentId]);
        if (!$student) return;

        $levelLabel = ucfirst($level);

        // Notify parents
        $parents = $db->fetchAll("SELECT p.user_id FROM parent_student ps JOIN parents p ON ps.parent_id = p.id WHERE ps.student_id = ? AND p.user_id IS NOT NULL", [$studentId]);
        foreach ($parents as $p) {
            self::create(
                $p['user_id'],
                "Prestasi Anak Anda!",
                "{$student['full_name']} meraih prestasi: {$title} (Tingkat {$levelLabel}, +{$points} poin)",
                'success',
                'modules/orang_tua/achievements.php'
            );
        }

        // Notify Kepala Sekolah
        $kepsekUsers = $db->fetchAll("SELECT id FROM users WHERE role = 'kepala_sekolah' AND is_active = 1");
        foreach ($kepsekUsers as $u) {
            self::create(
                $u['id'],
                "Prestasi Siswa Baru",
                "{$student['full_name']} meraih: {$title} (Tingkat {$levelLabel})",
                'success',
                'modules/kepala_sekolah/achievements.php'
            );
        }
    }

    /**
     * Notify when student has repeated lateness (3+ times this week)
     * -> Notifies: Wali Kelas, Parent
     */
    public static function onRepeatedLateness($studentId, $lateCount) {
        $db = Database::getInstance();
        $student = $db->fetch("SELECT s.full_name, s.class_id, c.homeroom_teacher_id FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$studentId]);
        if (!$student) return;

        // Notify wali kelas
        if ($student['homeroom_teacher_id']) {
            $teacher = $db->fetch("SELECT user_id FROM teachers WHERE id = ?", [$student['homeroom_teacher_id']]);
            if ($teacher && $teacher['user_id']) {
                self::create(
                    $teacher['user_id'],
                    "Keterlambatan Berulang",
                    "{$student['full_name']} sudah terlambat {$lateCount} kali minggu ini. Perlu tindak lanjut.",
                    'danger',
                    'modules/wali_kelas/follow_ups.php?action=add&student_id=' . $studentId
                );
            }
        }

        // Notify parent
        $parents = $db->fetchAll("SELECT p.user_id FROM parent_student ps JOIN parents p ON ps.parent_id = p.id WHERE ps.student_id = ? AND p.user_id IS NOT NULL", [$studentId]);
        foreach ($parents as $p) {
            self::create(
                $p['user_id'],
                "Perhatian: Keterlambatan",
                "Anak Anda ({$student['full_name']}) sudah terlambat {$lateCount} kali minggu ini.",
                'warning',
                'modules/orang_tua/attendance.php'
            );
        }
    }

    /**
     * Notify admin when behavior validation is needed
     */
    public static function onValidationNeeded($studentId, $recorderName, $categoryName) {
        $db = Database::getInstance();
        $student = $db->fetch("SELECT s.full_name, s.class_id, c.homeroom_teacher_id FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?", [$studentId]);
        if (!$student || !$student['homeroom_teacher_id']) return;

        $teacher = $db->fetch("SELECT user_id FROM teachers WHERE id = ?", [$student['homeroom_teacher_id']]);
        if ($teacher && $teacher['user_id']) {
            self::create(
                $teacher['user_id'],
                "Validasi Diperlukan",
                "Catatan dari {$recorderName} untuk {$student['full_name']} ({$categoryName}) menunggu validasi Anda.",
                'info',
                'modules/wali_kelas/teacher_notes.php'
            );
        }
    }
}
