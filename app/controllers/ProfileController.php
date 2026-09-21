<?php
declare(strict_types=1);

final class ProfileController
{
    public function index(): void
    {
        $u = Auth::require();
        view('users/profile', ['title' => 'Your profile', 'u' => $u]);
    }

    public function save(): void
    {
        $u    = Auth::require();
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            flash('error', 'Enter your full name.');
            redirect('profile');
        }
        DB::run('UPDATE users SET name = ? WHERE id = ?', [$name, $u['id']]);

        $new = (string) ($_POST['password'] ?? '');
        if ($new !== '') {
            if (!password_verify((string) ($_POST['current_password'] ?? ''), $u['password_hash'])) {
                flash('error', 'Your current password is incorrect.');
                redirect('profile');
            }
            if ($problem = Auth::passwordProblem($new, $u['email'])) {
                flash('error', $problem);
                redirect('profile');
            }
            if (!hash_equals($new, (string) ($_POST['password_confirm'] ?? ''))) {
                flash('error', 'The two new passwords do not match.');
                redirect('profile');
            }
            DB::run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [Auth::hash($new), $u['id']]);
            $_SESSION['sv'] = (int) $u['session_version'] + 1;
            Session::regenerate();
            Activity::log('password_changed', 'auth', (int) $u['id'], 'Changed password; other devices signed out');
            flash('success', 'Profile saved. Your password was changed and other devices were signed out.');
            redirect('profile');
        }
        flash('success', 'Profile saved.');
        redirect('profile');
    }
}
