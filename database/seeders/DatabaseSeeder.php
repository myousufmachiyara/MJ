<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
        $now = now();
        $userId = 1;

        // 🔑 Create Super Admin User
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('12345678'),
            ]
        );

        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->assignRole($superAdmin);

        $managerUser = User::firstOrCreate(
            ['username' => 'm.kashif'],
            [
                'name' => 'M.Kashif',
                'email' => null,
                'password' => Hash::make('12345678'),
            ]
        );

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerUser->assignRole($managerRole);

        // 📌 Functional Modules
        $modules = [
            'user_roles', 'users',
            'coa', 'shoa',
            'products', 'product_categories', 'product_subcategories', 'attributes',
            'purities',
            'locations', 'stock_transfer',
            'purchase_invoices', 'purchase_return',
            'sale_invoices', 'sale_return',
            'payment_vouchers', 'vouchers',
            'production', 'production_receiving', 'production_return',
        ];

        $managerModules = [
            'coa', 'shoa',
            'products', 'product_categories', 'product_subcategories', 'attributes',
            'purities',
            'purchase_invoices', 'purchase_return',
            'sale_invoices', 'sale_return',
            'payment_vouchers', 'vouchers',
        ];

        $actions = ['index', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$module.$action"]);
            }
        }

        $managerPermissions = [];
        foreach ($managerModules as $module) {
            foreach ($actions as $action) {
                $managerPermissions[] = "$module.$action";
            }
        }

        $reports = ['inventory', 'purchase', 'production', 'sales', 'accounts'];
        foreach ($reports as $report) {
            Permission::firstOrCreate(['name' => "reports.$report"]);
        }

        $reportPermissions = ['reports.inventory', 'reports.purchase', 'reports.sales', 'reports.accounts'];

        $superAdmin->syncPermissions(Permission::all());
        $managerRole->syncPermissions(
            Permission::whereIn('name', array_merge($managerPermissions, $reportPermissions))->get()
        );

        // ---------------------
        // HEADS OF ACCOUNTS
        // ---------------------
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses',    'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // SUB HEADS
        // ---------------------
        SubHeadOfAccounts::insert([
            ['id' =>  1, 'hoa_id' => 1, 'name' => 'Cash & Cash Equivalents',     'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'hoa_id' => 1, 'name' => 'Bank Accounts',               'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'hoa_id' => 1, 'name' => 'Inventory',                   'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'hoa_id' => 1, 'name' => 'Accounts Receivable',         'created_at' => $now, 'updated_at' => $now],
            ['id' =>  5, 'hoa_id' => 1, 'name' => 'VAT Recoverable (Input Tax)', 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'hoa_id' => 2, 'name' => 'Accounts Payable',            'created_at' => $now, 'updated_at' => $now],
            ['id' =>  7, 'hoa_id' => 2, 'name' => 'VAT Payable (Output Tax)',    'created_at' => $now, 'updated_at' => $now],
            ['id' =>  8, 'hoa_id' => 3, 'name' => 'Owner Capital',               'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'hoa_id' => 3, 'name' => 'Retained Earnings',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'hoa_id' => 4, 'name' => 'Sales Revenue',               'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'hoa_id' => 4, 'name' => 'Service Income',              'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'hoa_id' => 5, 'name' => 'Material Purchases',          'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'hoa_id' => 5, 'name' => 'Manufacturing & Making',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'hoa_id' => 5, 'name' => 'Operating Expenses',          'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // CHART OF ACCOUNTS
        // ---------------------
        $coaData = [
            // Assets
            ['account_code' => '101001', 'shoa_id' =>  1, 'name' => 'Cash in Hand',                 'account_type' => 'cash'],
            ['account_code' => '102001', 'shoa_id' =>  2, 'name' => 'Meezan Bank',                  'account_type' => 'bank'],
            ['account_code' => '102002', 'shoa_id' =>  2, 'name' => 'HBL Bank',                     'account_type' => 'bank'],
            ['account_code' => '104001', 'shoa_id' =>  3, 'name' => 'Gold Inventory',               'account_type' => 'asset'],
            ['account_code' => '104002', 'shoa_id' =>  3, 'name' => 'Diamond Inventory',            'account_type' => 'asset'],
            ['account_code' => '104003', 'shoa_id' =>  3, 'name' => 'Parts Inventory',              'account_type' => 'asset'],
            ['account_code' => '103001', 'shoa_id' =>  4, 'name' => 'Customer 01',                  'account_type' => 'customer'],
            ['account_code' => '103002', 'shoa_id' =>  4, 'name' => 'Customer 02',                  'account_type' => 'customer'],
            ['account_code' => '105001', 'shoa_id' =>  5, 'name' => 'VAT Input Tax Recoverable',    'account_type' => 'asset'],
            // Liabilities
            ['account_code' => '205001', 'shoa_id' =>  6, 'name' => 'Vendor 01',                    'account_type' => 'vendor'],
            ['account_code' => '205002', 'shoa_id' =>  6, 'name' => 'Vendor 02',                    'account_type' => 'vendor'],
            ['account_code' => '208001', 'shoa_id' =>  7, 'name' => 'Output VAT Payable',           'account_type' => 'liability'],
            // Equity
            ['account_code' => '301001', 'shoa_id' =>  8, 'name' => 'Owner Capital',                'account_type' => 'equity'],
            ['account_code' => '302001', 'shoa_id' =>  9, 'name' => 'Retained Earnings',            'account_type' => 'equity'],
            // Revenue
            ['account_code' => '401001', 'shoa_id' => 10, 'name' => 'Gold Sales Revenue',           'account_type' => 'revenue'],
            ['account_code' => '401002', 'shoa_id' => 10, 'name' => 'Diamond Sales Revenue',        'account_type' => 'revenue'],
            ['account_code' => '402001', 'shoa_id' => 11, 'name' => 'Making Charges Income',        'account_type' => 'revenue'],
            ['account_code' => '403001', 'shoa_id' => 10, 'name' => 'Parts Sales Revenue',          'account_type' => 'revenue'],
            // Expenses
            ['account_code' => '510001', 'shoa_id' => 12, 'name' => 'Material Purchases (Gold)',    'account_type' => 'expenses'],
            ['account_code' => '510002', 'shoa_id' => 12, 'name' => 'Material Purchases (Diamond)', 'account_type' => 'expenses'],
            ['account_code' => '510003', 'shoa_id' => 13, 'name' => 'Making Charges Expense',       'account_type' => 'expenses'],
            ['account_code' => '510004', 'shoa_id' => 12, 'name' => 'Parts Purchases',              'account_type' => 'expenses'],
            ['account_code' => '601001', 'shoa_id' => 14, 'name' => 'Salaries & Wages',             'account_type' => 'expenses'],
            ['account_code' => '601002', 'shoa_id' => 14, 'name' => 'Rent Expense',                 'account_type' => 'expenses'],
            ['account_code' => '601003', 'shoa_id' => 14, 'name' => 'Utilities Expense',            'account_type' => 'expenses'],
            ['account_code' => '601004', 'shoa_id' => 14, 'name' => 'Miscellaneous Expense',        'account_type' => 'expenses'],
        ];

        foreach ($coaData as $item) {
            ChartOfAccounts::create(array_merge($item, [
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
            ]));
        }

        // ---------------------
        // PURITIES
        // ---------------------
        $purities = [
            ['label' => '24K (99.9%)', 'value' => 0.99, 'sort_order' => 1],
            ['label' => '22K (92%)',   'value' => 0.92, 'sort_order' => 2],
            ['label' => '21K (88%)',   'value' => 0.88, 'sort_order' => 3],
            ['label' => '18K (75%)',   'value' => 0.75, 'sort_order' => 4],
            ['label' => '14K (60%)',   'value' => 0.60, 'sort_order' => 5],
            ['label' => '9K (37.5%)', 'value' => 0.37, 'sort_order' => 6],
        ];

        foreach ($purities as $row) {
            Purity::firstOrCreate(['value' => $row['value']], $row);
        }

        // ---------------------
        // MEASUREMENT UNITS
        // ---------------------
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Carat',      'shortcode' => 'ct'],
            ['id' => 2, 'name' => 'Milligram',  'shortcode' => 'mg'],
            ['id' => 3, 'name' => 'Kilogram',   'shortcode' => 'kg'],
            ['id' => 4, 'name' => 'Gram',       'shortcode' => 'g'],
            ['id' => 5, 'name' => 'Tola',       'shortcode' => 'tola'],
            ['id' => 6, 'name' => 'Karat',      'shortcode' => 'K'],
            ['id' => 7, 'name' => 'Millimeter', 'shortcode' => 'mm'],
            ['id' => 8, 'name' => 'Pieces',     'shortcode' => 'pcs'],
        ]);

        // ---------------------
        // PRODUCT CATEGORIES
        // ---------------------
        ProductCategory::insert([
            ['id' =>  1, 'name' => 'Diamond',  'code' => 'DIAM', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  2, 'name' => 'Gold',     'code' => 'GOLD', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  3, 'name' => 'Stone',    'code' => 'STON', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  4, 'name' => 'Chain',    'code' => 'CHAI', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  5, 'name' => 'Ring',     'code' => 'RING', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  6, 'name' => 'Earing',   'code' => 'EARI', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  7, 'name' => 'Pendent',  'code' => 'PEND', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  8, 'name' => 'Bracelet', 'code' => 'BRAC', 'created_at' => now(), 'updated_at' => now()],
            ['id' =>  9, 'name' => 'Bangel',   'code' => 'BANG', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Necklace', 'code' => 'NECK', 'created_at' => now(), 'updated_at' => now()],
        ]);

        ProductSubcategory::insert([
            // Diamond
            ['category_id' => 1, 'name' => 'Diamond I',     'code' => 'DIAM-I',     'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 1, 'name' => 'Diamond SI',    'code' => 'DIAM-SI',    'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 1, 'name' => 'Diamond VS-SI', 'code' => 'DIAM-SI-VS', 'created_at' => now(), 'updated_at' => now()],
            // Gold
            ['category_id' => 2, 'name' => 'WG 14 CR.', 'code' => 'GOLD-WG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'WG 18 CR.', 'code' => 'GOLD-WG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'PG 14 CR.', 'code' => 'GOLD-PG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'PG 18 CR.', 'code' => 'GOLD-PG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'YG 14 CR.', 'code' => 'GOLD-YG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'YG 18 CR.', 'code' => 'GOLD-YG18', 'created_at' => now(), 'updated_at' => now()],
            // Stone
            ['category_id' => 3, 'name' => 'Stone 1', 'code' => 'STON-1', 'created_at' => now(), 'updated_at' => now()],
            // Chain
            ['category_id' => 4, 'name' => 'WG 18 Cr', 'code' => 'CHAI-WG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'YG 18 Cr', 'code' => 'CHAI-YG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'PG 18 Cr', 'code' => 'CHAI-PG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'WG 14 Cr', 'code' => 'CHAI-WG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'YG 14 Cr', 'code' => 'CHAI-YG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'PG 14 Cr', 'code' => 'CHAI-PG14', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ---------------------
        // ATTRIBUTES
        // ---------------------
        $shape = Attribute::create(['name' => 'Shape', 'slug' => Str::slug('Shape')]);

        foreach ([
            'Round', 'Princess', 'Emerald', 'Asscher', 'Marquise',
            'Oval', 'Radiant', 'Pear', 'Cushion', 'Heart',
            'Baguette', 'Trillion', 'Rose Cut', 'Old Mine Cut', 'Cabochon',
        ] as $s) {
            AttributeValue::create(['attribute_id' => $shape->id, 'value' => $s]);
        }

        $size = Attribute::create(['name' => 'Size', 'slug' => Str::slug('Size')]);

        foreach ([
            '0.01 ct (1.0 mm)', '0.05 ct (2.5 mm)', '0.10 ct (3.0 mm)',
            '0.25 ct (4.0 mm)', '0.50 ct (5.0 mm)', '0.75 ct (5.8 mm)',
            '1.00 ct (6.5 mm)', '1.50 ct (7.5 mm)', '2.00 ct (8.2 mm)', '3.00 ct (9.5 mm)',
            'Size 10', 'Size 12', 'Size 14', 'Size 16', 'Size 18',
            'Size 20', 'Size 22', 'Size 24', 'Size 26', 'Size 28',
            '14 cm', '16 cm', '18 cm', '20 cm', '22 cm', '24 cm',
            '55 mm', '57 mm', '60 mm', '62 mm', '65 mm', '67 mm', '70 mm',
        ] as $sz) {
            AttributeValue::create(['attribute_id' => $size->id, 'value' => $sz]);
        }
    }
}