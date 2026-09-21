<?php
declare(strict_types=1);

/**
 * First-run setup wizard. Only reachable while no private config exists.
 * Once finished, App::run() returns 404 for every /setup URL — permanently.
 */
final class SetupController
{
    public function index(): void
    {
        view('setup/index', ['title' => 'Setup', 'checks' => $this->checks()], 'layouts/bare');
    }

    public function checkDatabase(): void
    {
        $this->throttle();
        $db = $this->dbInput(json_body());
        try {
            $pdo = DB::connect($db);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $existing = $this->existingInstall($pdo);
            json_out(['ok' => true, 'version' => $version, 'existing' => $existing]);
        } catch (PDOException $e) {
            json_out(['ok' => false, 'error' => $this->dbError($e)]);
        }
    }

    public function install(): void
    {
        $this->throttle();
        $checks = $this->checks();
        if (in_array(false, array_column($checks, 'ok'), true)) {
            $this->fail('Fix the items marked in the server check first.');
        }
        $dir = Config::writableDir();
        if (!$dir) {
            $this->fail('No private folder is writable. Create a folder named "meridian-data" next to public_html in the File Manager, then try again.');
        }

        $lock = fopen($dir . '/.install.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $this->fail('Setup is already running in another window.');
        }

        $db = $this->dbInput($_POST);
        try {
            $pdo = DB::connect($db);
        } catch (PDOException $e) {
            $this->fail($this->dbError($e));
        }

        $existing = $this->existingInstall($pdo);
        $company  = input('company_name');
        $name     = input('admin_name');
        $email    = mb_strtolower(input('admin_email'));
        $password = (string) ($_POST['admin_password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Enter a valid administrator email address.');
        }

        if ($existing) {
            // Reconnecting to an existing database (for example after the config file was lost):
            // prove ownership with an existing administrator account.
            $admin = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
            $admin->execute([$email]);
            $row = $admin->fetch();
            if (!$row || !password_verify($password, $row['password_hash'])) {
                usleep(random_int(400000, 900000));
                $this->fail('This database already has Meridian HR data. Sign in with an existing administrator email and password to reconnect it.');
            }
        } else {
            if (mb_strlen($company) < 2 || mb_strlen($name) < 2) {
                $this->fail('Enter the company name and your full name.');
            }
            if ($problem = Auth::passwordProblem($password, $email)) {
                $this->fail($problem);
            }
            if (!hash_equals($password, (string) ($_POST['admin_password_confirm'] ?? ''))) {
                $this->fail('The two passwords do not match.');
            }
        }

        $config = [
            'db'           => $db,
            'app_key'      => base64_encode(random_bytes(32)),
            'installed_at' => date('c'),
            'version'      => APP_VERSION,
        ];
        Config::setRuntime($config);
        DB::use($pdo);

        Migrator::migrate();
        Seeder::tools();

        if (!$existing) {
            DB::insert('users', [
                'name'          => $name,
                'email'         => $email,
                'password_hash' => Auth::hash($password),
                'role'          => 'admin',
                'permissions'   => '{}',
                'status'        => 'active',
                'is_owner'      => 1,
            ]);
            Settings::many([
                'company_name' => $company,
                'timezone'     => in_array(input('timezone'), timezone_identifiers_list(), true) ? input('timezone') : 'Asia/Qatar',
            ]);
            if (!empty($_POST['sample_data'])) {
                Seeder::sample();
            }
        } else {
            // Secrets encrypted with the lost app key can no longer be read.
            Settings::set('ai_key', '');
            Settings::set('ai_enabled', '0');
        }

        $aiKey = trim((string) ($_POST['ai_key'] ?? ''));
        if ($aiKey !== '') {
            $provider = array_key_exists(input('ai_provider'), AI::PROVIDERS) ? input('ai_provider') : 'anthropic';
            Settings::many([
                'ai_provider' => $provider,
                'ai_model'    => mb_substr(input('ai_model'), 0, 80),
                'ai_key'      => Crypto::encrypt($aiKey),
                'ai_enabled'  => '1',
            ]);
        }

        Config::write($config, $dir);
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($dir . '/.install.lock');
        Session::relocate();

        $result = Auth::attempt($email, $password);
        Activity::log('installed', 'setup', null, $existing ? 'Reconnected existing database' : 'Completed first-time setup');
        flash('success', $existing ? 'Database reconnected. Welcome back.' : 'Setup is complete. Your workspace is ready.');
        if ($result !== 'ok') {
            redirect('login');
        }
        redirect('');
    }

