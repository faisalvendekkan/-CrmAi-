<?php
declare(strict_types=1);

final class SettingsController
{
    public function index(): void
    {
        Auth::require('admin');
        $key = (string) setting('ai_key', '');
        $plain = $key !== '' ? Crypto::decrypt($key) : '';
        view('settings/index', [
            'title'    => 'Settings',
            'keyHint'  => $plain !== '' ? '•••• ' . mb_substr($plain, -4) : '',
            'dataDir'  => Config::dataDir(),
            'outside'  => Config::dataDir() ? Config::isOutsideWebroot((string) Config::dataDir()) : false,
            'schema'   => (int) setting('schema_version', 0),
            'aiUsage'  => (int) DB::val('SELECT COUNT(*) FROM ai_usage WHERE created_at >= ?', [date('Y-m-01')]),
            'biometricPunches' => (int) DB::val('SELECT COUNT(*) FROM biometric_punches'),
            'biometricUnmatched' => (int) DB::val('SELECT COUNT(*) FROM biometric_punches WHERE employee_id IS NULL'),
            'pageModule' => 'settings',
        ]);
    }

    public function save(): void
    {
        Auth::require('admin');
        $section = input('section');

        if ($section === 'general') {
            $company = mb_substr(input('company_name'), 0, 120);
            $tz = input('timezone');
            if (mb_strlen($company) < 2) {
                flash('error', 'Enter the company name.');
                redirect('settings');
            }
            Settings::many([
                'company_name'      => $company,
                'timezone'          => in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Asia/Qatar',
                'annual_leave_days' => (string) max(0, min(60, (int) input('annual_leave_days', '21'))),
                'session_minutes'   => (string) max(15, min(720, (int) input('session_minutes', '120'))),
            ]);
            Activity::log('updated', 'settings', null, 'Updated general settings');
            flash('success', 'General settings saved.');
        }

        if ($section === 'ai') {
            $provider = array_key_exists(input('ai_provider'), AI::PROVIDERS) ? input('ai_provider') : 'anthropic';
            $pairs = [
                'ai_provider'   => $provider,
                'ai_model'      => trim(mb_substr(input('ai_model'), 0, 100)),
                'ai_enabled'    => empty($_POST['ai_enabled']) ? '0' : '1',
                'ai_share_data' => empty($_POST['ai_share_data']) ? '0' : '1',
            ];
            if (!AI::validModelId($provider, $pairs['ai_model'])) {
                $pairs['ai_model'] = '';
                flash('error', 'The model name was invalid, so the provider default will be used. Fetch models and choose one from the list.');
            }
            $newKey = trim((string) ($_POST['ai_key'] ?? ''));
            if ($newKey !== '') {
                $pairs['ai_key'] = Crypto::encrypt(mb_substr($newKey, 0, 400));
            }
            if (!empty($_POST['ai_key_remove'])) {
                $pairs['ai_key'] = '';
                $pairs['ai_enabled'] = '0';
            }
            if ($pairs['ai_enabled'] === '1' && $newKey === '' && setting('ai_key', '') === '' ) {
                $pairs['ai_enabled'] = '0';
                flash('error', 'Add an API key before turning on the assistant.');
            }
            Settings::many($pairs);
            Activity::log('updated', 'settings', null, 'Updated AI settings' . ($newKey !== '' ? ' (new API key)' : ''));
            flash('success', 'AI settings saved.');
        }

        if ($section === 'mcp') {
            $enabled = empty($_POST['mcp_enabled']) ? '0' : '1';
            $newToken = trim((string) ($_POST['mcp_token'] ?? ''));
            $remove = !empty($_POST['mcp_token_remove']);

            $pairs = ['mcp_enabled' => $enabled];

            if ($remove) {
                $pairs['mcp_token_hash'] = '';
                $pairs['mcp_enabled'] = '0';
            } elseif ($newToken !== '') {
                if (strlen($newToken) < 32) {
                    flash('error', 'Use an MCP bearer token with at least 32 characters.');
                    redirect('settings#mcp');
                }
                $pairs['mcp_token_hash'] = password_hash($newToken, Auth::algo());
            } elseif ($enabled === '1' && setting('mcp_token_hash', '') === '') {
                $pairs['mcp_enabled'] = '0';
                flash('error', 'Add an MCP bearer token before enabling the MCP server.');
            }

            Settings::many($pairs);
            Activity::log('updated', 'settings', null, 'Updated MCP server settings' . ($newToken !== '' ? ' (new token)' : ''));
            flash('success', 'MCP settings saved.');
        }

        if ($section === 'biometric') {
            $enabled = empty($_POST['biometric_enabled']) ? '0' : '1';
            $newToken = trim((string) ($_POST['biometric_token'] ?? ''));
            $remove = !empty($_POST['biometric_token_remove']);
            $pairs = ['biometric_enabled' => $enabled];
            if ($remove) {
                $pairs['biometric_token_hash'] = '';
                $pairs['biometric_enabled'] = '0';
            } elseif ($newToken !== '') {
                if (strlen($newToken) < 32 || strlen($newToken) > 200) {
                    flash('error', 'Use an integration token with 32 to 200 characters.');
                    redirect('settings#biometric');
                }
                $pairs['biometric_token_hash'] = password_hash($newToken, Auth::algo());
            } elseif ($enabled === '1' && setting('biometric_token_hash', '') === '') {
                $pairs['biometric_enabled'] = '0';
                flash('error', 'Add an integration token before enabling biometric import.');
            }
            Settings::many($pairs);
            Activity::log('updated', 'settings', null, 'Updated biometric import settings' . ($newToken !== '' ? ' (new token)' : ''));
            flash('success', 'Biometric import settings saved.');
        }

        redirect('settings');
    }

