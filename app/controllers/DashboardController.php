<?php
declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        $u = Auth::require();
        $window = (int) setting('alert_window', 30);

        $horizon = Alerts::items(['window' => 90, 'include_expired' => true, 'categories' => array_keys(Alerts::CATEGORIES), 'expired_days' => 60]);
        $attention = array_values(array_filter($horizon, fn ($a) => $a['days'] <= $window));

        $stats = [];
        if (can('employees')) {
            $stats['employees'] = (int) DB::val("SELECT COUNT(*) FROM employees WHERE status <> 'inactive'");
        }
        if (can('leave')) {
            $stats['on_leave'] = (int) DB::val("SELECT COUNT(DISTINCT employee_id) FROM leave_requests WHERE status = 'approved' AND ? BETWEEN start_date AND end_date", [today()]);
            $stats['pending_leave'] = (int) DB::val("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
        }
        if (can('tasks')) {
            $stats['open_tasks'] = (int) DB::val("SELECT COUNT(*) FROM tasks WHERE status <> 'done'");
            $stats['overdue_tasks'] = (int) DB::val("SELECT COUNT(*) FROM tasks WHERE status <> 'done' AND deadline < CURDATE()");
        }

        $attendance = null;
        if (can('attendance')) {
            $active = (int) DB::val("SELECT COUNT(*) FROM employees WHERE status <> 'inactive'");
            $marks = [];
            foreach (DB::all("SELECT a.status, COUNT(*) c FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.work_date = ? AND e.status <> 'inactive' GROUP BY a.status", [today()]) as $r) {
                $marks[$r['status']] = (int) $r['c'];
            }
            $attendance = ['active' => $active, 'marks' => $marks, 'unmarked' => max(0, $active - array_sum($marks))];
        }

        $pending = can('leave') ? DB::all("SELECT l.id, l.leave_type, l.start_date, l.end_date, l.days, e.name, e.department FROM leave_requests l LEFT JOIN employees e ON e.id = l.employee_id WHERE l.status = 'pending' ORDER BY l.start_date LIMIT 5") : [];
        $tasks = can('tasks') ? DB::all("SELECT id, title, owner, priority, deadline, status FROM tasks WHERE status <> 'done' ORDER BY FIELD(priority,'high','medium','low'), deadline IS NULL, deadline LIMIT 6") : [];

        view('dashboard', [
            'title'      => 'Dashboard',
            'u'          => $u,
            'window'     => $window,
            'horizon'    => $horizon,
            'attention'  => array_slice($attention, 0, 8),
            'attentionTotal' => count($attention),
            'stats'      => $stats,
            'attendance' => $attendance,
            'pending'    => $pending,
            'tasks'      => $tasks,
            'pageModule' => 'dashboard',
        ]);
    }
}
