<?php
declare(strict_types=1);

final class UserController
{
    public function index(): void
    {
        Auth::require('admin');
        $users = DB::all('SELECT * FROM users ORDER BY is_owner DESC, role = \'admin\' DESC, name');
        view('users/index', ['title' => 'Users & access', 'users' => $users, 'pageModule' => 'users']);
    }

    public function form(int $id = 0): void
    {
        Auth::require('admin');
        $u = $id ? (DB::one('SELECT * FROM users WHERE id = ?', [$id]) ?? abort(404)) : [
            'id' => 0, 'name' => '', 'email' => '', 'role' => 'manager', 'status' => 'active', 'is_owner' => 0,
            'permissions' => json_encode(['employees' => 'view', 'leave' => 'edit', 'attendance' => 'edit', 'tasks' => 'edit', 'assistant' => 'edit', 'apps' => 'view', 'ai_tools' => 'view']),
        ];
        foreach (['name', 'email', 'role', 'status'] as $k) {
            if (isset($_SESSION['_old'][$k])) {
                $u[$k] = $_SESSION['_old'][$k];
            }
        }
        forget_input();
        view('users/form', [
            'title' => $id ? 'Edit ' . $u['name'] : 'Add user',
            'u'     => $u,
            'perms' => json_decode((string) $u['permissions'], true) ?: [],
            'pageModule' => 'users',
        ]);
    }

    public function save(): void
    {
        $me = Auth::require('admin');
        $id = (int) input('id', '0');
        $existing = $id ? (DB::one('SELECT * FROM users WHERE id = ?', [$id]) ?? abort(404)) : null;

        $name   = input('name');
        $email  = mb_strtolower(input('email'));
        $role   = array_key_exists(input('role'), Auth::ROLES) ? input('role') : 'viewer';
        $status = input('status') === 'suspended' ? 'suspended' : 'active';
        $password = (string) ($_POST['password'] ?? '');

        $perms = [];
        foreach (array_keys(Auth::PERMISSIONS) as $p) {
            $lvl = (string) ($_POST['perm'][$p] ?? 'none');
            if (in_array($lvl, ['view', 'edit'], true)) {
                $perms[$p] = $role === 'viewer' ? 'view' : $lvl;
            }
        }

        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = 'Enter the full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (DB::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) $errors[] = 'Another user already uses this email.';
        if (!$existing || $password !== '') {
            if ($p = Auth::passwordProblem($password, $email)) $errors[] = $p;
        }
        if ($existing && (int) $existing['is_owner'] === 1) {
            $role = 'admin';
            $status = 'active';
        }
        if ($existing && (int) $existing['id'] === (int) $me['id']) {
            if ($role !== 'admin' || $status !== 'active') $errors[] = 'You cannot remove your own administrator access or suspend yourself.';
        }
        if ($errors) {
            remember_input($_POST);
            flash('error', implode(' ', $errors));
            redirect($id ? "users/$id/edit" : 'users/new');
        }

        $data = [
            'name'        => $name,
            'email'       => $email,
            'role'        => $role,
            'status'      => $status,
            'permissions' => json_encode($role === 'admin' ? new stdClass() : $perms),
        ];
        $mustChange = !empty($_POST['must_change_password']) ? 1 : 0;

        if ($existing) {
            if ($password !== '') {
                $data['password_hash'] = Auth::hash($password);
                $data['must_change_password'] = $mustChange;
            }
            DB::update('users', $data, $id);
            if ($password !== '' || ($existing['status'] === 'active' && $status === 'suspended')) {
                DB::run('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$id]);
            }
            Activity::log('updated', 'users', $id, "User {$email} ({$role}, {$status})");
            flash('success', "Saved {$name}.");
        } else {
            $data['password_hash'] = Auth::hash($password);
            $data['must_change_password'] = $mustChange;
            $id = DB::insert('users', $data);
            Activity::log('created', 'users', $id, "Added user {$email} ({$role})");
            flash('success', "Added {$name}. Share the password with them securely" . ($mustChange ? ' — they will set their own at first sign-in.' : '.'));
        }
        forget_input();
        redirect('users');
    }

    public function delete(int $id): void
    {
        $me = Auth::require('admin');
        $u = DB::one('SELECT * FROM users WHERE id = ?', [$id]) ?? abort(404);
        if ((int) $u['is_owner'] === 1 || (int) $u['id'] === (int) $me['id']) {
            flash('error', 'The owner account and your own account cannot be removed.');
            redirect('users');
        }
        DB::delete('users', $id);
        Activity::log('deleted', 'users', $id, "Removed user {$u['email']}");
        flash('success', "Removed {$u['name']}.");
        redirect('users');
    }

    public function signOut(int $id): void
    {
        Auth::require('admin');
        $u = DB::one('SELECT name FROM users WHERE id = ?', [$id]) ?? abort(404);
        DB::run('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$id]);
        Activity::log('signed_out_everywhere', 'users', $id, "Signed out {$u['name']} on all devices");
        flash('success', "{$u['name']} was signed out on all devices.");
        redirect('users');
    }
}
