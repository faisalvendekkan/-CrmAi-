<?php
declare(strict_types=1);

final class Seeder
{
    public static function tools(): void
    {
        if ((int) DB::val("SELECT COUNT(*) FROM tools") > 0) {
            return;
        }
        $tools = [
            ['ai', 'Claude', 'AI assistant', 'https://claude.ai/', 'Draft policies, letters, reports and analyse long documents.'],
            ['ai', 'ChatGPT', 'AI assistant', 'https://chatgpt.com/', 'Write HR communication, summaries and meeting notes.'],
            ['ai', 'Google Gemini', 'AI assistant', 'https://gemini.google.com/', 'Research and draft with your Google Workspace files.'],
            ['ai', 'Microsoft Copilot', 'AI assistant', 'https://copilot.microsoft.com/', 'AI help across Word, Excel, Outlook and Teams.'],
            ['ai', 'Perplexity', 'AI research', 'https://www.perplexity.ai/', 'Answers with sources for labour law and market research.'],
            ['app', 'Microsoft Outlook', 'Communication', 'https://outlook.office.com/', 'Company email and calendar.'],
            ['app', 'Microsoft Teams', 'Communication', 'https://teams.microsoft.com/', 'Chat, meetings and team channels.'],
            ['app', 'WhatsApp Web', 'Communication', 'https://web.whatsapp.com/', 'Message staff and vendors from the browser.'],
            ['app', 'OneDrive', 'Cloud storage', 'https://onedrive.live.com/', 'Store and share company files.'],
            ['app', 'Google Drive', 'Cloud storage', 'https://drive.google.com/', 'Shared folders and documents.'],
            ['app', 'Zoho WorkDrive', 'Cloud storage', 'https://workdrive.zoho.com/', 'Team folders with access control.'],
            ['app', 'Zoho People', 'HR system', 'https://people.zoho.com/', 'HR records and self-service.'],
            ['app', 'LinkedIn Recruiter', 'Recruitment', 'https://www.linkedin.com/talent/', 'Source and contact candidates.'],
            ['app', 'Hukoomi', 'Government', 'https://hukoomi.gov.qa/', 'Qatar government e-services portal.'],
            ['app', 'Metrash', 'Government', 'https://portal.moi.gov.qa/', 'Ministry of Interior services for residency and vehicles.'],
        ];
        foreach ($tools as $i => [$kind, $name, $cat, $url, $desc]) {
            DB::insert('tools', ['kind' => $kind, 'name' => $name, 'category' => $cat, 'url' => $url, 'description' => $desc, 'sort_order' => $i]);
        }
    }

