<?php
declare(strict_types=1);

/**
 * Record modules. Each entry drives the list, form, validation, search and export.
 * To add a field: add the column in a new migration, then add it here.
 *
 * Field options: label, type (text|email|tel|date|time|number|select|textarea|employee),
 * required, list (show in table), search, options (select), suggest (datalist values),
 * expiry (label used by expiry alerts), full (full-width in form), help, placeholder.
 */
final class Modules
{
    public static function all(): array
    {
        return [
            'employees' => [
                'label'    => 'Employees',
                'singular' => 'employee',
                'table'    => 'employees',
                'perm'     => 'employees',
                'icon'     => 'users',
                'intro'    => 'Staff profiles, ID numbers and visa dates in one register.',
                'title'    => 'name',
                'sort'     => 'name ASC',
                'filters'  => ['department'],
                'fields'   => [
                    'name'            => ['label' => 'Full name', 'type' => 'text', 'required' => true, 'list' => true, 'search' => true],
                    'employee_no'     => ['label' => 'Employee ID', 'type' => 'text', 'search' => true, 'placeholder' => 'EMP-001'],
                    'department'      => ['label' => 'Department', 'type' => 'text', 'list' => true, 'search' => true, 'suggest' => 'departments'],
                    'designation'     => ['label' => 'Designation', 'type' => 'text', 'list' => true, 'search' => true],
                    'nationality'     => ['label' => 'Nationality', 'type' => 'text'],
                    'email'           => ['label' => 'Email', 'type' => 'email', 'search' => true],
                    'phone'           => ['label' => 'Phone', 'type' => 'tel', 'placeholder' => '+974'],
                    'joining_date'    => ['label' => 'Joining date', 'type' => 'date'],
                    'qid'             => ['label' => 'QID number', 'type' => 'text', 'search' => true],
                    'qid_expiry'      => ['label' => 'QID expiry', 'type' => 'date', 'expiry' => 'QID'],
                    'passport'        => ['label' => 'Passport number', 'type' => 'text', 'search' => true],
                    'passport_expiry' => ['label' => 'Passport expiry', 'type' => 'date', 'expiry' => 'Passport'],
                    'visa_expiry'     => ['label' => 'Visa expiry', 'type' => 'date', 'list' => true, 'expiry' => 'Visa'],
                    'status'          => ['label' => 'Status', 'type' => 'select', 'list' => true, 'options' => ['active' => 'Active', 'on_leave' => 'On leave', 'inactive' => 'Inactive'], 'default' => 'active'],
                    'notes'           => ['label' => 'Notes', 'type' => 'textarea', 'full' => true],
                ],
            ],

            'documents' => [
                'label'    => 'Company documents',
                'singular' => 'document',
                'table'    => 'documents',
                'perm'     => 'documents',
                'icon'     => 'file',
                'intro'    => 'Commercial registration, licences, insurance and contracts with their renewal owner.',
                'title'    => 'name',
                'sort'     => 'expiry_date IS NULL, expiry_date ASC',
                'filters'  => ['expiry'],
                'fields'   => [
                    'name'        => ['label' => 'Document name', 'type' => 'text', 'required' => true, 'list' => true, 'search' => true],
                    'category'    => ['label' => 'Category', 'type' => 'text', 'list' => true, 'search' => true, 'suggest' => ['Commercial registration', 'Trade licence', 'Establishment card', 'Insurance', 'Tenancy contract', 'Service contract', 'Municipality licence', 'Other']],
                    'owner'       => ['label' => 'Responsible', 'type' => 'text', 'list' => true, 'search' => true, 'suggest' => 'owners'],
                    'reference'   => ['label' => 'Reference number', 'type' => 'text', 'list' => true, 'search' => true],
                    'issue_date'  => ['label' => 'Issue date', 'type' => 'date'],
                    'expiry_date' => ['label' => 'Expiry date', 'type' => 'date', 'list' => true, 'expiry' => 'Expiry'],
                    'notes'       => ['label' => 'Notes', 'type' => 'textarea', 'full' => true],
                ],
            ],

            'emp-documents' => [
                'label'    => 'Employee documents',
                'singular' => 'employee document',
                'table'    => 'emp_documents',
                'perm'     => 'emp_documents',
                'icon'     => 'id',
                'intro'    => 'Health cards, work permits, licences and other personal documents with expiry follow-up.',
                'title'    => 'doc_type',
                'sort'     => 't.expiry_date IS NULL, t.expiry_date ASC',
                'filters'  => ['department', 'expiry'],
                'fields'   => [
                    'employee_id' => ['label' => 'Employee', 'type' => 'employee', 'required' => true, 'list' => true],
                    'doc_type'    => ['label' => 'Document type', 'type' => 'text', 'required' => true, 'list' => true, 'search' => true, 'suggest' => ['Health card', 'Work permit', 'Driving licence', 'Medical fitness', 'Professional licence', 'Insurance card', 'Other']],
                    'reference'   => ['label' => 'Reference number', 'type' => 'text', 'list' => true, 'search' => true],
                    'issue_date'  => ['label' => 'Issue date', 'type' => 'date'],
                    'expiry_date' => ['label' => 'Expiry date', 'type' => 'date', 'list' => true, 'expiry' => 'Expiry'],
                    'notes'       => ['label' => 'Notes', 'type' => 'textarea', 'full' => true],
                ],
            ],

            'assets' => [
                'label'    => 'Vehicles & assets',
                'singular' => 'asset',
                'table'    => 'assets',
                'perm'     => 'assets',
                'icon'     => 'car',
                'intro'    => 'Vehicles, laptops, phones and equipment — who has them and when renewals fall due.',
                'title'    => 'item',
                'sort'     => 'asset_type ASC, item ASC',
                'filters'  => ['asset_type', 'expiry'],
                'fields'   => [
                    'asset_type'          => ['label' => 'Type', 'type' => 'select', 'list' => true, 'options' => ['Vehicle' => 'Vehicle', 'Laptop' => 'Laptop', 'Desktop' => 'Desktop', 'Phone' => 'Phone', 'Printer' => 'Printer', 'Tool' => 'Tool', 'Furniture' => 'Furniture', 'Other' => 'Other'], 'default' => 'Vehicle'],
                    'item'                => ['label' => 'Item or model', 'type' => 'text', 'required' => true, 'list' => true, 'search' => true, 'placeholder' => 'Toyota Hilux 2024'],
                    'reference'           => ['label' => 'Plate or serial number', 'type' => 'text', 'list' => true, 'search' => true],
                    'details'             => ['label' => 'Details', 'type' => 'text', 'search' => true, 'placeholder' => 'Colour, specs, location'],
                    'assigned_to'         => ['label' => 'Assigned to', 'type' => 'text', 'list' => true, 'search' => true, 'suggest' => 'employees'],
                    'registration_expiry' => ['label' => 'Istimara / registration expiry', 'type' => 'date', 'list' => true, 'expiry' => 'Istimara'],
                    'coverage_expiry'     => ['label' => 'Insurance / warranty expiry', 'type' => 'date', 'list' => true, 'expiry' => 'Insurance / warranty'],
                    'purchase_date'       => ['label' => 'Purchase date', 'type' => 'date'],
                    'status'              => ['label' => 'Status', 'type' => 'select', 'list' => true, 'options' => ['in_use' => 'In use', 'available' => 'Available', 'maintenance' => 'Maintenance', 'retired' => 'Retired'], 'default' => 'in_use'],
                    'notes'               => ['label' => 'Notes', 'type' => 'textarea', 'full' => true],
                ],
            ],

            'tasks' => [
                'label'    => 'Admin tasks',
                'singular' => 'task',
                'table'    => 'tasks',
                'perm'     => 'tasks',
                'icon'     => 'check',
                'intro'    => 'Daily admin work with an owner, a priority and a deadline.',
                'title'    => 'title',
                'sort'     => "FIELD(status,'open','in_progress','done'), FIELD(priority,'high','medium','low'), deadline IS NULL, deadline ASC",
                'filters'  => [],
                'fields'   => [
                    'title'    => ['label' => 'Task', 'type' => 'text', 'required' => true, 'list' => true, 'search' => true],
                    'owner'    => ['label' => 'Owner', 'type' => 'text', 'list' => true, 'search' => true, 'suggest' => 'employees'],
                    'priority' => ['label' => 'Priority', 'type' => 'select', 'list' => true, 'options' => ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'], 'default' => 'medium'],
                    'deadline' => ['label' => 'Deadline', 'type' => 'date', 'list' => true],
                    'status'   => ['label' => 'Status', 'type' => 'select', 'list' => true, 'options' => ['open' => 'Open', 'in_progress' => 'In progress', 'done' => 'Done'], 'default' => 'open'],
                    'notes'    => ['label' => 'Notes', 'type' => 'textarea', 'full' => true],
                ],
            ],

            'leave' => [
                'label'    => 'Leave',
                'singular' => 'leave request',
                'table'    => 'leave_requests',
                'perm'     => 'leave',
                'icon'     => 'calendar',
                'intro'    => 'Leave requests, approvals and balances.',
                'title'    => 'leave_type',
                'sort'     => "FIELD(t.status,'pending','approved','rejected'), t.start_date DESC",
                'filters'  => ['department'],
                'fields'   => [
                    'employee_id' => ['label' => 'Employee', 'type' => 'employee', 'required' => true, 'list' => true],
                    'leave_type'  => ['label' => 'Leave type', 'type' => 'select', 'list' => true, 'options' => ['annual' => 'Annual', 'sick' => 'Sick', 'emergency' => 'Emergency', 'unpaid' => 'Unpaid', 'maternity' => 'Maternity', 'hajj' => 'Hajj', 'other' => 'Other'], 'default' => 'annual'],
                    'start_date'  => ['label' => 'From', 'type' => 'date', 'required' => true, 'list' => true],
                    'end_date'    => ['label' => 'To', 'type' => 'date', 'required' => true, 'list' => true],
                    'days'        => ['label' => 'Days', 'type' => 'number', 'list' => true, 'computed' => true],
                    'status'      => ['label' => 'Status', 'type' => 'select', 'list' => true, 'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], 'default' => 'pending'],
                    'reason'      => ['label' => 'Reason', 'type' => 'textarea', 'full' => true],
                ],
            ],

            'attendance/records' => [
                'label'    => 'Attendance records',
                'singular' => 'attendance record',
                'table'    => 'attendance',
                'perm'     => 'attendance',
                'icon'     => 'clock',
                'intro'    => 'Full attendance history. Use the daily roster to mark today quickly.',
                'title'    => 'work_date',
                'sort'     => 't.work_date DESC, e.name ASC',
                'filters'  => ['department'],
                'fields'   => [
                    'employee_id' => ['label' => 'Employee', 'type' => 'employee', 'required' => true, 'list' => true],
                    'work_date'   => ['label' => 'Date', 'type' => 'date', 'required' => true, 'list' => true, 'default' => 'today'],
                    'check_in'    => ['label' => 'Check in', 'type' => 'time', 'list' => true],
                    'check_out'   => ['label' => 'Check out', 'type' => 'time', 'list' => true],
                    'status'      => ['label' => 'Status', 'type' => 'select', 'list' => true, 'options' => ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On leave', 'remote' => 'Remote'], 'default' => 'present'],
                    'notes'       => ['label' => 'Notes', 'type' => 'text', 'list' => true, 'full' => true],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        $m = self::all()[$key] ?? null;
        if ($m) {
            $m['key'] = $key;
        }
        return $m;
    }

    public static function hasEmployee(array $m): bool
    {
        foreach ($m['fields'] as $f) {
            if ($f['type'] === 'employee') {
                return true;
            }
        }
        return false;
    }

    /** Values for datalist suggestions. */
    public static function suggestions(string|array $source): array
    {
        if (is_array($source)) {
            return $source;
        }
        return match ($source) {
            'departments' => array_column(DB::all("SELECT DISTINCT department FROM employees WHERE department <> '' ORDER BY department"), 'department'),
            'employees'   => array_column(DB::all("SELECT name FROM employees WHERE status <> 'inactive' ORDER BY name"), 'name'),
            'owners'      => array_column(DB::all("SELECT DISTINCT owner FROM documents WHERE owner <> '' ORDER BY owner"), 'owner'),
            default       => [],
        };
    }

    public static function employeeOptions(): array
    {
        return DB::all("SELECT id, name, department FROM employees ORDER BY status = 'inactive', name");
    }
}
