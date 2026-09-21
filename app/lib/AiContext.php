<?php
declare(strict_types=1);

/**
 * Builds the system prompt for the assistant: instructions plus a compact,
 * permission-filtered snapshot of the company's live data.
 */
final class AiContext
{
    public static function system(array $page = []): string
    {
        $u       = Auth::user();
        $company = (string) setting('company_name');
        $now     = date('l j F Y, H:i') . ' (' . date_default_timezone_get() . ')';
        $perms   = [];
        foreach (Auth::PERMISSIONS as $k => $label) {
            if ($k !== 'assistant' && can($k)) {
                $perms[] = $label . (can($k, 'edit') ? ' (can edit)' : ' (view only)');
            }
        }

        $permsList = $perms ? implode(', ', $perms) : 'the dashboard only';

        $prompt = <<<TXT
You are Meridian, the built-in assistant inside {$company}'s HR and administration app (Meridian HR). You help with anything the user asks:
- Questions about the company's live data shown below (employees, expiries, leave, attendance, documents, assets, tasks, candidates).
- HR and admin work: drafting letters, emails, memos, policies, job descriptions, warning letters, salary or experience certificates, interview questions, onboarding checklists.
- Qatar and GCC HR practice (Labour Law No. 14 of 2004, QID, visas, work permits, end-of-service gratuity, annual leave). Give practical guidance and say the user should confirm with the Ministry of Labour or a legal adviser for binding decisions.
- General questions, calculations, Excel formulas, translations (English, Arabic, Malayalam, Hindi and more) and writing help.

Rules:
- Today is {$now}. Current user: {$u['name']} ({$u['role']}). They can access: {$permsList}.
- Base company-specific answers only on the data snapshot. Never invent employees, dates or numbers. If something is not in the snapshot, say so and tell the user which page to check.
- You cannot change data. When the user wants to add or change something, tell them exactly where to do it (for example: Employees → open the person → Edit).
- Be concise and direct. Use short paragraphs, bullet lists and markdown tables when they help. Put dates in the format 12 Mar 2026.
- For drafted letters, write the full ready-to-use text with {$company} as the company and leave clear placeholders like [Date] only where information is missing.
TXT;

        if (setting('ai_share_data', '1') !== '1') {
            return $prompt . "\n\nThe administrator has disabled sharing company data with the assistant. Answer general questions only and explain this if the user asks about company records.";
        }

        return $prompt . "\n\n<company_data>\n" . self::snapshot($page) . "\n</company_data>";
    }

