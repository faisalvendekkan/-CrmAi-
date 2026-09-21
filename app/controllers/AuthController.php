<?php
declare(strict_types=1);

final class AuthController
{
    public function loginForm(): void
    {
        if (Auth::user()) {
            redirect('');
        }
        view('auth/login', [], 'layouts/bare');
    }

    public function login(): void
    {
        if (Auth::user()) {
            redirect('');
        }
        $email    = mb_strtolower(input('email'));
        $password = (string) ($_POST['password'] ?? '');
        remember_input(['email' => $email]);

        if ($email === '' || $password === '') {
            flash('error', 'Enter your email and password.');
            redirect('login');
        }

        $result = Auth::attempt($email, mb_substr($password, 0, 1024));
        if ($result !== 'ok') {
            usleep(random_int(200000, 500000));
            flash('error', match ($result) {
                'locked'    => 'Too many failed attempts. Sign-in is paused for 15 minutes for this account.',
                'suspended' => 'This account is suspended. Contact your administrator.',
                default     => 'The email or password is incorrect.',
            });
            redirect('login');
        }

        forget_input();
        $intended = $_SESSION['intended'] ?? '';
        unset($_SESSION['intended']);
        $base = base_path();
        if (is_string($intended) && str_starts_with($intended, $base . '/') && !str_starts_with($intended, '//') && !str_contains($intended, '/api/')) {
            header('Location: ' . $intended, true, 303);
            exit;
        }
        redirect('');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('info', 'You are signed out.');
        redirect('login');
    }

    public function passwordForm(): void
    {
        Auth::require();
        view('auth/password', [], 'layouts/bare');
    }

    public function passwordSave(): void
    {
        $u        = Auth::require();
        $password = (string) ($_POST['password'] ?? '');
        if ($problem = Auth::passwordProblem($password, $u['email'])) {
            flash('error', $problem);
            redirect('password');
        }
        if (!hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) {
            flash('error', 'The two passwords do not match.');
            redirect('password');
        }
        if (password_verify($password, $u['password_hash'])) {
            flash('error', 'Choose a new password that is different from the temporary one.');
            redirect('password');
        }
        DB::run('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?', [Auth::hash($password), $u['id']]);
        $_SESSION['sv'] = (int) $u['session_version'] + 1;
        Session::regenerate();
        Activity::log('password_changed', 'auth', (int) $u['id'], 'Set a new password');
        flash('success', 'Password updated.');
        redirect('');
    }
}
