<?php
declare(strict_types=1);

final class EmployeeController
{
    public function show(int $id): void
    {
        Auth::require('employees');
        $e = DB::one('SELECT * FROM employees WHERE id = ?', [$id]) ?? abort(404);

        $docs = can('emp_documents') ? DB::all('SELECT * FROM emp_documents WHERE employee_id = ? ORDER BY expiry_date IS NULL, expiry_date', [$id]) : [];
        $leave = can('leave') ? DB::all('SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY start_date DESC LIMIT 12', [$id]) : [];
        $assets = can('assets') ? DB::all('SELECT * FROM assets WHERE assigned_to = ? ORDER BY asset_type, item', [$e['name']]) : [];
        $att = [];
        if (can('attendance')) {
            foreach (DB::all('SELECT status, COUNT(*) c FROM attendance WHERE employee_id = ? AND work_date >= ? GROUP BY status', [$id, date('Y-m-d', strtotime('-30 days'))]) as $r) {
                $att[$r['status']] = (int) $r['c'];
            }
        }
        $entitled = (float) setting('annual_leave_days', 21);
        $used = AiContext::leaveUsed($id);

        view('employees/show', [
            'title'    => $e['name'],
            'e'        => $e,
            'docs'     => $docs,
            'leave'    => $leave,
            'assets'   => $assets,
            'att'      => $att,
            'entitled' => $entitled,
            'used'     => $used,
            'pageModule' => 'employees',
            'pageRecord' => $id,
        ]);
    }
}