    public static function snapshot(array $page = []): string
    {
        $out = [];

        if (can('employees')) {
            $counts = DB::all("SELECT status, COUNT(*) c FROM employees GROUP BY status");
            $out[] = '## Employees: ' . implode(', ', array_map(fn ($r) => $r['c'] . ' ' . humanize($r['status']), $counts));
            $depts = DB::all("SELECT COALESCE(NULLIF(department,''),'No department') d, COUNT(*) c FROM employees WHERE status <> 'inactive' GROUP BY d ORDER BY c DESC");
            $out[] = 'Departments: ' . implode(', ', array_map(fn ($r) => "{$r['d']} ({$r['c']})", $depts));
            $rows = DB::all("SELECT id, name, employee_no, department, designation, nationality, joining_date, status, visa_expiry, qid_expiry, passport_expiry FROM employees ORDER BY name LIMIT 250");
            $out[] = "Employee directory (id | name | emp no | department | designation | nationality | joined | status | visa exp | QID exp | passport exp):";
            foreach ($rows as $r) {
                $out[] = implode(' | ', [$r['id'], $r['name'], $r['employee_no'] ?: '-', $r['department'] ?: '-', $r['designation'] ?: '-', $r['nationality'] ?: '-', $r['joining_date'] ?: '-', $r['status'], $r['visa_expiry'] ?: '-', $r['qid_expiry'] ?: '-', $r['passport_expiry'] ?: '-']);
            }
        }

        $alerts = Alerts::items(['window' => 60, 'include_expired' => true, 'categories' => array_keys(Alerts::CATEGORIES), 'limit' => 60, 'expired_days' => 180]);
        if ($alerts) {
            $out[] = "\n## Expiries and deadlines (expired in last 180 days and due within 60 days)";
            foreach ($alerts as $a) {
                $out[] = "- {$a['title']} — {$a['sub']} — {$a['date']} (" . ($a['days'] < 0 ? abs($a['days']) . ' days overdue' : "in {$a['days']} days") . ')';
            }
        }

        if (can('leave')) {
            $pending = DB::all("SELECT l.leave_type, l.start_date, l.end_date, l.days, e.name FROM leave_requests l LEFT JOIN employees e ON e.id = l.employee_id WHERE l.status = 'pending' ORDER BY l.start_date LIMIT 30");
            $out[] = "\n## Pending leave requests: " . count($pending);
            foreach ($pending as $r) {
                $out[] = "- {$r['name']}: {$r['leave_type']} {$r['start_date']} to {$r['end_date']} ({$r['days']} days)";
            }
            $onLeave = DB::all("SELECT e.name, l.leave_type, l.end_date FROM leave_requests l JOIN employees e ON e.id = l.employee_id WHERE l.status = 'approved' AND ? BETWEEN l.start_date AND l.end_date", [today()]);
            $out[] = 'On approved leave today: ' . ($onLeave ? implode(', ', array_map(fn ($r) => "{$r['name']} ({$r['leave_type']} until {$r['end_date']})", $onLeave)) : 'nobody');
            $out[] = 'Annual leave entitlement setting: ' . setting('annual_leave_days') . ' days per year.';
        }

        if (can('attendance')) {
            $att = DB::all("SELECT a.status, a.check_in, e.name FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.work_date = ?", [today()]);
            $active = (int) DB::val("SELECT COUNT(*) FROM employees WHERE status = 'active'");
            $by = [];
            foreach ($att as $a) {
                $by[$a['status']][] = $a['name'] . ($a['check_in'] ? ' ' . substr($a['check_in'], 0, 5) : '');
            }
            $out[] = "\n## Attendance today: " . count($att) . " of {$active} active employees marked";
            foreach ($by as $status => $names) {
                $out[] = '- ' . humanize($status) . ': ' . implode(', ', array_slice($names, 0, 40));
            }
        }

        if (can('tasks')) {
            $tasks = DB::all("SELECT title, owner, priority, deadline, status FROM tasks WHERE status <> 'done' ORDER BY FIELD(priority,'high','medium','low'), deadline IS NULL, deadline LIMIT 40");
            $out[] = "\n## Open admin tasks: " . count($tasks);
            foreach ($tasks as $t) {
                $out[] = "- [{$t['priority']}] {$t['title']} — " . ($t['owner'] ?: 'no owner') . ', due ' . ($t['deadline'] ?: 'no date') . ", {$t['status']}";
            }
        }

        if (can('documents')) {
            $n = (int) DB::val("SELECT COUNT(*) FROM documents");
            $out[] = "\n## Company documents: {$n} on file";
            foreach (DB::all("SELECT name, category, owner, reference, expiry_date FROM documents ORDER BY expiry_date IS NULL, expiry_date LIMIT 60") as $d) {
                $out[] = "- {$d['name']} ({$d['category']}) ref " . ($d['reference'] ?: '-') . ', responsible ' . ($d['owner'] ?: '-') . ', expires ' . ($d['expiry_date'] ?: '-');
            }
        }

        if (can('assets')) {
            $out[] = "\n## Vehicles and assets";
            foreach (DB::all("SELECT asset_type, item, reference, assigned_to, status, registration_expiry, coverage_expiry FROM assets ORDER BY asset_type, item LIMIT 80") as $a) {
                $out[] = "- {$a['asset_type']}: {$a['item']} (" . ($a['reference'] ?: '-') . ') assigned to ' . ($a['assigned_to'] ?: '-') . ", {$a['status']}, registration exp " . ($a['registration_expiry'] ?: '-') . ', insurance/warranty exp ' . ($a['coverage_expiry'] ?: '-');
            }
        }

        if (can('candidates')) {
            $c = DB::all("SELECT name, role, experience, score, ai_score, status FROM candidates ORDER BY created_at DESC LIMIT 25");
            if ($c) {
                $out[] = "\n## Recent candidates (CV screening)";
                foreach ($c as $r) {
                    $out[] = "- {$r['name']} for {$r['role']}: {$r['experience']} yrs, keyword score {$r['score']}%" . ($r['ai_score'] !== null ? ", AI score {$r['ai_score']}%" : '') . ", {$r['status']}";
                }
            }
        }

        // Page the user is looking at right now
        $module = (string) ($page['module'] ?? '');
        $id     = (int) ($page['record'] ?? 0);
        if ($module === 'employees' && $id && can('employees')) {
            $out[] = "\n## The user is viewing this employee file\n" . self::employeeFile($id);
        } elseif ($module === 'candidates' && $id && can('candidates')) {
            $c = DB::one("SELECT name, role, experience, keywords, matched, missing, score, status, LEFT(cv_text, 6000) cv FROM candidates WHERE id = ?", [$id]);
            if ($c) {
                $out[] = "\n## The user is viewing this candidate\nName: {$c['name']}\nRole: {$c['role']}\nExperience: {$c['experience']} years\nRequired keywords: {$c['keywords']}\nKeyword score: {$c['score']}%\nStatus: {$c['status']}\nCV text:\n{$c['cv']}";
            }
        } elseif ($module !== '') {
            $out[] = "\nThe user is currently on the \"" . humanize(str_replace(['/', '-'], ' ', $module)) . '" page.';
        }

        $text = implode("\n", $out);
        return mb_strlen($text) > 60000 ? mb_substr($text, 0, 60000) . "\n[snapshot truncated]" : $text;
    }

