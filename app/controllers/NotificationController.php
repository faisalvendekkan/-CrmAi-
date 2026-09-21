<?php
declare(strict_types=1);

final class NotificationController
{
    public function index(): void
    {
        Auth::require('notifications');
        $items = Alerts::items();
        view('notifications/index', [
            'title'   => 'Expiry alerts',
            'items'   => $items,
            'enabled' => Alerts::enabledCategories(),
            'canEdit' => can('notifications', 'edit'),
            'pageModule' => 'alerts',
        ]);
    }

    public function save(): void
    {
        Auth::require('notifications', 'edit');
        $window = (int) input('alert_window', '30');
        if (!in_array($window, [7, 15, 30, 60, 90], true)) {
            $window = 30;
        }
        $cats = array_values(array_intersect(array_keys(Alerts::CATEGORIES), (array) ($_POST['categories'] ?? [])));
        $recipients = [];
        foreach (preg_split('/[\s,;]+/', input('digest_recipients')) ?: [] as $r) {
            if (filter_var($r, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = mb_strtolower($r);
            }
        }
        Settings::many([
            'alert_window'          => (string) $window,
            'alert_categories'      => implode(',', $cats),
            'alert_include_expired' => empty($_POST['alert_include_expired']) ? '0' : '1',
            'alert_browser'         => empty($_POST['alert_browser']) ? '0' : '1',
            'digest_enabled'        => empty($_POST['digest_enabled']) ? '0' : '1',
            'digest_recipients'     => implode(', ', array_unique(array_slice($recipients, 0, 20))),
        ]);
        Activity::log('updated', 'notifications', null, 'Updated alert settings');
        flash('success', 'Alert settings saved.');
        redirect('alerts');
    }

    /** Small JSON used by the browser to show a once-per-day desktop notification. */
    public function browser(): void
    {
        Auth::require('notifications');
        if (setting('alert_browser') !== '1') {
            json_out(['ok' => true, 'enabled' => false]);
        }
        $items = Alerts::items();
        json_out([
            'ok'      => true,
            'enabled' => true,
            'expired' => count(array_filter($items, fn ($i) => $i['risk'] === 'expired')),
            'due'     => count(array_filter($items, fn ($i) => $i['risk'] === 'due')),
            'first'   => $items[0]['title'] ?? null,
        ]);
    }
}
