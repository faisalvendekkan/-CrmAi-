<?php
declare(strict_types=1);

/** List, create, edit, delete and export for every module defined in Modules::all(). */
final class ModuleController
{
    private const PER_PAGE = 50;

    public function index(string $key): void
    {
        $m = $this->module($key);
        Auth::require($m['perm']);

        [$where, $params, $filters] = $this->filters($m);
        [$select, $from] = $this->source($m);

        $total = (int) DB::val("SELECT COUNT(*) FROM $from" . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $params);
        $page  = max(1, (int) input('page', '1', 'get'));
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);
        $off   = ($page - 1) * self::PER_PAGE;

        $rows = DB::all(
            "SELECT $select FROM $from" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
            " ORDER BY {$m['sort']} LIMIT " . self::PER_PAGE . " OFFSET $off",
            $params
        );

        view('module/index', [
            'title'   => $m['label'],
            'm'       => $m,
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'filters' => $filters,
            'summary' => $this->summary($m),
            'depts'   => in_array('department', $m['filters'], true) ? Modules::suggestions('departments') : [],
            'pageModule' => $key,
        ]);
    }

    public function form(string $key, int $id = 0): void
    {
        $m = $this->module($key);
        Auth::require($m['perm'], 'edit');

        $row = [];
        if ($id) {
            $row = DB::one("SELECT * FROM `{$m['table']}` WHERE id = ?", [$id]) ?? abort(404);
        } else {
            foreach ($m['fields'] as $col => $f) {
                $row[$col] = ($f['default'] ?? '') === 'today' ? today() : ($f['default'] ?? '');
            }
            if (isset($_GET['employee_id'])) {
                $row['employee_id'] = (int) $_GET['employee_id'];
            }
        }
        foreach ($_SESSION['_old'] ?? [] as $k => $v) {
            if (isset($m['fields'][$k])) {
                $row[$k] = $v;
            }
        }
        forget_input();

        view('module/form', [
            'title'     => ($id ? 'Edit ' : 'New ') . $m['singular'],
            'm'         => $m,
            'row'       => $row,
            'id'        => $id,
            'employees' => Modules::hasEmployee($m) ? Modules::employeeOptions() : [],
            'pageModule' => $key,
        ]);
    }

    public function save(string $key): void
    {
        $m  = $this->module($key);
        $u  = Auth::require($m['perm'], 'edit');
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id ? (DB::one("SELECT * FROM `{$m['table']}` WHERE id = ?", [$id]) ?? abort(404)) : null;

        [$data, $errors] = $this->validate($m);

        if ($m['table'] === 'leave_requests') {
            if (($data['start_date'] ?? '') && ($data['end_date'] ?? '') && $data['end_date'] < $data['start_date']) {
                $errors[] = 'The end date must be on or after the start date.';
            }
            $data['days'] = Seeder::days((string) ($data['start_date'] ?? ''), (string) ($data['end_date'] ?? ''));
            if (($existing['status'] ?? 'pending') !== $data['status'] && $data['status'] !== 'pending') {
                $data['decided_by'] = $u['id'];
                $data['decided_at'] = date('Y-m-d H:i:s');
            }
        }

        if ($errors) {
            remember_input($_POST);
            flash('error', implode(' ', $errors));
            redirect($id ? "$key/$id/edit" : "$key/new");
        }

        try {
            if ($id) {
                DB::update($m['table'], $data, $id);
            } else {
                $id = DB::insert($m['table'], $data);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                remember_input($_POST);
                flash('error', $m['table'] === 'attendance' ? 'This employee already has an attendance record for that date. Edit the existing record instead.' : 'This record already exists.');
                redirect($existing ? "$key/$id/edit" : "$key/new");
            }
            throw $e;
        }

        $label = $this->title($m, $data);
        Activity::log($existing ? 'updated' : 'created', $m['perm'], $id, ucfirst($m['singular']) . ': ' . $label);
        forget_input();
        flash('success', ($existing ? 'Saved ' : 'Added ') . $m['singular'] . ' “' . $label . '”.');

        if ($key === 'employees') {
            redirect("employees/$id");
        }
        $back = input('return');
        if ($back !== '' && preg_match('#^employees/\d+$#', $back)) {
            redirect($back);
        }
        redirect($key);
    }

    public function delete(string $key, int $id): void
    {
        $m   = $this->module($key);
        Auth::require($m['perm'], 'edit');
        $row = DB::one("SELECT * FROM `{$m['table']}` WHERE id = ?", [$id]) ?? abort(404);

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        if ($m['table'] === 'employees') {
            foreach (['attendance', 'leave_requests', 'emp_documents'] as $t) {
                DB::run("DELETE FROM `$t` WHERE employee_id = ?", [$id]);
            }
        }
        DB::delete($m['table'], $id);
        $pdo->commit();

        $label = $this->title($m, $row);
        Activity::log('deleted', $m['perm'], $id, ucfirst($m['singular']) . ': ' . $label);
        flash('success', 'Deleted ' . $m['singular'] . ' “' . $label . '”.');
        $back = input('return');
        redirect($back !== '' && preg_match('#^employees/\d+$#', $back) ? $back : $key);
    }

    public function export(string $key): void
    {
        $m = $this->module($key);
        Auth::require($m['perm']);
        [$select, $from] = $this->source($m);
        $rows = DB::all("SELECT $select FROM $from ORDER BY {$m['sort']}");
        Activity::log('exported', $m['perm'], null, 'Exported ' . count($rows) . ' ' . strtolower($m['label']));

        $name = preg_replace('/[^a-z0-9]+/', '-', strtolower($m['label'])) . '-' . today() . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(fn ($f) => $f['label'], $m['fields']));
        foreach ($rows as $r) {
            $line = [];
            foreach ($m['fields'] as $col => $f) {
                $v = $f['type'] === 'employee' ? ($r['employee_name'] ?? '') : ($r[$col] ?? '');
                if ($f['type'] === 'select' && isset($f['options'][$v])) {
                    $v = $f['options'][$v];
                }
                $line[] = csv_safe($v);
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    // ---------------------------------------------------------------------

    private function module(string $key): array
    {
        return Modules::get($key) ?? abort(404);
    }

    private function source(array $m): array
    {
        if (Modules::hasEmployee($m)) {
            return ["t.*, e.name AS employee_name, e.department AS employee_department", "`{$m['table']}` t LEFT JOIN employees e ON e.id = t.employee_id"];
        }
        return ['t.*', "`{$m['table']}` t"];
    }

    private function filters(array $m): array
    {
        $where = [];
        $params = [];
        $f = [
            'q'      => mb_substr(input('q', '', 'get'), 0, 100),
            'status' => input('status', '', 'get'),
            'dept'   => input('dept', '', 'get'),
            'expiry' => input('expiry', '', 'get'),
            'type'   => input('type', '', 'get'),
        ];

        if ($f['q'] !== '') {
            $like = [];
            foreach ($m['fields'] as $col => $def) {
                if (!empty($def['search'])) {
                    $like[] = "t.`$col` LIKE ?";
                    $params[] = '%' . $f['q'] . '%';
                }
            }
            if (Modules::hasEmployee($m)) {
                $like[] = 'e.name LIKE ?';
                $params[] = '%' . $f['q'] . '%';
            }
            if ($like) {
                $where[] = '(' . implode(' OR ', $like) . ')';
            }
        }
        if ($f['status'] !== '' && isset($m['fields']['status']['options'][$f['status']])) {
            $where[] = 't.status = ?';
            $params[] = $f['status'];
        }
        if ($f['dept'] !== '' && in_array('department', $m['filters'], true)) {
            $where[] = Modules::hasEmployee($m) ? 'e.department = ?' : 't.department = ?';
            $params[] = $f['dept'];
        }
        if ($f['type'] !== '' && isset($m['fields']['asset_type']['options'][$f['type']])) {
            $where[] = 't.asset_type = ?';
            $params[] = $f['type'];
        }
        $expiryCols = array_keys(array_filter($m['fields'], fn ($d) => isset($d['expiry'])));
        if ($expiryCols && in_array($f['expiry'], ['due', 'expired'], true)) {
            $or = [];
            foreach ($expiryCols as $col) {
                if ($f['expiry'] === 'expired') {
                    $or[] = "t.`$col` < ?";
                    $params[] = today();
                } else {
                    $or[] = "t.`$col` BETWEEN ? AND ?";
                    $params[] = today();
                    $params[] = date('Y-m-d', strtotime('+' . (int) setting('alert_window', 30) . ' days'));
                }
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        return [$where, $params, $f];
    }

    private function summary(array $m): array
    {
        $t = $m['table'];
        $s = [['Total', (int) DB::val("SELECT COUNT(*) FROM `$t`"), 'neutral', []]];
        $expiryCols = array_keys(array_filter($m['fields'], fn ($d) => isset($d['expiry'])));
        if ($expiryCols) {
            $w = date('Y-m-d', strtotime('+' . (int) setting('alert_window', 30) . ' days'));
            $exp = implode(' OR ', array_map(fn ($c) => "`$c` < CURDATE()", $expiryCols));
            $due = implode(' OR ', array_map(fn ($c) => "`$c` BETWEEN CURDATE() AND ?", $expiryCols));
            $s[] = ['Expired', (int) DB::val("SELECT COUNT(*) FROM `$t` WHERE $exp"), 'danger', ['expiry' => 'expired']];
            $s[] = ['Due in ' . setting('alert_window', 30) . ' days', (int) DB::val("SELECT COUNT(*) FROM `$t` WHERE $due", array_fill(0, count($expiryCols), $w)), 'warn', ['expiry' => 'due']];
        }
        if (isset($m['fields']['status']['options'])) {
            $counts = [];
            foreach (DB::all("SELECT status, COUNT(*) c FROM `$t` GROUP BY status") as $r) {
                $counts[$r['status']] = (int) $r['c'];
            }
            foreach ($m['fields']['status']['options'] as $val => $label) {
                if (count($s) >= 5) {
                    break;
                }
                $s[] = [$label, $counts[$val] ?? 0, tone($val), ['status' => $val]];
            }
        }
        return $s;
    }

    private function validate(array $m): array
    {
        $data = [];
        $errors = [];
        foreach ($m['fields'] as $col => $f) {
            if (!empty($f['computed'])) {
                continue;
            }
            $v = input($col);
            $max = $f['type'] === 'textarea' ? 5000 : 255;
            if (mb_strlen($v) > $max) {
                $errors[] = "{$f['label']} is too long (maximum {$max} characters).";
                continue;
            }
            switch ($f['type']) {
                case 'email':
                    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Enter a valid email for {$f['label']}.";
                    }
                    break;
                case 'date':
                    if ($v !== '' && !valid_date($v)) {
                        $errors[] = "Enter a valid date for {$f['label']}.";
                    }
                    break;
                case 'time':
                    if ($v !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v)) {
                        $errors[] = "Enter a valid time for {$f['label']}.";
                    }
                    break;
                case 'number':
                    if ($v !== '' && !is_numeric($v)) {
                        $errors[] = "{$f['label']} must be a number.";
                    }
                    break;
                case 'select':
                    if (!isset($f['options'][$v])) {
                        $v = (string) ($f['default'] ?? array_key_first($f['options']));
                    }
                    break;
                case 'employee':
                    if ($v !== '' && !DB::val('SELECT id FROM employees WHERE id = ?', [(int) $v])) {
                        $errors[] = 'Choose an employee from the list.';
                    }
                    $v = $v === '' ? '' : (string) (int) $v;
                    break;
            }
            if (!empty($f['required']) && $v === '') {
                $errors[] = "{$f['label']} is required.";
            }
            $data[$col] = $v === '' ? null : $v;
        }
        return [$data, array_unique($errors)];
    }

    private function title(array $m, array $row): string
    {
        $t = (string) ($row[$m['title']] ?? '');
        if (!empty($row['employee_id'])) {
            $name = (string) DB::val('SELECT name FROM employees WHERE id = ?', [(int) $row['employee_id']]);
            $t = $name . ($t !== '' ? ' — ' . humanize($t) : '');
        }
        return $t !== '' ? $t : '#' . ($row['id'] ?? '');
    }
}