    public static function employeeFile(int $id): string
    {
        $e = DB::one("SELECT * FROM employees WHERE id = ?", [$id]);
        if (!$e) {
            return 'Not found.';
        }
        $lines = [];
        foreach (Modules::get('employees')['fields'] as $k => $f) {
            $lines[] = $f['label'] . ': ' . ($e[$k] ?? '' ?: '-');
        }
        if (can('emp_documents')) {
            foreach (DB::all("SELECT doc_type, reference, expiry_date FROM emp_documents WHERE employee_id = ?", [$id]) as $d) {
                $lines[] = "Document: {$d['doc_type']} ref " . ($d['reference'] ?: '-') . ', expires ' . ($d['expiry_date'] ?: '-');
            }
        }
        if (can('leave')) {
            $lines[] = 'Annual leave used this year: ' . self::leaveUsed($id) . ' of ' . setting('annual_leave_days') . ' days';
            foreach (DB::all("SELECT leave_type, start_date, end_date, days, status FROM leave_requests WHERE employee_id = ? ORDER BY start_date DESC LIMIT 10", [$id]) as $l) {
                $lines[] = "Leave: {$l['leave_type']} {$l['start_date']} to {$l['end_date']} ({$l['days']} d) {$l['status']}";
            }
        }
        if (can('assets')) {
            foreach (DB::all("SELECT asset_type, item, reference FROM assets WHERE assigned_to = ?", [$e['name']]) as $a) {
                $lines[] = "Assigned asset: {$a['asset_type']} {$a['item']} ({$a['reference']})";
            }
        }
        return implode("\n", $lines);
    }

    public static function leaveUsed(int $employeeId): float
    {
        return (float) DB::val(
            "SELECT COALESCE(SUM(days),0) FROM leave_requests WHERE employee_id = ? AND status = 'approved' AND leave_type = 'annual' AND YEAR(start_date) = YEAR(CURDATE())",
            [$employeeId]
        );
    }
}
