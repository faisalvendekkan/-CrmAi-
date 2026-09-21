<?php
declare(strict_types=1);

/**
 * Remote MCP endpoint for Meridian HR.
 *
 * Read-only by design. Uses a dedicated bearer token configured by an admin
 * in Settings. Supports the current stateless MCP shape and a small legacy
 * compatibility surface for clients that still call initialize.
 */
final class McpController
{
    private const PROTOCOL = '2026-07-28';

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('MCP-Protocol-Version: ' . self::PROTOCOL);

        if (!is_https()) {
            $this->error(null, -32000, 'MCP requires HTTPS.', 426);
        }

        if (setting('mcp_enabled', '0') !== '1') {
            $this->error(null, -32001, 'MCP is disabled.', 403);
        }

        $this->authorize();

        $body = json_body();
        $id = $body['id'] ?? null;
        $method = (string) ($body['method'] ?? '');
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];

        $headerMethod = (string) ($_SERVER['HTTP_MCP_METHOD'] ?? '');
        if ($headerMethod !== '' && $method !== '' && $headerMethod !== $method) {
            $this->error($id, -32600, 'Mcp-Method header does not match the JSON-RPC method.', 400);
        }

        if (($body['jsonrpc'] ?? '') !== '2.0' || $method === '') {
            $this->error($id, -32600, 'Invalid JSON-RPC request.', 400);
        }

        switch ($method) {
            case 'server/discover':
                $this->result($id, [
                    'protocolVersion' => self::PROTOCOL,
                    'capabilities' => ['tools' => new stdClass()],
                    '_meta' => [
                        'io.modelcontextprotocol/serverInfo' => [
                            'name' => 'HrAdmin',
                            'version' => APP_VERSION,
                        ],
                    ],
                ]);
                break;

            case 'initialize':
                $this->result($id, [
                    'protocolVersion' => $params['protocolVersion'] ?? self::PROTOCOL,
                    'serverInfo' => ['name' => 'Meridian HR MCP', 'version' => APP_VERSION],
                    'capabilities' => ['tools' => new stdClass()],
                ]);
                break;

            case 'notifications/initialized':
                http_response_code(204);
                exit;

            case 'ping':
                $this->result($id, new stdClass());
                break;

            case 'tools/list':
                $this->result($id, [
                    'tools' => $this->tools(),
                    'ttlMs' => 300000,
                    'cacheScope' => 'private',
                ]);
                break;

            case 'tools/call':
                $name = (string) ($params['name'] ?? '');
                $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
                $headerName = (string) ($_SERVER['HTTP_MCP_NAME'] ?? '');
                if ($headerName !== '' && $name !== '' && $headerName !== $name) {
                    $this->error($id, -32600, 'Mcp-Name header does not match the requested tool.', 400);
                }
                $this->result($id, $this->callTool($name, $args));
                break;

            default:
                $this->error($id, -32601, 'Method not found.', 404);
        }
    }

    private function authorize(): void
    {
        $hash = (string) setting('mcp_token_hash', '');
        if ($hash === '') {
            $this->error(null, -32002, 'MCP authentication is not configured.', 401);
        }

        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            header('WWW-Authenticate: Bearer');
            $this->error(null, -32003, 'Bearer token required.', 401);
        }

        $token = trim($m[1]);
        if ($token === '' || !password_verify($token, $hash)) {
            usleep(250000);
            $this->error(null, -32004, 'Invalid MCP token.', 401);
        }
    }

    private function tools(): array
    {
        $readonly = [
            'readOnlyHint' => true,
            'destructiveHint' => false,
            'openWorldHint' => false,
            'idempotentHint' => true,
        ];

        return [
            [
                'name' => 'list_employees',
                'title' => 'List employees',
                'description' => 'Use this when the user asks to list, show or review employees or staff records in HrAdmin.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'status' => ['type' => 'string', 'enum' => ['active', 'on_leave', 'inactive']],
                        'department' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'employee_details',
                'title' => 'Employee details',
                'description' => 'Use this when the user asks for employee details. With an id it returns one full employee profile; without an id it returns employees.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'search_employees',
                'title' => 'Search employees',
                'description' => 'Search HrAdmin employee records by name, employee ID, department, designation, email, QID or passport number.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Employee search text.'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'get_employee',
                'title' => 'Get employee',
                'description' => 'Retrieve one HrAdmin employee profile by numeric employee record ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'attendance_summary',
                'title' => 'Attendance summary',
                'description' => 'Get HrAdmin attendance counts and employee attendance records for a date.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format. Defaults to today.'],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'pending_leave_requests',
                'title' => 'Pending leave requests',
                'description' => 'List pending employee leave requests in HrAdmin.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'expiry_alerts',
                'title' => 'Expiry alerts',
                'description' => 'List HrAdmin records that are expired or due soon, including employee and administration expiries.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'hr_dashboard_summary',
                'title' => 'HR dashboard summary',
                'description' => 'Get employee totals, pending leave, today attendance and expiry counts from HrAdmin.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new stdClass(),
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'search',
                'title' => 'Search HrAdmin',
                'description' => 'Search HrAdmin employee records for relevant people. This compatibility tool is useful for ChatGPT read and knowledge workflows.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query.'],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
                'outputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'results' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'string'],
                                    'title' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                    'metadata' => ['type' => 'object'],
                                ],
                                'required' => ['id', 'title', 'url'],
                                'additionalProperties' => true,
                            ],
                        ],
                    ],
                    'required' => ['results'],
                    'additionalProperties' => false,
                ],
                'annotations' => $readonly,
            ],
            [
                'name' => 'fetch',
                'title' => 'Fetch HrAdmin record',
                'description' => 'Fetch the complete HrAdmin employee record for an item returned by the search tool.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Search result id such as employee-12.'],
                    ],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'outputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'text' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                        'metadata' => ['type' => 'object'],
                    ],
                    'required' => ['id', 'title', 'text', 'url'],
                    'additionalProperties' => true,
                ],
                'annotations' => $readonly,
            ],
        ];
    }

    private function callTool(string $name, array $args): array
    {
        try {
            $data = match ($name) {
                'list_employees' => $this->listEmployees($args),
                'employee_details' => $this->employeeDetails($args),
                'search_employees' => $this->searchEmployees($args),
                'get_employee' => $this->getEmployee($args),
                'attendance_summary' => $this->attendanceSummary($args),
                'pending_leave_requests' => $this->pendingLeave($args),
                'expiry_alerts' => $this->expiryAlerts($args),
                'hr_dashboard_summary' => $this->dashboardSummary(),
                'search' => $this->compatSearch($args),
                'fetch' => $this->compatFetch($args),
                default => throw new InvalidArgumentException('Unknown tool: ' . $name),
            };

            if (in_array($name, ['search', 'fetch'], true)) {
                return [
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]],
                    'structuredContent' => $data,
                    'isError' => false,
                ];
            }

            return [
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
                'isError' => false,
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'content' => [['type' => 'text', 'text' => $e->getMessage()]],
                'structuredContent' => ['error' => $e->getMessage()],
                'isError' => true,
            ];
        }
    }

    private function compatSearch(array $args): array
    {
        $query = mb_substr(trim((string) ($args['query'] ?? '')), 0, 100);
        if ($query === '') throw new InvalidArgumentException('query is required.');

        $rows = $this->searchEmployees(['query' => $query, 'limit' => 50]);
        $base = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $results = array_map(static function ($r) use ($base) {
            $id = (int) $r['id'];
            $sub = array_filter([$r['employee_no'] ?? '', $r['department'] ?? '', $r['designation'] ?? '']);
            return [
                'id' => 'employee-' . $id,
                'title' => (string) $r['name'],
                'url' => $base . url('employees/' . $id),
                'metadata' => [
                    'type' => 'employee',
                    'employee_id' => $id,
                    'summary' => implode(' · ', $sub),
                    'status' => (string) ($r['status'] ?? ''),
                ],
            ];
        }, $rows);

        return ['results' => $results];
    }

    private function compatFetch(array $args): array
    {
        $raw = trim((string) ($args['id'] ?? ''));
        if (!preg_match('/^employee-(\d+)$/', $raw, $m)) {
            throw new InvalidArgumentException('Use an employee result id returned by search, for example employee-12.');
        }

        $employee = $this->getEmployee(['id' => (int) $m[1]]);
        $base = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return [
            'id' => $raw,
            'title' => (string) ($employee['name'] ?? 'Employee'),
            'text' => json_encode($employee, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'url' => $base . url('employees/' . (int) $m[1]),
            'metadata' => [
                'type' => 'employee',
                'department' => (string) ($employee['department'] ?? ''),
                'designation' => (string) ($employee['designation'] ?? ''),
                'status' => (string) ($employee['status'] ?? ''),
            ],
        ];
    }

    private function listEmployees(array $args): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $where = [];
        $params = [];

        $status = trim((string) ($args['status'] ?? ''));
        if ($status !== '') {
            if (!in_array($status, ['active', 'on_leave', 'inactive'], true)) {
                throw new InvalidArgumentException('status must be active, on_leave or inactive.');
            }
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $department = mb_substr(trim((string) ($args['department'] ?? '')), 0, 100);
        if ($department !== '') {
            $where[] = 'department = ?';
            $params[] = $department;
        }

        $sql = "SELECT id, name, employee_no, department, designation, nationality, email, phone, joining_date,
                       qid_expiry, passport_expiry, visa_expiry, status
                FROM employees";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY status = 'inactive', name LIMIT {$limit}";
        return DB::all($sql, $params);
    }

    private function employeeDetails(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id > 0) {
            return $this->getEmployee(['id' => $id]);
        }
        return $this->listEmployees(['limit' => $args['limit'] ?? 50]);
    }

    private function searchEmployees(array $args): array
    {
        $q = mb_substr(trim((string) ($args['query'] ?? '')), 0, 100);
        if ($q === '') throw new InvalidArgumentException('query is required.');
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        $like = '%' . $q . '%';

        return DB::all(
            "SELECT id, name, employee_no, department, designation, nationality, email, phone, joining_date, qid_expiry, passport_expiry, visa_expiry, status
             FROM employees
             WHERE name LIKE ? OR employee_no LIKE ? OR department LIKE ? OR designation LIKE ? OR email LIKE ? OR qid LIKE ? OR passport LIKE ?
             ORDER BY status = 'inactive', name
             LIMIT {$limit}",
            array_fill(0, 7, $like)
        );
    }

    private function getEmployee(array $args): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) throw new InvalidArgumentException('A valid employee id is required.');

        $employee = DB::one(
            "SELECT id, name, employee_no, department, designation, nationality, email, phone, joining_date,
                    qid, qid_expiry, passport, passport_expiry, visa_expiry, status, notes
             FROM employees WHERE id = ?",
            [$id]
        );
        if (!$employee) throw new InvalidArgumentException('Employee not found.');

        $employee['documents'] = DB::all(
            "SELECT doc_type, reference, issue_date, expiry_date, notes FROM emp_documents WHERE employee_id = ? ORDER BY expiry_date IS NULL, expiry_date",
            [$id]
        );
        $employee['recent_attendance'] = DB::all(
            "SELECT work_date, check_in, check_out, status, notes FROM attendance WHERE employee_id = ? ORDER BY work_date DESC LIMIT 10",
            [$id]
        );
        $employee['leave'] = DB::all(
            "SELECT leave_type, start_date, end_date, days, status, reason FROM leave_requests WHERE employee_id = ? ORDER BY start_date DESC LIMIT 10",
            [$id]
        );
        return $employee;
    }

    private function attendanceSummary(array $args): array
    {
        $date = trim((string) ($args['date'] ?? today()));
        if (!valid_date($date)) throw new InvalidArgumentException('date must be YYYY-MM-DD.');

        $rows = DB::all(
            "SELECT e.id, e.name, e.department, a.status, a.check_in, a.check_out
             FROM employees e
             LEFT JOIN attendance a ON a.employee_id = e.id AND a.work_date = ?
             WHERE e.status <> 'inactive'
             ORDER BY e.department, e.name",
            [$date]
        );

        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'remote' => 0, 'unmarked' => 0];
        foreach ($rows as $r) {
            $k = $r['status'] ?: 'unmarked';
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        return ['date' => $date, 'counts' => $counts, 'employees' => $rows];
    }

    private function pendingLeave(array $args): array
    {
        $limit = max(1, min(50, (int) ($args['limit'] ?? 20)));
        return DB::all(
            "SELECT l.id, e.name AS employee, e.department, l.leave_type, l.start_date, l.end_date, l.days, l.reason
             FROM leave_requests l
             JOIN employees e ON e.id = l.employee_id
             WHERE l.status = 'pending'
             ORDER BY l.start_date ASC
             LIMIT {$limit}"
        );
    }

    private function expiryAlerts(array $args): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 30)));
        return array_slice(Alerts::items(['check_perms' => false]), 0, $limit);
    }

    private function dashboardSummary(): array
    {
        $today = today();
        return [
            'employees' => [
                'total' => (int) DB::val("SELECT COUNT(*) FROM employees"),
                'active' => (int) DB::val("SELECT COUNT(*) FROM employees WHERE status = 'active'"),
                'on_leave' => (int) DB::val("SELECT COUNT(*) FROM employees WHERE status = 'on_leave'"),
                'inactive' => (int) DB::val("SELECT COUNT(*) FROM employees WHERE status = 'inactive'"),
            ],
            'pending_leave_requests' => (int) DB::val("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'"),
            'attendance_today' => [
                'present' => (int) DB::val("SELECT COUNT(*) FROM attendance WHERE work_date = ? AND status = 'present'", [$today]),
                'late' => (int) DB::val("SELECT COUNT(*) FROM attendance WHERE work_date = ? AND status = 'late'", [$today]),
                'absent' => (int) DB::val("SELECT COUNT(*) FROM attendance WHERE work_date = ? AND status = 'absent'", [$today]),
                'remote' => (int) DB::val("SELECT COUNT(*) FROM attendance WHERE work_date = ? AND status = 'remote'", [$today]),
            ],
            'expiry_alert_count' => count(Alerts::items(['check_perms' => false])),
            'date' => $today,
        ];
    }

    private function result(mixed $id, mixed $result): never
    {
        echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function error(mixed $id, int $code, string $message, int $http = 400): never
    {
        http_response_code($http);
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
