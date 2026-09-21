<?php
declare(strict_types=1);

/** Collects every expiry date and deadline across the app. */
final class Alerts
{
    public const CATEGORIES = [
        'employees'     => 'Employee IDs & visas',
        'documents'     => 'Company documents',
        'emp_documents' => 'Employee documents',
        'assets'        => 'Vehicles & asset renewals',
        'tasks'         => 'Admin deadlines',
    ];

    /**
     * @param array{window?:int, include_expired?:bool, categories?:array, check_perms?:bool, limit?:int, expired_days?:int} $o
     */
    public static function items(array $o = []): array
    {
        $window   = (int) ($o['window'] ?? setting('alert_window', 30));
        $expired  = (bool) ($o['include_expired'] ?? (setting('alert_include_expired', '1') === '1'));
        $cats     = $o['categories'] ?? self::enabledCategories();
        $perms    = $o['check_perms'] ?? true;
        $until    = date('Y-m-d', strtotime("+{$window} days"));
        $from     = $expired ? date('Y-m-d', strtotime('-' . (int) ($o['expired_days'] ?? 365) . ' days')) : today();
        $items    = [];

        $ok = fn (string $perm) => !$perms || can($perm);

        if (in_array('employees', $cats, true) && $ok('employees')) {
            foreach (['qid_expiry' => 'QID', 'passport_expiry' => 'Passport', 'visa_expiry' => 'Visa'] as $col => $label) {
                $rows = DB::all("SELECT id, name, department, $col AS d FROM employees WHERE status <> 'inactive' AND $col BETWEEN ? AND ?", [$from, $until]);
                foreach ($rows as $r) {
                    $items[] = self::item('employees', 'employees/' . $r['id'], $r['name'], "{$label}, " . ($r['department'] ?: 'No department'), $label, $r['d']);
                }
            }
        }

        if (in_array('documents', $cats, true) && $ok('documents')) {
            foreach (DB::all("SELECT id, name, category, owner, expiry_date AS d FROM documents WHERE expiry_date BETWEEN ? AND ?", [$from, $until]) as $r) {
                $items[] = self::item('documents', 'documents/' . $r['id'] . '/edit', $r['name'], trim(($r['category'] ?: 'Document') . ($r['owner'] ? ', ' . $r['owner'] : '')), 'Document', $r['d']);
            }
        }

        if (in_array('emp_documents', $cats, true) && $ok('emp_documents')) {
            $rows = DB::all("SELECT t.id, t.doc_type, t.expiry_date AS d, e.name FROM emp_documents t LEFT JOIN employees e ON e.id = t.employee_id WHERE t.expiry_date BETWEEN ? AND ?", [$from, $until]);
            foreach ($rows as $r) {
                $items[] = self::item('emp_documents', 'emp-documents/' . $r['id'] . '/edit', $r['name'] ?: 'Unassigned', $r['doc_type'], $r['doc_type'], $r['d']);
            }
        }

        if (in_array('assets', $cats, true) && $ok('assets')) {
            foreach (['registration_expiry' => 'Istimara', 'coverage_expiry' => 'Insurance / warranty'] as $col => $label) {
                $rows = DB::all("SELECT id, item, reference, asset_type, $col AS d FROM assets WHERE status <> 'retired' AND $col BETWEEN ? AND ?", [$from, $until]);
                foreach ($rows as $r) {
                    $l = $r['asset_type'] === 'Vehicle' ? $label : ($col === 'coverage_expiry' ? 'Warranty' : 'Registration');
                    $items[] = self::item('assets', 'assets/' . $r['id'] . '/edit', $r['item'], trim($l . ($r['reference'] ? ', ' . $r['reference'] : '')), $l, $r['d']);
                }
            }
        }

        if (in_array('tasks', $cats, true) && $ok('tasks')) {
            foreach (DB::all("SELECT id, title, owner, priority, deadline AS d FROM tasks WHERE status <> 'done' AND deadline BETWEEN ? AND ?", [$from, $until]) as $r) {
                $items[] = self::item('tasks', 'tasks/' . $r['id'] . '/edit', $r['title'], 'Deadline' . ($r['owner'] ? ', ' . $r['owner'] : ''), 'Deadline', $r['d']);
            }
        }

        usort($items, fn ($a, $b) => $a['days'] <=> $b['days']);
        return isset($o['limit']) ? array_slice($items, 0, (int) $o['limit']) : $items;
    }

    public static function enabledCategories(): array
    {
        return array_values(array_filter(explode(',', (string) setting('alert_categories', implode(',', array_keys(self::CATEGORIES))))));
    }

    private static function item(string $cat, string $link, string $title, string $sub, string $label, string $date): array
    {
        $days = (int) days_until($date);
        return [
            'category' => $cat,
            'link'     => $link,
            'title'    => $title,
            'sub'      => $sub,
            'label'    => $label,
            'date'     => $date,
            'days'     => $days,
            'risk'     => $days < 0 ? 'expired' : 'due',
        ];
    }
}
