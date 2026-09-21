<?php
declare(strict_types=1);

final class CandidateController
{
    public const STATUSES = ['new' => 'New', 'shortlisted' => 'Shortlisted', 'interview' => 'Interview', 'hired' => 'Hired', 'rejected' => 'Rejected'];

    public function index(): void
    {
        Auth::require('candidates');
        $q = mb_substr(input('q', '', 'get'), 0, 100);
        $status = input('status', '', 'get');
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(name LIKE ? OR role LIKE ? OR keywords LIKE ? OR email LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        if (isset(self::STATUSES[$status])) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $rows = DB::all('SELECT id, name, email, role, experience, score, ai_score, matched, missing, status, created_at FROM candidates' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 200', $params);
        $stats = DB::one("SELECT COUNT(*) total, SUM(score >= 75) strong, SUM(status = 'shortlisted' OR status = 'interview') shortlisted, ROUND(AVG(score)) avg FROM candidates");

        view('candidates/index', [
            'title'  => 'CV screening',
            'rows'   => $rows,
            'stats'  => $stats,
            'q'      => $q,
            'status' => $status,
            'statuses' => self::STATUSES,
            'pageModule' => 'candidates',
        ]);
    }

    public function create(): void
    {
        Auth::require('candidates', 'edit');
        view('candidates/form', ['title' => 'Screen a CV', 'roles' => array_column(DB::all('SELECT DISTINCT role, keywords FROM candidates ORDER BY created_at DESC LIMIT 20'), 'keywords', 'role'), 'pageModule' => 'candidates']);
    }

    public function store(): void
    {
        $u = Auth::require('candidates', 'edit');
        $d = [
            'name'       => input('name'),
            'email'      => input('email'),
            'phone'      => input('phone'),
            'role'       => input('role'),
            'experience' => (float) input('experience', '0'),
            'keywords'   => input('keywords'),
            'cv_text'    => mb_substr(trim((string) ($_POST['cv_text'] ?? '')), 0, 200000),
            'file_name'  => mb_substr(input('file_name'), 0, 190),
        ];
        $errors = [];
        if (mb_strlen($d['name']) < 2) $errors[] = 'Enter the candidate name.';
        if ($d['role'] === '') $errors[] = 'Enter the role applied for.';
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
        if (!self::keywords($d['keywords'])) $errors[] = 'Add at least one required keyword.';
        if (mb_strlen($d['cv_text']) < 30) $errors[] = 'Upload a CV or paste the CV text.';
        if ($errors) {
            remember_input($_POST);
            flash('error', implode(' ', $errors));
            redirect('candidates/new');
        }

        [$score, $matched, $missing] = self::score($d['cv_text'], $d['keywords']);
        $d += ['score' => $score, 'matched' => json_encode($matched, JSON_UNESCAPED_UNICODE), 'missing' => json_encode($missing, JSON_UNESCAPED_UNICODE), 'status' => 'new', 'created_by' => $u['id']];
        $d['experience'] = max(0, min(60, $d['experience']));
        $id = DB::insert('candidates', $d);
        Activity::log('created', 'candidates', $id, "Screened {$d['name']} for {$d['role']} ({$score}%)");
        forget_input();

        if (!empty($_POST['run_ai']) && AI::configured() && can('assistant')) {
            try {
                $this->runAnalysis($id);
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
        }
        flash('success', "Screened {$d['name']}: {$score}% keyword match.");
        redirect("candidates/$id");
    }

    public function show(int $id): void
    {
        Auth::require('candidates');
        $c = DB::one('SELECT * FROM candidates WHERE id = ?', [$id]) ?? abort(404);
        view('candidates/show', ['title' => $c['name'], 'c' => $c, 'statuses' => self::STATUSES, 'pageModule' => 'candidates', 'pageRecord' => $id]);
    }

    public function status(int $id): void
    {
        Auth::require('candidates', 'edit');
        $c = DB::one('SELECT name FROM candidates WHERE id = ?', [$id]) ?? abort(404);
        $s = input('status');
        if (!isset(self::STATUSES[$s])) abort(422);
        DB::run('UPDATE candidates SET status = ? WHERE id = ?', [$s, $id]);
        Activity::log('updated', 'candidates', $id, "{$c['name']} moved to " . self::STATUSES[$s]);
        flash('success', "{$c['name']} moved to " . self::STATUSES[$s] . '.');
        redirect("candidates/$id");
    }

    public function analyse(int $id): void
    {
        Auth::require('candidates', 'edit');
        if (!can('assistant')) abort(403);
        try {
            $this->runAnalysis($id);
            flash('success', 'AI review added.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect("candidates/$id");
    }

    public function delete(int $id): void
    {
        Auth::require('candidates', 'edit');
        $c = DB::one('SELECT name FROM candidates WHERE id = ?', [$id]) ?? abort(404);
        DB::delete('candidates', $id);
        Activity::log('deleted', 'candidates', $id, "Deleted candidate {$c['name']}");
        flash('success', "Deleted candidate {$c['name']}.");
        redirect('candidates');
    }

    private function runAnalysis(int $id): void
    {
        $u = Auth::user();
        if (!RateLimit::aiAllowed((int) $u['id'])) {
            throw new RuntimeException('AI request limit reached. Try again in a few minutes.');
        }
        $c = DB::one('SELECT * FROM candidates WHERE id = ?', [$id]) ?? abort(404);
        $system = 'You are an experienced recruiter in Qatar screening CVs for ' . setting('company_name') . '. Be fair, specific and evidence-based. Judge only job-relevant qualifications; ignore age, nationality, religion, gender and marital status.';
        $prompt = "Role: {$c['role']}\nRequired keywords: {$c['keywords']}\nStated experience: {$c['experience']} years\n\nCV text:\n" . mb_substr((string) $c['cv_text'], 0, 30000) .
            "\n\nRespond in markdown with exactly these sections:\n**Fit score:** a number 0-100 on the first line, formatted as `Fit score: NN`\n### Summary\n2-3 sentences.\n### Strengths\nBullets.\n### Gaps and risks\nBullets.\n### Interview questions\n4 targeted questions.\n### Recommendation\nOne of: Shortlist, Consider, Reject — with one sentence why.";
        $reply = AI::complete($system, [['role' => 'user', 'content' => $prompt]], 1400);
        RateLimit::recordAi((int) $u['id'], 'cv');
        $aiScore = preg_match('/Fit score:\**\s*(\d{1,3})/i', $reply, $m) ? min(100, (int) $m[1]) : null;
        DB::run('UPDATE candidates SET ai_summary = ?, ai_score = ? WHERE id = ?', [$reply, $aiScore, $id]);
        Activity::log('ai_review', 'candidates', $id, "AI review for {$c['name']}");
    }

    public static function keywords(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,\n;]+/', $raw) ?: [] as $k) {
            $k = trim($k);
            if ($k !== '' && !in_array(mb_strtolower($k), array_map('mb_strtolower', $out), true)) {
                $out[] = mb_substr($k, 0, 60);
            }
        }
        return array_slice($out, 0, 40);
    }

    /** @return array{0:int,1:array,2:array} */
    public static function score(string $text, string $rawKeywords): array
    {
        $hay = ' ' . preg_replace('/\s+/u', ' ', mb_strtolower($text)) . ' ';
        $matched = $missing = [];
        foreach (self::keywords($rawKeywords) as $k) {
            $needle = preg_quote(mb_strtolower($k), '/');
            preg_match('/(?<![\p{L}\p{N}])' . $needle . '(?![\p{L}\p{N}])/u', $hay) ? $matched[] = $k : $missing[] = $k;
        }
        $total = count($matched) + count($missing);
        return [$total ? (int) round(count($matched) / $total * 100) : 0, $matched, $missing];
    }
}