    public static function sample(): void
    {
        if ((int) DB::val("SELECT COUNT(*) FROM employees") > 0) {
            return;
        }
        $d = fn (int $days) => date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));

        $employees = [
            ['EMP-001', 'Mohammed Shafi', 'Operations', 'Admin Executive', 'Indian', '+974 7000 1001', $d(-900), '28435612345', $d(210), 'P7824519', $d(640), $d(18), 'active'],
            ['EMP-002', 'Aisha Al-Kuwari', 'Human Resources', 'HR Manager', 'Qatari', '+974 7000 1002', $d(-1500), '28863400123', $d(420), 'Q1029384', $d(1200), null, 'active'],
            ['EMP-003', 'Rahul Menon', 'Finance', 'Accountant', 'Indian', '+974 7000 1003', $d(-700), '29135698765', $d(9), 'N4452190', $d(95), $d(9), 'active'],
            ['EMP-004', 'Fatima Hassan', 'Human Resources', 'Recruitment Officer', 'Egyptian', '+974 7000 1004', $d(-400), '29818400456', $d(160), 'A2398801', $d(900), $d(160), 'on_leave'],
            ['EMP-005', 'John Mathew', 'Logistics', 'Fleet Supervisor', 'Filipino', '+974 7000 1005', $d(-1100), '28560800778', $d(-4), 'P9981234', $d(300), $d(-4), 'active'],
            ['EMP-006', 'Omar Siddiqui', 'Sales', 'Sales Executive', 'Pakistani', '+974 7000 1006', $d(-250), '29058600342', $d(27), 'BK772019', $d(700), $d(27), 'active'],
            ['EMP-007', 'Priya Nair', 'Finance', 'Finance Officer', 'Indian', '+974 7000 1007', $d(-60), '29935600981', $d(320), 'R5561023', $d(1400), $d(320), 'active'],
            ['EMP-008', 'Ahmed Farouk', 'Operations', 'Driver', 'Sudanese', '+974 7000 1008', $d(-1800), '28173600560', $d(44), 'SD100293', $d(58), $d(44), 'active'],
        ];
        $ids = [];
        foreach ($employees as [$no, $name, $dept, $role, $nat, $phone, $join, $qid, $qidExp, $pp, $ppExp, $visa, $status]) {
            $ids[$name] = DB::insert('employees', [
                'employee_no' => $no, 'name' => $name, 'department' => $dept, 'designation' => $role, 'nationality' => $nat,
                'email' => strtolower(str_replace([' ', "'"], ['.', ''], $name)) . '@example.com', 'phone' => $phone, 'joining_date' => $join,
                'qid' => $qid, 'qid_expiry' => $qidExp, 'passport' => $pp, 'passport_expiry' => $ppExp, 'visa_expiry' => $visa, 'status' => $status,
            ]);
        }

        foreach ([
            ['Commercial registration', 'Commercial registration', 'PRO', 'CR-145872', $d(-300), $d(22)],
            ['Trade licence', 'Trade licence', 'PRO', 'TL-88213', $d(-340), $d(64)],
            ['Establishment card', 'Establishment card', 'Admin', 'EC-55190', $d(-200), $d(-6)],
            ['Group medical insurance', 'Insurance', 'Finance', 'QIC-GM-2291', $d(-330), $d(35)],
            ['Office tenancy contract', 'Tenancy contract', 'Admin', 'LEASE-C-12', $d(-500), $d(230)],
        ] as [$name, $cat, $owner, $ref, $issue, $exp]) {
            DB::insert('documents', ['name' => $name, 'category' => $cat, 'owner' => $owner, 'reference' => $ref, 'issue_date' => $issue, 'expiry_date' => $exp]);
        }

        foreach ([
            ['Mohammed Shafi', 'Health card', 'HC-110293', $d(12)],
            ['Rahul Menon', 'Health card', 'HC-110877', $d(140)],
            ['John Mathew', 'Driving licence', 'DL-7781203', $d(33)],
            ['Ahmed Farouk', 'Driving licence', 'DL-6620019', $d(-2)],
            ['Omar Siddiqui', 'Work permit', 'WP-2291823', $d(27)],
        ] as [$emp, $type, $ref, $exp]) {
            DB::insert('emp_documents', ['employee_id' => $ids[$emp], 'doc_type' => $type, 'reference' => $ref, 'expiry_date' => $exp]);
        }

        foreach ([
            ['Vehicle', 'Toyota Hilux 2023', '284551', 'White pickup', 'John Mathew', $d(16), $d(16), 'in_use'],
            ['Vehicle', 'Nissan Sunny 2022', '193002', 'Silver sedan', 'Ahmed Farouk', $d(88), $d(52), 'in_use'],
            ['Vehicle', 'Mitsubishi L300 2021', '410998', 'Delivery van', '', $d(-3), $d(40), 'maintenance'],
            ['Laptop', 'Dell Latitude 5440', 'DL5440-8821', '16 GB, i7', 'Aisha Al-Kuwari', null, $d(190), 'in_use'],
            ['Laptop', 'MacBook Air M3', 'C02XK1', '16 GB', 'Rahul Menon', null, $d(25), 'in_use'],
            ['Printer', 'HP LaserJet Pro M404', 'VNB3K29011', 'Admin office', '', null, $d(-20), 'available'],
            ['Phone', 'iPhone 15', 'F2LXQ8PD', 'Company line', 'Omar Siddiqui', null, $d(300), 'in_use'],
        ] as [$type, $item, $ref, $det, $to, $reg, $cov, $st]) {
            DB::insert('assets', ['asset_type' => $type, 'item' => $item, 'reference' => $ref, 'details' => $det, 'assigned_to' => $to, 'registration_expiry' => $reg, 'coverage_expiry' => $cov, 'status' => $st]);
        }

        foreach ([
            ['Submit visa renewal for Rahul Menon', 'Mohammed Shafi', 'high', $d(3), 'in_progress'],
            ['Renew commercial registration', 'PRO', 'high', $d(15), 'open'],
            ['Collect medical fitness results', 'Fatima Hassan', 'medium', $d(6), 'open'],
            ['Order office stationery', 'Mohammed Shafi', 'low', $d(10), 'open'],
            ['Service Mitsubishi L300', 'John Mathew', 'medium', $d(-1), 'open'],
            ['Update staff handbook', 'Aisha Al-Kuwari', 'medium', $d(-5), 'done'],
        ] as [$title, $owner, $pri, $dl, $st]) {
            DB::insert('tasks', ['title' => $title, 'owner' => $owner, 'priority' => $pri, 'deadline' => $dl, 'status' => $st]);
        }

        foreach ([
            ['Fatima Hassan', 'annual', $d(-3), $d(8), 'approved', 'Family visit'],
            ['Omar Siddiqui', 'annual', $d(20), $d(34), 'pending', 'Annual vacation'],
            ['Rahul Menon', 'sick', $d(-12), $d(-11), 'approved', 'Medical certificate provided'],
            ['Priya Nair', 'emergency', $d(2), $d(3), 'pending', 'Family matter'],
        ] as [$emp, $type, $from, $to, $st, $reason]) {
            DB::insert('leave_requests', [
                'employee_id' => $ids[$emp], 'leave_type' => $type, 'start_date' => $from, 'end_date' => $to,
                'days' => self::days($from, $to), 'status' => $st, 'reason' => $reason,
            ]);
        }

        $marks = [
            'Mohammed Shafi' => ['present', '07:52'], 'Aisha Al-Kuwari' => ['present', '08:01'], 'Rahul Menon' => ['late', '08:47'],
            'Fatima Hassan' => ['on_leave', null], 'John Mathew' => ['present', '06:58'], 'Omar Siddiqui' => ['remote', '08:10'],
        ];
        foreach ($marks as $emp => [$st, $in]) {
            DB::insert('attendance', ['employee_id' => $ids[$emp], 'work_date' => today(), 'status' => $st, 'check_in' => $in]);
        }

        DB::insert('candidates', [
            'name' => 'Sara Thomas', 'email' => 'sara.thomas@example.com', 'role' => 'HR Executive', 'experience' => 4,
            'keywords' => 'Recruitment, Payroll, Qatar Labour Law, Excel, Onboarding',
            'cv_text' => 'HR Executive with 4 years of experience in recruitment, onboarding and payroll processing in Doha. Strong knowledge of Qatar Labour Law, WPS and Excel reporting.',
            'score' => 100, 'matched' => json_encode(['Recruitment', 'Payroll', 'Qatar Labour Law', 'Excel', 'Onboarding']), 'missing' => '[]', 'status' => 'shortlisted',
        ]);
        DB::insert('candidates', [
            'name' => 'Imran Qureshi', 'email' => 'imran.q@example.com', 'role' => 'HR Executive', 'experience' => 2,
            'keywords' => 'Recruitment, Payroll, Qatar Labour Law, Excel, Onboarding',
            'cv_text' => 'Administrative assistant with 2 years experience handling recruitment schedules and employee files. Proficient in Excel.',
            'score' => 40, 'matched' => json_encode(['Recruitment', 'Excel']), 'missing' => json_encode(['Payroll', 'Qatar Labour Law', 'Onboarding']), 'status' => 'new',
        ]);
    }

    public static function days(string $from, string $to): float
    {
        if (!valid_date($from) || !valid_date($to) || $to < $from) {
            return 0;
        }
        return (float) ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1);
    }
}
