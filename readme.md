# KPT DataTables

Advanced PHP DataTables library with CRUD operations, search, sorting, pagination, bulk actions, and UIKit3 integration.

## Features

- 🚀 **Full CRUD Operations** - Create, Read, Update, Delete with AJAX support
- 🔍 **Advanced Search** - Search all columns or specific columns
- 📊 **Sorting** - Multi-column sorting with visual indicators
- 📄 **Pagination** - Configurable page sizes with first/last navigation
- ✅ **Bulk Actions** - Select multiple records for bulk operations
- ✏️ **Inline Editing** - Double-click to edit fields directly in the table
- 📁 **File Uploads** - Built-in file upload handling with validation
- 🎨 **Themes** - Light and dark UIKit3 themes with toggle
- 📱 **Responsive** - Mobile-friendly design
- 🔗 **JOINs** - Support for complex database relationships
- 🎛️ **Customizable** - Extensive configuration options
- 🔧 **Chainable API** - Fluent interface for easy configuration

## Requirements

- PHP 8.1 or higher
- PDO extension
- JSON extension

## Installation

Install via Composer:

```bash
composer require kpirnie/kpt-datatables
```

## Dependencies

This package depends on:
- [`kevinpirnie/kpt-database`](https://packagist.org/packages/kevinpirnie/kpt-database) - Database wrapper
- [`kevinpirnie/kpt-logger`](https://packagist.org/packages/kevinpirnie/kpt-logger) - Logging functionality

## Quick Start

### 1. Basic Setup

```php
<?php
require 'vendor/autoload.php';

use KPT\Database;
use KPT\DataTables\DataTables;

// Configure database
$dbConfig = (object)[
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$db = new Database($dbConfig);
$dataTable = new DataTables($db);
```

### 2. Simple Table

```php
// Handle AJAX requests
if (isset($_POST['action']) || isset($_GET['action'])) {
    $dataTable->handleAjax();
}

// Configure and render table
echo $dataTable
    ->table('users')
    ->columns([
        'id' => ['label' => 'ID', 'field' => 'id'],
        'name' => ['label' => 'Full Name', 'field' => 'name'],
        'email' => ['label' => 'Email Address', 'field' => 'email'],
        'created_at' => ['label' => 'Created', 'field' => 'created_at']
    ])
    ->sortable(['name', 'email', 'created_at'])
    ->render();
```

## Advanced Usage

### Complete Configuration Example

```php
$dataTable = new DataTables($db);

$dataTable
    ->table('users')
    ->primaryKey('user_id') // Default: 'id'
    ->columns([
        'user_id' => ['label' => 'ID', 'field' => 'u.user_id'],
        'name' => ['label' => 'Name', 'field' => 'u.name', 'class' => 'uk-text-bold'],
        'email' => ['label' => 'Email', 'field' => 'u.email'],
        'role_name' => ['label' => 'Role', 'field' => 'r.role_name'],
        'status' => ['label' => 'Status', 'field' => 'u.status']
    ])
    
    // JOIN other tables
    ->join('LEFT', 'user_roles r', 'u.role_id = r.role_id')
    
    // Configure sorting and editing
    ->sortable(['name', 'email', 'created_at'])
    ->inlineEditable(['name', 'email'])
    
    // Pagination options
    ->perPage(25)
    ->pageSizeOptions([10, 25, 50, 100], true) // true includes "ALL" option
    
    // Enable bulk actions
    ->bulkActions(true, [
        'activate' => [
            'label' => 'Activate Selected',
            'icon' => 'check',
            'class' => 'uk-button-secondary',
            'confirm' => 'Activate selected users?',
            'callback' => function($ids, $db, $table) {
                return $db->raw("UPDATE {$table} SET status = 'active' WHERE user_id IN (" . 
                              implode(',', array_fill(0, count($ids), '?')) . ")", $ids);
            }
        ]
    ])
    
    // Configure action buttons
    ->actions('end', true, true, [
        [
            'icon' => 'mail',
            'title' => 'Send Email',
            'class' => 'btn-email'
        ]
    ])
    
    // Add form configuration
    ->addForm('Add New User', [
        'name' => [
            'type' => 'text',
            'label' => 'Full Name',
            'required' => true,
            'placeholder' => 'Enter full name',
            'class' => 'uk-input-large'
        ],
        'email' => [
            'type' => 'email',
            'label' => 'Email Address',
            'required' => true,
            'placeholder' => 'user@example.com'
        ],
        'role_id' => [
            'type' => 'select',
            'label' => 'Role',
            'required' => true,
            'options' => [
                '1' => 'Administrator',
                '2' => 'Editor',
                '3' => 'User'
            ]
        ],
        'avatar' => [
            'type' => 'file',
            'label' => 'Avatar Image'
        ],
        'status' => [
            'type' => 'radio',
            'label' => 'Status',
            'options' => [
                'active' => 'Active',
                'inactive' => 'Inactive'
            ],
            'value' => 'active'
        ]
    ])
    
    // Edit form (similar to add form)
    ->editForm('Edit User', [
        // ... same fields as add form
    ])
    
    // CSS customization
    ->tableClass('uk-table uk-table-striped uk-table-hover custom-table')
    ->rowClass('custom-row')
    ->columnClasses([
        'name' => 'uk-text-bold',
        'email' => 'uk-text-primary',
        'status' => 'uk-text-center'
    ])
    
    // File upload configuration
    ->fileUpload('uploads/avatars/', ['jpg', 'jpeg', 'png', 'gif'], 5242880) // 5MB limit
    
    ->render();
```

## Field Types

### Text Inputs
```php
'field_name' => [
    'type' => 'text', // text, email, url, tel, number, password
    'label' => 'Field Label',
    'required' => true,
    'placeholder' => 'Placeholder text',
    'class' => 'custom-css-class',
    'attributes' => ['maxlength' => '100']
]
```

### Textarea
```php
'description' => [
    'type' => 'textarea',
    'label' => 'Description',
    'placeholder' => 'Enter description...',
    'attributes' => ['rows' => '5']
]
```

### Select Dropdown
```php
'category' => [
    'type' => 'select',
    'label' => 'Category',
    'required' => true,
    'options' => [
        '1' => 'Category 1',
        '2' => 'Category 2',
        '3' => 'Category 3'
    ]
]
```

### Radio Buttons
```php
'status' => [
    'type' => 'radio',
    'label' => 'Status',
    'options' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending'
    ],
    'value' => 'active'
]
```

### Checkbox
```php
'newsletter' => [
    'type' => 'checkbox',
    'label' => 'Subscribe to Newsletter',
    'value' => '1'
]
```

### File Upload
```php
'document' => [
    'type' => 'file',
    'label' => 'Upload Document'
]
```

### Date/Time Fields
```php
'birth_date' => [
    'type' => 'date',
    'label' => 'Birth Date'
],
'appointment' => [
    'type' => 'datetime',
    'label' => 'Appointment Date & Time'
],
'meeting_time' => [
    'type' => 'time',
    'label' => 'Meeting Time'
]
```

## Bulk Actions

### Built-in Delete Action
```php
->bulkActions(true) // Enables default delete action
```

### Custom Bulk Actions
```php
->bulkActions(true, [
    'archive' => [
        'label' => 'Archive Selected',
        'icon' => 'archive',
        'class' => 'uk-button-secondary',
        'confirm' => 'Archive selected records?',
        'callback' => function($selectedIds, $database, $tableName) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            return $database->raw(
                "UPDATE {$tableName} SET archived = 1 WHERE id IN ({$placeholders})", 
                $selectedIds
            );
        },
        'success_message' => 'Records archived successfully',
        'error_message' => 'Failed to archive records'
    ],
    'export' => [
        'label' => 'Export Selected',
        'icon' => 'download',
        'class' => 'uk-button-primary',
        'callback' => function($selectedIds, $database, $tableName) {
            // Custom export logic
            return true;
        }
    ]
])
```

## Themes

### Theme Toggle
The package includes light and dark themes with automatic theme switching:

```html
<!-- Theme toggle button is automatically included -->
<button onclick="DataTables.toggleTheme()">Toggle Theme</button>
```

### Custom CSS Classes
```php
// Row classes with ID suffix (e.g., "highlight-123")
->rowClass('highlight')

// Column-specific classes
->columnClasses([
    'name' => 'uk-text-bold uk-text-primary',
    'status' => 'uk-text-center',
    'actions' => 'uk-text-nowrap'
])
```

## Database Joins

```php
$dataTable
    ->table('orders o')
    ->join('INNER', 'customers c', 'o.customer_id = c.customer_id')
    ->join('LEFT', 'order_status s', 'o.status_id = s.status_id')
    ->columns([
        'order_id' => ['label' => 'Order ID', 'field' => 'o.order_id'],
        'customer_name' => ['label' => 'Customer', 'field' => 'c.name'],
        'order_date' => ['label' => 'Date', 'field' => 'o.order_date'],
        'status_name' => ['label' => 'Status', 'field' => 's.status_name'],
        'total' => ['label' => 'Total', 'field' => 'o.total']
    ]);
```

## AJAX vs Non-AJAX Forms

### AJAX Forms (Default)
```php
->addForm('Add Record', $fields, true) // true = AJAX
->editForm('Edit Record', $fields, true)
```

### Traditional Form Submission
```php
->addForm('Add Record', $fields, false) // false = traditional POST
->editForm('Edit Record', $fields, false)
```

## File Upload Configuration

```php
->fileUpload(
    'uploads/documents/',           // Upload path
    ['pdf', 'doc', 'docx', 'jpg'],  // Allowed extensions
    10485760                        // Max file size (10MB)
)
```

## Search Configuration

### Enable/Disable Search
```php
->search(true)  // Enable search
->noSearch()    // Disable search (shorthand for ->search(false))
```

The search feature automatically includes:
- Global search across all columns
- Column-specific search dropdown
- Real-time search with 300ms debounce

## Events and Hooks

### JavaScript Events
```javascript
// Table loaded
document.addEventListener('datatables:loaded', function(e) {
    console.log('Table loaded', e.detail);
});

// Record added
document.addEventListener('datatables:record:added', function(e) {
    console.log('Record added', e.detail);
});

// Theme changed
document.addEventListener('datatables:theme:changed', function(e) {
    console.log('Theme changed to', e.detail.theme);
});
```

## Installation Page

For first-time setup, the package can generate an installation page if no database configuration is found:

```php
// Check if configuration exists
if (!file_exists('settings/.config.json')) {
    // Show installation page
    include 'vendor/kpirnie/kpt-datatables/install.php';
    exit;
}
```

## Browser Support

- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan

# Run code style check
composer phpcs
```

## Security

If you discover any security-related issues, please email security@kpirnie.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Kevin Pirnie](https://github.com/kpirnie)
- [UIKit3](https://getuikit.com/) for the UI framework
- All contributors

## Support

- **Documentation**: [GitHub Wiki](https://github.com/kpirnie/kpt-datatables/wiki)
- **Issues**: [GitHub Issues](https://github.com/kpirnie/kpt-datatables/issues)

## Roadmap

- [ ] Export functionality (CSV, Excel, PDF)
- [ ] Advanced filtering options
- [ ] Column visibility toggle
- [ ] Row drag & drop reordering
- [ ] Real-time updates via WebSockets
- [ ] Integration with popular PHP frameworks
- [ ] REST API endpoints
- [ ] Audit trail/change logging

---

**Made with ❤️ by [Kevin Pirnie](https://kpirnie.com)**
```