    private function checks(): array
    {
        $dir = Config::writableDir();
        return [
            ['label' => 'PHP 8.0 or newer', 'detail' => 'Running PHP ' . PHP_VERSION, 'ok' => PHP_VERSION_ID >= 80000],
            ['label' => 'MySQL driver (pdo_mysql)', 'detail' => extension_loaded('pdo_mysql') ? 'Available' : 'Enable pdo_mysql in hPanel → PHP Configuration', 'ok' => extension_loaded('pdo_mysql')],
            ['label' => 'Encryption (sodium or OpenSSL)', 'detail' => function_exists('sodium_crypto_secretbox') ? 'Sodium available' : (function_exists('openssl_encrypt') ? 'OpenSSL available' : 'Enable sodium or openssl'), 'ok' => function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt')],
            ['label' => 'Multibyte strings (mbstring)', 'detail' => extension_loaded('mbstring') ? 'Available' : 'Enable mbstring', 'ok' => extension_loaded('mbstring')],
            ['label' => 'cURL for the AI assistant', 'detail' => function_exists('curl_init') ? 'Available' : 'Optional — needed only for AI', 'ok' => true, 'warn' => !function_exists('curl_init')],
            [
                'label'  => 'Private settings folder',
                'detail' => $dir ? ((Config::isOutsideWebroot($dir) ? 'Outside the website folder — safe from redeploys' : 'Inside storage/ — protected, but keep it out of Git') . '') : 'Create a folder "meridian-data" next to public_html',
                'ok'     => (bool) $dir,
                'warn'   => $dir && !Config::isOutsideWebroot($dir),
            ],
        ];
    }

    private function dbInput(array $src): array
    {
        $get = fn ($k, $d = '') => is_string($src[$k] ?? null) ? trim($src[$k]) : $d;
        $host = $get('db_host', 'localhost') ?: 'localhost';
        if (!preg_match('/^[A-Za-z0-9.\-:_]+$/', $host)) {
            $host = 'localhost';
        }
        return [
            'host' => $host,
            'port' => max(1, min(65535, (int) ($get('db_port', '3306') ?: 3306))),
            'name' => $get('db_name'),
            'user' => $get('db_user'),
            'pass' => is_string($src['db_pass'] ?? null) ? $src['db_pass'] : '',
        ];
    }

    private function existingInstall(PDO $pdo): bool
    {
        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        } catch (PDOException) {
            return false;
        }
    }

    private function dbError(PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        return match ($code) {
            1045    => 'Access denied. Check the database username and password in hPanel → Databases → MySQL Databases.',
            1049    => 'That database name does not exist. Copy the full name from hPanel (it starts with your account prefix, like u123456789_hr).',
            2002    => 'The database server could not be reached. On Hostinger the host is usually "localhost".',
            default => 'Could not connect to the database (' . ($code ?: 'error') . '). Check the details and try again.',
        };
    }

    private function throttle(): void
    {
        $_SESSION['setup_hits'] = array_filter($_SESSION['setup_hits'] ?? [], fn ($t) => $t > time() - 600);
        if (count($_SESSION['setup_hits']) >= 30) {
            abort(429);
        }
        $_SESSION['setup_hits'][] = time();
    }

    private function fail(string $message): never
    {
        remember_input($_POST);
        unset($_SESSION['_old']['db_pass'], $_SESSION['_old']['ai_key']);
        flash('error', $message);
        redirect('setup');
    }
}
