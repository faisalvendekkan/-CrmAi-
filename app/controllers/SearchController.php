<?php
declare(strict_types=1);

/** Powers the command palette (Ctrl/⌘ K). */
final class SearchController
{
    public function search(): void
    {
        Auth::require();
        $q = mb_substr(input('q', '', 'get'), 0, 80);
        if (mb_strlen($q) < 2) {
            json_out(['ok' => true, 'results' => []]);
        }
        $like = '%' . $q . '%';
        $r = [];
        $add = function (string $group, string $title, string $sub, string $link) use (&$r) {
            $r[] = ['group' => $group, 'title' => $title, 'sub' => $sub, 'url' => url($link)];
        };

        if (can('employees')) {
            foreach (DB::all('SELECT id, name, department, designation FROM employees WHERE name LIKE ? OR employee_no LIKE ? OR qid LIKE ? OR passport LIKE ? OR email LIKE ? LIMIT 6', [$like, $like, $like, $like, $like]) as $x) {
                $add('Employees', $x['name'], trim(($x['designation'] ?? '') . ', ' . ($x['department'] ?? ''), ', '), 'employees/' . $x['id']);
            }
        }
        if (can('documents')) {
            foreach (DB::all('SELECT id, name, reference FROM documents WHERE name LIKE ? OR reference LIKE ? OR category LIKE ? LIMIT 4', [$like, $like, $like]) as $x) {
                $add('Company documents', $x['name'], (string) $x['reference'], 'documents/' . $x['id'] . '/edit');
            }
        }
        if (can('emp_documents')) {
            foreach (DB::all('SELECT t.id, t.doc_type, t.reference, e.name FROM emp_documents t LEFT JOIN employees e ON e.id = t.employee_id WHERE t.doc_type LIKE ? OR t.reference LIKE ? LIMIT 4', [$like, $like]) as $x) {
                $add('Employee documents', $x['doc_type'] . ' — ' . ($x['name'] ?? ''), (string) $x['reference'], 'emp-documents/' . $x['id'] . '/edit');
            }
        }
        if (can('assets')) {
            foreach (DB::all('SELECT id, item, reference, asset_type FROM assets WHERE item LIKE ? OR reference LIKE ? OR assigned_to LIKE ? LIMIT 5', [$like, $like, $like]) as $x) {
                $add('Vehicles & assets', $x['item'], $x['asset_type'] . ($x['reference'] ? ', ' . $x['reference'] : ''), 'assets/' . $x['id'] . '/edit');
            }
        }
        if (can('tasks')) {
            foreach (DB::all('SELECT id, title, status FROM tasks WHERE title LIKE ? OR owner LIKE ? LIMIT 4', [$like, $like]) as $x) {
                $add('Admin tasks', $x['title'], humanize($x['status']), 'tasks/' . $x['id'] . '/edit');
            }
        }
        if (can('candidates')) {
            foreach (DB::all('SELECT id, name, role, score FROM candidates WHERE name LIKE ? OR role LIKE ? LIMIT 4', [$like, $like]) as $x) {
                $add('Candidates', $x['name'], $x['role'] . ', ' . $x['score'] . '%', 'candidates/' . $x['id']);
            }
        }
        json_out(['ok' => true, 'results' => $r]);
    }
}
