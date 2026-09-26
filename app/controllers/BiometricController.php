<?php
declare(strict_types=1);

/** Receives punch metadata from a trusted local ZKBio Time sync process. */
final class BiometricController
{
    public function transactions(): void
    {
        header('Cache-Control: no-store');
        if (!is_https()) {
            json_out(['ok' => false, 'error' => 'HTTPS is required.'], 426);
        }
        if (setting('biometric_enabled', '0') !== '1') {
            json_out(['ok' => false, 'error' => 'Biometric import is disabled.'], 403);
        }
        $hash = (string) setting('biometric_token_hash', '');
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if ($hash === '' || !preg_match('/^Bearer ([^\s]+)$/i', $auth, $m) || !password_verify($m[1], $hash)) {
            header('WWW-Authenticate: Bearer');
            json_out(['ok' => false, 'error' => 'Invalid integration token.'], 401);
        }

        $stream = fopen('php://input', 'rb');
        $raw = $stream ? stream_get_contents($stream, 131073) : false;
        if ($stream) fclose($stream);
        if ($raw === false || strlen($raw) > 131072) {
            json_out(['ok' => false, 'error' => 'Request is too large.'], 413);
        }
        $body = json_decode($raw, true);
        $items = $body['transactions'] ?? null;
        if (!is_array($items) || array_values($items) !== $items || count($items) < 1 || count($items) > 100) {
            json_out(['ok' => false, 'error' => 'Send 1 to 100 transactions.'], 422);
        }

        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                json_out(['ok' => false, 'error' => 'Invalid transaction.'], 422);
            }
            foreach (['id', 'emp_code', 'punch_time', 'punch_state', 'verify_type', 'terminal_sn'] as $field) {
                if (isset($item[$field]) && !is_scalar($item[$field])) {
                    json_out(['ok' => false, 'error' => 'Invalid transaction field.'], 422);
                }
            }
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $code = trim((string) ($item['emp_code'] ?? ''));
            $time = (string) ($item['punch_time'] ?? '');
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $time);
            if ($id === false || $code === '' || strlen($code) > 40 || !$parsed || $parsed->format('Y-m-d H:i:s') !== $time) {
                json_out(['ok' => false, 'error' => 'Transaction id, employee code or punch time is invalid.'], 422);
            }
            $state = (string) ($item['punch_state'] ?? '');
            $verify = (string) ($item['verify_type'] ?? '');
            $terminal = (string) ($item['terminal_sn'] ?? '');
            if (strlen($state) > 20 || strlen($verify) > 20 || strlen($terminal) > 40) {
                json_out(['ok' => false, 'error' => 'Transaction metadata is too long.'], 422);
            }
            $validated[] = [$id, $code, $time, $state, $verify, $terminal];
        }

        $new = 0;
        $duplicates = 0;
        $unmatched = [];
        $days = [];
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($validated as [$id, $code, $time, $state, $verify, $terminal]) {
                $matches = DB::all('SELECT id FROM employees WHERE employee_no = ? LIMIT 2', [$code]);
                $employeeId = count($matches) === 1 ? (int) $matches[0]['id'] : null;
                $existing = DB::one('SELECT employee_code, employee_id, punch_time FROM biometric_punches WHERE source_id = ? FOR UPDATE', [$id]);
                if ($existing) {
                    if ($existing['employee_code'] !== $code || $existing['punch_time'] !== $time) {
                        throw new DomainException('A source transaction ID was reused for different punch data.');
                    }
                    $duplicates++;
                    if ($employeeId !== null && $existing['employee_id'] === null) {
                        DB::run('UPDATE biometric_punches SET employee_id = ? WHERE source_id = ?', [$employeeId, $id]);
                        $days[$employeeId . ':' . substr($time, 0, 10)] = [$employeeId, substr($time, 0, 10)];
                    }
                } else {
                    DB::run('INSERT INTO biometric_punches (source_id, employee_code, employee_id, punch_time, punch_state, verify_type, terminal_sn) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$id, $code, $employeeId, $time, $state ?: null, $verify ?: null, $terminal ?: null]);
                    $new++;
                    if ($employeeId !== null) {
                        $days[$employeeId . ':' . substr($time, 0, 10)] = [$employeeId, substr($time, 0, 10)];
                    }
                }
                if ($employeeId === null) $unmatched[$code] = true;
            }
            foreach ($days as [$employeeId, $date]) {
                self::updateDay($employeeId, $date);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof DomainException) {
                json_out(['ok' => false, 'error' => $e->getMessage()], 409);
            }
            throw $e;
        }

        if ($new > 0) Activity::log('imported', 'attendance', null, "Imported {$new} biometric punches");
        json_out(['ok' => true, 'imported' => $new, 'duplicates' => $duplicates,
            'unmatched_codes' => array_slice(array_keys($unmatched), 0, 20),
            'unmatched_count' => count($unmatched)]);
    }

    /** Re-link punches after employee numbers have been corrected in Meridian HR. */
    public function reconcile(): void
    {
        Auth::require('attendance', 'edit');
        $linked = 0;
        $days = [];
        $cursor = 0;
        do {
            $rows = DB::all('SELECT id, employee_code, punch_time FROM biometric_punches WHERE employee_id IS NULL AND id > ? ORDER BY id LIMIT 1000', [$cursor]);
            foreach ($rows as $row) {
                $cursor = (int) $row['id'];
                $matches = DB::all('SELECT id FROM employees WHERE employee_no = ? LIMIT 2', [$row['employee_code']]);
                if (count($matches) !== 1) continue;
                $employeeId = (int) $matches[0]['id'];
                DB::run('UPDATE biometric_punches SET employee_id = ? WHERE id = ?', [$employeeId, $row['id']]);
                $date = substr($row['punch_time'], 0, 10);
                $days[$employeeId . ':' . $date] = [$employeeId, $date];
                $linked++;
            }
        } while (count($rows) === 1000);
        foreach ($days as [$employeeId, $date]) self::updateDay($employeeId, $date);
        Activity::log('reconciled', 'attendance', null, "Linked {$linked} biometric punches");
        flash('success', "Linked {$linked} punches. Update unmatched employee IDs and run again if needed.");
        redirect('attendance');
    }

    private static function updateDay(int $employeeId, string $date): void
    {
        $times = DB::one('SELECT MIN(punch_time) AS first_punch, MAX(punch_time) AS last_punch FROM biometric_punches WHERE employee_id = ? AND punch_time >= ? AND punch_time < DATE_ADD(?, INTERVAL 1 DAY)', [$employeeId, $date, $date]);
        if (!$times || $times['first_punch'] === null) return;
        $first = substr($times['first_punch'], 11, 8);
        $last = $times['last_punch'] === $times['first_punch'] ? null : substr($times['last_punch'], 11, 8);
        DB::run("INSERT INTO attendance (employee_id, work_date, status, check_in, check_out, notes)
                 VALUES (?, ?, 'present', ?, ?, 'Imported from ZKBio Time')
                 ON DUPLICATE KEY UPDATE
                   check_in = IF(status IN ('on_leave', 'remote'), check_in, VALUES(check_in)),
                   check_out = IF(status IN ('on_leave', 'remote'), check_out, VALUES(check_out)),
                   status = IF(status = 'absent', 'present', status)", [$employeeId, $date, $first, $last]);
    }
}
