<?php
declare(strict_types=1);

final class AttendanceController
{
    private const STATUSES = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On leave', 'remote' => 'Remote'];

    public function roster(): void
    {
        Auth::require('attendance');
        $date = input('date', today(), 'get');
        if (!valid_date($date)) {
            $date = today();
        }
        $dept = input('dept', '', 'get');
        $params = [$date];
        $sql = "SELECT e.id, e.name, e.department, e.designation, a.status, a.check_in, a.check_out, a.notes,
                       (SELECT l.leave_type FROM leave_requests l WHERE l.employee_id = e.id AND l.status = 'approved' AND ? BETWEEN l.start_date AND l.end_date LIMIT 1) AS leave_type
                FROM employees e
                LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ?
                WHERE e.status <> 'inactive'";
        $params[] = $date;
        if ($dept !== '') {
            $sql .= ' AND e.department = ?';
            $params[] = $dept;
        }
        $rows = DB::all($sql . ' ORDER BY e.department, e.name', $params);

        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        $unmarked = 0;
        foreach ($rows as $r) {
            $r['status'] ? $counts[$r['status']]++ : $unmarked++;
        }

        view('attendance/roster', [
            'title'    => 'Attendance',
            'date'     => $date,
            'dept'     => $dept,
            'depts'    => Modules::suggestions('departments'),
            'rows'     => $rows,
            'counts'   => $counts,
            'unmarked' => $unmarked,
            'biometricUnmatched' => (int) DB::val('SELECT COUNT(*) FROM biometric_punches WHERE employee_id IS NULL'),
            'statuses' => self::STATUSES,
            'canEdit'  => can('attendance', 'edit'),
            'pageModule' => 'attendance',
        ]);
    }

    public function mark(): void
    {
        Auth::require('attendance', 'edit');
        $b = json_body() ?: $_POST;
        $empId = (int) ($b['employee_id'] ?? 0);
        $date = (string) ($b['date'] ?? '');
        $status = (string) ($b['status'] ?? '');
        $in = $this->time($b['check_in'] ?? '');
        $out = $this->time($b['check_out'] ?? '');
        $notes = mb_substr(trim((string) ($b['notes'] ?? '')), 0, 255);

        if (!valid_date($date) || !DB::val('SELECT id FROM employees WHERE id = ?', [$empId])) {
            json_out(['ok' => false, 'error' => 'Invalid employee or date.'], 422);
        }
        if ($status === 'clear') {
            DB::run('DELETE FROM attendance WHERE employee_id = ? AND work_date = ?', [$empId, $date]);
            json_out(['ok' => true, 'cleared' => true]);
        }
        if (!isset(self::STATUSES[$status])) {
            json_out(['ok' => false, 'error' => 'Choose a status.'], 422);
        }
        $auto = in_array($status, ['present', 'late', 'remote'], true) && $date === today() ? date('H:i') : null;
        DB::run(
            'INSERT INTO attendance (employee_id, work_date, status, check_in, check_out, notes) VALUES (?, ?, ?, COALESCE(?, ?), ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), check_in = COALESCE(?, check_in, ?), check_out = VALUES(check_out), notes = VALUES(notes)',
            [$empId, $date, $status, $in, $auto, $out, $notes ?: null, $in, $auto]
        );
        $row = DB::one('SELECT status, check_in, check_out FROM attendance WHERE employee_id = ? AND work_date = ?', [$empId, $date]);
        json_out(['ok' => true, 'status' => $row['status'], 'check_in' => $row['check_in'] ? substr($row['check_in'], 0, 5) : '', 'check_out' => $row['check_out'] ? substr($row['check_out'], 0, 5) : '']);
    }

    public function markAll(): void
    {
        Auth::require('attendance', 'edit');
        $date = input('date');
        if (!valid_date($date)) {
            abort(422, 'Invalid date.');
        }
        $n = DB::run(
            "INSERT IGNORE INTO attendance (employee_id, work_date, status)
             SELECT e.id, ?, IF(EXISTS(SELECT 1 FROM leave_requests l WHERE l.employee_id = e.id AND l.status = 'approved' AND ? BETWEEN l.start_date AND l.end_date), 'on_leave', 'present')
             FROM employees e WHERE e.status <> 'inactive'",
            [$date, $date]
        )->rowCount();
        Activity::log('bulk_marked', 'attendance', null, "Marked {$n} unmarked employees for {$date}");
        flash('success', "Marked {$n} employees. People on approved leave were marked as on leave.");
        redirect('attendance', ['date' => $date]);
    }

    private function time(mixed $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
    }
}
