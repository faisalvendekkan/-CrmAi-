<?php
declare(strict_types=1);

final class ToolController
{
    public function ai(): void
    {
        $this->list('ai');
    }

    public function apps(): void
    {
        $this->list('app');
    }

    private function list(string $kind): void
    {
        $perm = $kind === 'ai' ? 'ai_tools' : 'apps';
        Auth::require($perm);
        $tools = DB::all('SELECT * FROM tools WHERE kind = ? ORDER BY sort_order, name', [$kind]);
        view('tools/index', [
            'title'   => $kind === 'ai' ? 'AI integrations' : 'Applications',
            'kind'    => $kind,
            'tools'   => $tools,
            'cats'    => array_values(array_unique(array_filter(array_column($tools, 'category')))),
            'canEdit' => can($perm, 'edit'),
            'pageModule' => $kind === 'ai' ? 'ai-tools' : 'apps',
        ]);
    }

    public function save(): void
    {
        $kind = input('kind') === 'ai' ? 'ai' : 'app';
        Auth::require($kind === 'ai' ? 'ai_tools' : 'apps', 'edit');
        $id  = (int) input('id', '0');
        $url = input('url');
        $d = [
            'kind'        => $kind,
            'name'        => mb_substr(input('name'), 0, 100),
            'category'    => mb_substr(input('category'), 0, 80),
            'url'         => $url,
            'description' => mb_substr(input('description'), 0, 300),
        ];
        if ($d['name'] === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            flash('error', 'Enter a name and a full web address starting with https://');
            redirect($kind === 'ai' ? 'ai-tools' : 'apps');
        }
        if ($id) {
            DB::update('tools', $d, $id);
        } else {
            $d['sort_order'] = (int) DB::val('SELECT COALESCE(MAX(sort_order),0) + 1 FROM tools');
            $id = DB::insert('tools', $d);
        }
        Activity::log('saved', 'tools', $id, 'Tool: ' . $d['name']);
        flash('success', 'Saved ' . $d['name'] . '.');
        redirect($kind === 'ai' ? 'ai-tools' : 'apps');
    }

    public function delete(int $id): void
    {
        $t = DB::one('SELECT * FROM tools WHERE id = ?', [$id]) ?? abort(404);
        Auth::require($t['kind'] === 'ai' ? 'ai_tools' : 'apps', 'edit');
        DB::delete('tools', $id);
        Activity::log('deleted', 'tools', $id, 'Tool: ' . $t['name']);
        flash('success', 'Removed ' . $t['name'] . '.');
        redirect($t['kind'] === 'ai' ? 'ai-tools' : 'apps');
    }
}