    public function aiModels(): void
    {
        Auth::require('admin');
        $body = json_body();
        $provider = (string) ($body['provider'] ?? AI::provider());
        if (!array_key_exists($provider, AI::PROVIDERS)) {
            json_out(['ok' => false, 'error' => 'Choose a valid AI provider.'], 422);
        }
        $key = trim((string) ($body['key'] ?? ''));
        try {
            $models = AI::models($provider, $key !== '' ? $key : null);
            json_out([
                'ok' => true,
                'models' => $models,
                'default' => AI::PROVIDERS[$provider]['model'],
                'provider' => AI::PROVIDERS[$provider]['label'],
            ]);
        } catch (RuntimeException $e) {
            json_out(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function aiTest(): void
    {
        Auth::require('admin');
        $body = json_body();
        $provider = (string) ($body['provider'] ?? AI::provider());
        if (!array_key_exists($provider, AI::PROVIDERS)) {
            json_out(['ok' => false, 'error' => 'Choose a valid AI provider.'], 422);
        }
        $model = trim((string) ($body['model'] ?? ''));
        if ($model === '') {
            $model = AI::PROVIDERS[$provider]['model'];
        }
        if (!AI::validModelId($provider, $model)) {
            json_out(['ok' => false, 'error' => 'Choose a valid model from the fetched model list.'], 422);
        }
        $key = trim((string) ($body['key'] ?? ''));
        if ($key === '') {
            $saved = (string) setting('ai_key', '');
            $key = $saved !== '' ? Crypto::decrypt($saved) : '';
        }
        try {
            AI::complete(
                'Reply with exactly: OK',
                [['role' => 'user', 'content' => 'Connection check']],
                16,
                ['provider' => $provider, 'model' => $model, 'key' => $key]
            );
            json_out([
                'ok' => true,
                'provider' => AI::PROVIDERS[$provider]['label'],
                'model' => $model,
            ]);
        } catch (RuntimeException $e) {
            json_out(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function backup(): void
    {
        Auth::require('admin');
        $tables = ['employees', 'documents', 'emp_documents', 'assets', 'tasks', 'leave_requests', 'attendance', 'biometric_punches', 'candidates', 'tools'];
        $out = ['app' => APP_NAME, 'version' => APP_VERSION, 'exported_at' => date('c'), 'company' => setting('company_name')];
        foreach ($tables as $t) {
            $out[$t] = DB::all("SELECT * FROM `$t`");
        }
        $out['users'] = DB::all('SELECT id, name, email, role, status, permissions, last_login_at, created_at FROM users');
        Activity::log('exported', 'settings', null, 'Downloaded full JSON backup');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="meridian-backup-' . today() . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function activity(): void
    {
        Auth::require('admin');
        $page = max(1, (int) input('page', '1', 'get'));
        $rows = DB::all('SELECT a.*, u.name AS user_name FROM activity a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 100 OFFSET ' . (($page - 1) * 100));
        $total = (int) DB::val('SELECT COUNT(*) FROM activity');
        view('settings/activity', ['title' => 'Activity log', 'rows' => $rows, 'page' => $page, 'pages' => max(1, (int) ceil($total / 100)), 'pageModule' => 'activity']);
    }
}
