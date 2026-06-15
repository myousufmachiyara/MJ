<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\HeadOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\ChartOfAccounts;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\MeasurementUnit;
use App\Models\ProductCategory;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductSubcategory;
use App\Models\Purity;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now    = now();
        $userId = 1;

        // ── Users & Roles ──────────────────────────────────────────────────────

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name'     => 'Admin',
                'email'    => 'admin@gmail.com',
                'password' => Hash::make('12345678'),
            ]
        );

        $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->assignRole($superAdminRole);

        $managerUser = User::firstOrCreate(
            ['username' => 'm.kashif'],
            [
                'name'     => 'M.Kashif',
                'email'    => null,
                'password' => Hash::make('12345678'),
            ]
        );

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerUser->assignRole($managerRole);

        // ── Permissions ────────────────────────────────────────────────────────

        $modules = [
            'user_roles', 'users',
            'coa', 'shoa',
            'products', 'product_categories', 'product_subcategories', 'attributes',
            'purities',
            'purchase_invoices', 'purchase_return',
            'sale_invoices', 'sale_return',
            'vouchers',
            'consignments', 
        ];

        $managerModules = [
            'coa', 'shoa',
            'products', 'product_categories', 'product_subcategories', 'attributes',
            'purities',
            'purchase_invoices', 'purchase_return',
            'sale_invoices', 'sale_return',
            'vouchers',
            'consignments',
        ];

        $actions = ['index', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$module.$action"]);
            }
        }

        // Report permissions
        foreach (['inventory', 'purchase', 'sales', 'accounts'] as $report) {
            Permission::firstOrCreate(['name' => "reports.$report"]);
        }

        // Sync superadmin with everything
        $superAdminRole->syncPermissions(Permission::all());

        // Manager gets module + report permissions
        $managerPermissions = [];
        foreach ($managerModules as $module) {
            foreach ($actions as $action) {
                $managerPermissions[] = "$module.$action";
            }
        }
        $managerPermissions = array_merge($managerPermissions, [
            'reports.inventory', 'reports.purchase', 'reports.sales', 'reports.accounts',
        ]);
        $managerRole->syncPermissions(
            Permission::whereIn('name', $managerPermissions)->get()
        );

        // ── Heads of Accounts ──────────────────────────────────────────────────

        $heads = [
            ['id' => 1, 'name' => 'Assets'],
            ['id' => 2, 'name' => 'Liabilities'],
            ['id' => 3, 'name' => 'Equity'],
            ['id' => 4, 'name' => 'Revenue'],
            ['id' => 5, 'name' => 'Expenses'],
        ];

        foreach ($heads as $head) {
            HeadOfAccounts::firstOrCreate(
                ['id' => $head['id']],
                array_merge($head, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // ── Sub Heads of Accounts ──────────────────────────────────────────────

        $subHeads = [
            ['id' =>  1, 'hoa_id' => 1, 'name' => 'Cash & Cash Equivalents'],
            ['id' =>  2, 'hoa_id' => 1, 'name' => 'Bank Accounts'],
            ['id' =>  3, 'hoa_id' => 1, 'name' => 'Inventory'],
            ['id' =>  4, 'hoa_id' => 1, 'name' => 'Accounts Receivable'],
            ['id' =>  5, 'hoa_id' => 1, 'name' => 'VAT Recoverable (Input Tax)'],
            ['id' =>  6, 'hoa_id' => 2, 'name' => 'Accounts Payable'],
            ['id' =>  7, 'hoa_id' => 2, 'name' => 'VAT Payable (Output Tax)'],
            ['id' =>  8, 'hoa_id' => 3, 'name' => 'Owner Capital'],
            ['id' =>  9, 'hoa_id' => 3, 'name' => 'Retained Earnings'],
            ['id' => 10, 'hoa_id' => 4, 'name' => 'Sales Revenue'],
            ['id' => 11, 'hoa_id' => 4, 'name' => 'Service Income'],
            ['id' => 12, 'hoa_id' => 5, 'name' => 'Material Purchases'],
            ['id' => 13, 'hoa_id' => 5, 'name' => 'Manufacturing & Making'],
            ['id' => 14, 'hoa_id' => 5, 'name' => 'Operating Expenses'],
        ];

        foreach ($subHeads as $sub) {
            SubHeadOfAccounts::firstOrCreate(
                ['id' => $sub['id']],
                array_merge($sub, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // ── Chart of Accounts ──────────────────────────────────────────────────

        $coaData = [
            // Assets — cash
            ['account_code' => '101001', 'shoa_id' =>  1, 'name' => 'Cash in Hand',                 'account_type' => 'cash'],
            // Assets — bank
            ['account_code' => '102001', 'shoa_id' =>  2, 'name' => 'Meezan Bank',                  'account_type' => 'bank'],
            ['account_code' => '102002', 'shoa_id' =>  2, 'name' => 'HBL Bank',                     'account_type' => 'bank'],
            // Assets — inventory
            ['account_code' => '104001', 'shoa_id' =>  3, 'name' => 'Gold Inventory',               'account_type' => 'asset'],
            ['account_code' => '104002', 'shoa_id' =>  3, 'name' => 'Diamond Inventory',            'account_type' => 'asset'],
            ['account_code' => '104003', 'shoa_id' =>  3, 'name' => 'Parts Inventory',              'account_type' => 'asset'],
            // Assets — VAT input
            ['account_code' => '105001', 'shoa_id' =>  5, 'name' => 'VAT Input Tax Recoverable',    'account_type' => 'asset'],
            // Liabilities — VAT output
            ['account_code' => '208001', 'shoa_id' =>  7, 'name' => 'Output VAT Payable',           'account_type' => 'liability'],
            // Equity
            ['account_code' => '301001', 'shoa_id' =>  8, 'name' => 'Owner Capital',                'account_type' => 'equity'],
            ['account_code' => '302001', 'shoa_id' =>  9, 'name' => 'Retained Earnings',            'account_type' => 'equity'],
            // Revenue
            ['account_code' => '401001', 'shoa_id' => 10, 'name' => 'Gold Sales Revenue',           'account_type' => 'revenue'],
            ['account_code' => '401002', 'shoa_id' => 10, 'name' => 'Diamond Sales Revenue',        'account_type' => 'revenue'],
            ['account_code' => '402001', 'shoa_id' => 11, 'name' => 'Making Charges Income',        'account_type' => 'revenue'],
            ['account_code' => '403001', 'shoa_id' => 10, 'name' => 'Parts Sales Revenue',          'account_type' => 'revenue'],
            // Expenses — material purchases
            ['account_code' => '510001', 'shoa_id' => 12, 'name' => 'Material Purchases (Gold)',    'account_type' => 'expenses'],
            ['account_code' => '510002', 'shoa_id' => 12, 'name' => 'Material Purchases (Diamond)', 'account_type' => 'expenses'],
            ['account_code' => '510003', 'shoa_id' => 13, 'name' => 'Making Charges Expense',       'account_type' => 'expenses'],
            ['account_code' => '510004', 'shoa_id' => 12, 'name' => 'Parts Purchases',              'account_type' => 'expenses'],
            // Expenses — operating
            ['account_code' => '601001', 'shoa_id' => 14, 'name' => 'Salaries & Wages',             'account_type' => 'expenses'],
            ['account_code' => '601002', 'shoa_id' => 14, 'name' => 'Rent Expense',                 'account_type' => 'expenses'],
            ['account_code' => '601003', 'shoa_id' => 14, 'name' => 'Utilities Expense',            'account_type' => 'expenses'],
            ['account_code' => '601004', 'shoa_id' => 14, 'name' => 'Miscellaneous Expense',        'account_type' => 'expenses'],
        ];

        foreach ($coaData as $item) {
            ChartOfAccounts::firstOrCreate(
                ['account_code' => $item['account_code']],
                array_merge($item, [
                    'receivables'  => 0.00,
                    'payables'     => 0.00,
                    'trn'          => null,
                    'opening_date' => $now,
                    'credit_limit' => 0.00,
                    'remarks'      => null,
                    'address'      => null,
                    'contact_no'   => null,
                    'created_by'   => $userId,
                    'updated_by'   => $userId,
                ])
            );
        }

        // ── Vendors (Accounts Payable) ──────────────────────────────────────────
        // 4 dummy vendors with sample contact details, TRN, and credit terms

        $vendorData = [
            [
                'account_code' => '205001',
                'name'         => 'Al Fardan Gold Trading LLC',
                'address'      => 'Shop 12, Gold Souq, Deira, Dubai, UAE',
                'contact_no'   => '+971-4-2345001',
                'trn'          => '100123456700001',
                'credit_limit' => 50000.00,
                'remarks'      => 'Primary gold bullion supplier',
            ],
            [
                'account_code' => '205002',
                'name'         => 'Sunrise Diamond Co.',
                'address'      => 'Office 304, Almas Tower, JLT, Dubai, UAE',
                'contact_no'   => '+971-4-2345002',
                'trn'          => '100123456700002',
                'credit_limit' => 75000.00,
                'remarks'      => 'Loose diamonds and certified stones',
            ],
            [
                'account_code' => '205003',
                'name'         => 'Royal Karat Manufacturing',
                'address'      => 'Warehouse 7, Al Quoz Industrial Area 3, Dubai, UAE',
                'contact_no'   => '+971-4-2345003',
                'trn'          => '100123456700003',
                'credit_limit' => 30000.00,
                'remarks'      => 'Custom jewelry manufacturing & making charges',
            ],
            [
                'account_code' => '205004',
                'name'         => 'Pearl Coast Findings & Parts',
                'address'      => 'Shop 45, Hamdan Center, Abu Dhabi, UAE',
                'contact_no'   => '+971-2-2345004',
                'trn'          => '100123456700004',
                'credit_limit' => 20000.00,
                'remarks'      => 'Clasps, chains, and jewelry findings supplier',
            ],
        ];

        foreach ($vendorData as $item) {
            ChartOfAccounts::firstOrCreate(
                ['account_code' => $item['account_code']],
                [
                    'shoa_id'      => 6,
                    'name'         => $item['name'],
                    'account_type' => 'vendor',
                    'receivables'  => 0.00,
                    'payables'     => 0.00,
                    'trn'          => $item['trn'],
                    'opening_date' => $now,
                    'credit_limit' => $item['credit_limit'],
                    'remarks'      => $item['remarks'],
                    'address'      => $item['address'],
                    'contact_no'   => $item['contact_no'],
                    'created_by'   => $userId,
                    'updated_by'   => $userId,
                ]
            );
        }

        // ── Customers (Accounts Receivable) ─────────────────────────────────────
        // 4 dummy customers with sample contact details and TRN

        $customerData = [
            [
                'account_code' => '103001',
                'name'         => 'Ahmed Al Mansoori',
                'address'      => 'Villa 23, Jumeirah 1, Dubai, UAE',
                'contact_no'   => '+971-50-1112001',
                'trn'          => '100987654300001',
                'credit_limit' => 15000.00,
                'remarks'      => 'Regular retail customer',
            ],
            [
                'account_code' => '103002',
                'name'         => 'Fatima Hussain',
                'address'      => 'Apt 1204, Marina Tower, Dubai Marina, Dubai, UAE',
                'contact_no'   => '+971-50-1112002',
                'trn'          => '100987654300002',
                'credit_limit' => 10000.00,
                'remarks'      => 'Walk-in customer, prefers gold jewelry',
            ],
            [
                'account_code' => '103003',
                'name'         => 'Mohammed Rashid Trading',
                'address'      => 'Office 9, Al Maktoum Road, Deira, Dubai, UAE',
                'contact_no'   => '+971-4-3334003',
                'trn'          => '100987654300003',
                'credit_limit' => 40000.00,
                'remarks'      => 'Wholesale customer, bulk orders',
            ],
            [
                'account_code' => '103004',
                'name'         => 'Layla Karimi',
                'address'      => 'Villa 8, Al Barsha 2, Dubai, UAE',
                'contact_no'   => '+971-50-1112004',
                'trn'          => '100987654300004',
                'credit_limit' => 8000.00,
                'remarks'      => 'Prefers diamond jewelry and custom orders',
            ],
        ];

        foreach ($customerData as $item) {
            ChartOfAccounts::firstOrCreate(
                ['account_code' => $item['account_code']],
                [
                    'shoa_id'      => 4,
                    'name'         => $item['name'],
                    'account_type' => 'customer',
                    'receivables'  => 0.00,
                    'payables'     => 0.00,
                    'trn'          => $item['trn'],
                    'opening_date' => $now,
                    'credit_limit' => $item['credit_limit'],
                    'remarks'      => $item['remarks'],
                    'address'      => $item['address'],
                    'contact_no'   => $item['contact_no'],
                    'created_by'   => $userId,
                    'updated_by'   => $userId,
                ]
            );
        }

        // ── Purities ───────────────────────────────────────────────────────────

        $purities = [
            ['label' => '24K (99.9%)', 'value' => 0.999, 'sort_order' => 1],
            ['label' => '22K (92%)',   'value' => 0.92,  'sort_order' => 2],
            ['label' => '21K (88%)',   'value' => 0.88,  'sort_order' => 3],
            ['label' => '18K (75%)',   'value' => 0.75,  'sort_order' => 4],
            ['label' => '14K (60%)',   'value' => 0.60,  'sort_order' => 5],
            ['label' => '9K (37.5%)',  'value' => 0.375, 'sort_order' => 6],
        ];

        foreach ($purities as $row) {
            Purity::firstOrCreate(['value' => $row['value']], $row);
        }

        // ── Measurement Units ──────────────────────────────────────────────────

        $units = [
            ['id' => 1, 'name' => 'Carat',      'shortcode' => 'ct'],
            ['id' => 2, 'name' => 'Milligram',  'shortcode' => 'mg'],
            ['id' => 3, 'name' => 'Kilogram',   'shortcode' => 'kg'],
            ['id' => 4, 'name' => 'Gram',       'shortcode' => 'g'],
            ['id' => 5, 'name' => 'Tola',       'shortcode' => 'tola'],
            ['id' => 6, 'name' => 'Karat',      'shortcode' => 'K'],
            ['id' => 7, 'name' => 'Millimeter', 'shortcode' => 'mm'],
            ['id' => 8, 'name' => 'Pieces',     'shortcode' => 'pcs'],
        ];

        foreach ($units as $unit) {
            MeasurementUnit::firstOrCreate(['id' => $unit['id']], $unit);
        }

        // ── Product Categories ─────────────────────────────────────────────────

        $categories = [
            ['id' =>  1, 'name' => 'Diamond',  'code' => 'DIAM'],
            ['id' =>  2, 'name' => 'Gold',     'code' => 'GOLD'],
            ['id' =>  3, 'name' => 'Stone',    'code' => 'STON'],
            ['id' =>  4, 'name' => 'Chain',    'code' => 'CHAI'],
            ['id' =>  5, 'name' => 'Ring',     'code' => 'RING'],
            ['id' =>  6, 'name' => 'Earing',   'code' => 'EARI'],
            ['id' =>  7, 'name' => 'Pendent',  'code' => 'PEND'],
            ['id' =>  8, 'name' => 'Bracelet', 'code' => 'BRAC'],
            ['id' =>  9, 'name' => 'Bangel',   'code' => 'BANG'],
            ['id' => 10, 'name' => 'Necklace', 'code' => 'NECK'],
        ];

        foreach ($categories as $cat) {
            ProductCategory::firstOrCreate(
                ['id' => $cat['id']],
                array_merge($cat, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // ── Product Sub Categories ─────────────────────────────────────────────

        $subCategories = [
            // Diamond
            ['category_id' => 1, 'name' => 'Diamond I',     'code' => 'DIAM-I'],
            ['category_id' => 1, 'name' => 'Diamond SI',    'code' => 'DIAM-SI'],
            ['category_id' => 1, 'name' => 'Diamond VS-SI', 'code' => 'DIAM-SI-VS'],
            // Gold
            ['category_id' => 2, 'name' => 'WG 14 CR.', 'code' => 'GOLD-WG14'],
            ['category_id' => 2, 'name' => 'WG 18 CR.', 'code' => 'GOLD-WG18'],
            ['category_id' => 2, 'name' => 'PG 14 CR.', 'code' => 'GOLD-PG14'],
            ['category_id' => 2, 'name' => 'PG 18 CR.', 'code' => 'GOLD-PG18'],
            ['category_id' => 2, 'name' => 'YG 14 CR.', 'code' => 'GOLD-YG14'],
            ['category_id' => 2, 'name' => 'YG 18 CR.', 'code' => 'GOLD-YG18'],
            // Stone
            ['category_id' => 3, 'name' => 'Stone 1', 'code' => 'STON-1'],
            // Chain
            ['category_id' => 4, 'name' => 'WG 18 Cr', 'code' => 'CHAI-WG18'],
            ['category_id' => 4, 'name' => 'YG 18 Cr', 'code' => 'CHAI-YG18'],
            ['category_id' => 4, 'name' => 'PG 18 Cr', 'code' => 'CHAI-PG18'],
            ['category_id' => 4, 'name' => 'WG 14 Cr', 'code' => 'CHAI-WG14'],
            ['category_id' => 4, 'name' => 'YG 14 Cr', 'code' => 'CHAI-YG14'],
            ['category_id' => 4, 'name' => 'PG 14 Cr', 'code' => 'CHAI-PG14'],
        ];

        foreach ($subCategories as $sub) {
            ProductSubcategory::firstOrCreate(
                ['code' => $sub['code']],
                array_merge($sub, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // ── Attributes ─────────────────────────────────────────────────────────

        $shape = Attribute::firstOrCreate(
            ['slug' => Str::slug('Shape')],
            ['name' => 'Shape']
        );

        $shapeValues = [
            'Round', 'Princess', 'Emerald', 'Asscher', 'Marquise',
            'Oval', 'Radiant', 'Pear', 'Cushion', 'Heart',
            'Baguette', 'Trillion', 'Rose Cut', 'Old Mine Cut', 'Cabochon',
        ];
        foreach ($shapeValues as $value) {
            AttributeValue::firstOrCreate(['attribute_id' => $shape->id, 'value' => $value]);
        }

        $size = Attribute::firstOrCreate(
            ['slug' => Str::slug('Size')],
            ['name' => 'Size']
        );

        $sizeValues = [
            '0.01 ct (1.0 mm)', '0.05 ct (2.5 mm)', '0.10 ct (3.0 mm)',
            '0.25 ct (4.0 mm)', '0.50 ct (5.0 mm)', '0.75 ct (5.8 mm)',
            '1.00 ct (6.5 mm)', '1.50 ct (7.5 mm)', '2.00 ct (8.2 mm)', '3.00 ct (9.5 mm)',
            'Size 10', 'Size 12', 'Size 14', 'Size 16', 'Size 18',
            'Size 20', 'Size 22', 'Size 24', 'Size 26', 'Size 28',
            '14 cm', '16 cm', '18 cm', '20 cm', '22 cm', '24 cm',
            '55 mm', '57 mm', '60 mm', '62 mm', '65 mm', '67 mm', '70 mm',
        ];
        foreach ($sizeValues as $value) {
            AttributeValue::firstOrCreate(['attribute_id' => $size->id, 'value' => $value]);
        }
    }
}