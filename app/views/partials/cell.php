<?php
/** Renders one table cell value. Expects $f (field def), $col, $r (row). */
$v = $r[$col] ?? null;
switch ($f['type']) {
    case 'employee':
        if (!empty($r['employee_name'])) {
            echo can('employees') ? '<a class="cell-title" href="' . e(url('employees/' . $r['employee_id'])) . '">' . e($r['employee_name']) . '</a>' : '<span class="cell-title">' . e($r['employee_name']) . '</span>';
            echo $r['employee_department'] ? '<div class="cell-sub">' . e($r['employee_department']) . '</div>' : '';
        } else {
            echo '<span class="muted">Unassigned</span>';
        }
        break;
    case 'date':
        if (isset($f['expiry'])) {
            echo expiry_badge($v);
        } elseif ($col === 'deadline' && valid_date($v) && ($r['status'] ?? '') !== 'done') {
            $d = days_until($v);
            echo '<span class="date-cell"><span>' . e(fmt_date($v)) . '</span>' . ($d < 0 ? '<span class="badge tone-danger">Overdue</span>' : ($d <= 2 ? '<span class="badge tone-warn">' . e(rel_days($d)) . '</span>' : '')) . '</span>';
        } else {
            echo '<span class="num">' . e(fmt_date($v)) . '</span>';
        }
        break;
    case 'time':
        echo $v ? '<span class="num">' . e(substr((string) $v, 0, 5)) . '</span>' : '<span class="muted">—</span>';
        break;
    case 'select':
        echo in_array($col, ['status', 'priority'], true) ? badge($v, $f['options'][$v] ?? null) : e($f['options'][$v] ?? $v);
        break;
    case 'number':
        echo '<span class="num">' . e(rtrim(rtrim((string) $v, '0'), '.') ?: '0') . '</span>';
        break;
    default:
        echo $v !== null && $v !== '' ? e($v) : '<span class="muted">—</span>';
}
