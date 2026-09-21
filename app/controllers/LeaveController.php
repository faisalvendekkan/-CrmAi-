<?php
declare(strict_types=1);

final class LeaveController
{
    public function decide(int $id): void
    {
        $u = Auth::require('leave', 'edit');
        $l = DB::one('SELECT l.*, e.name FROM leave_requests l LEFT JOIN employees e ON e.id = l.employee_id WHERE l.id = ?', [$id]) ?? abort(404);
        $decision = input('decision');
        if (!in_array($decision, ['approved', 'rejected', 'pending'], true)) {
            abort(400, 'Unknown decision.');
        }
        DB::run('UPDATE leave_requests SET status = ?, decided_by = ?, decided_at = ? WHERE id = ?', [
            $decision, $decision === 'pending' ? null : $u['id'], $decision === 'pending' ? null : date('Y-m-d H:i:s'), $id,
        ]);
        Activity::log($decision, 'leave', $id, 'Leave for ' . ($l['name'] ?? '—') . ' ' . $decision);
        flash('success', 'Leave for ' . ($l['name'] ?? 'employee') . ' marked as ' . $decision . '.');
        $back = input('return');
        redirect(match (true) {
            $back === 'dashboard'                      => '',
            (bool) preg_match('#^employees/\d+$#', $back) => $back,
            default                                    => 'leave',
        });
    }
}
