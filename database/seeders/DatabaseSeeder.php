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
use App\Models\BarcodeSequence;
use App\Models\ProductSubcategory;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();
        $userId = 1; // ID for created_by / updated_by

        // 🔑 Create Super Admin User
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com', // optional, keep if you want for notifications
                'password' => Hash::make('12345678'),
            ]
        );

        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->assignRole($superAdmin);

        // 📌 Functional Modules (CRUD-style permissions)
        $modules = [
            // User Management
            'user_roles',
            'users',

            // Accounts
            'coa',
            'shoa',

            // Products
            'products',
            'product_categories',
            'product_subcategories',
            'attributes',

            // Stock Management
            'locations',
            'stock_transfer',

            // Purchases
            'purchase_invoices',
            'purchase_invoices_1',
            'purchase_return',

            // Sales
            'sale_invoices',
            'sale_return',

            // Vouchers
            'payment_vouchers',
            'vouchers',

            // Production
            'production',
            'production_receiving',
            'production_return',

        ];

        $actions = ['index', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "$module.$action",
                ]);
            }
        }

        // 📊 Report permissions (only view access, no CRUD)
        $reports = ['inventory', 'purchase', 'production', 'sales', 'accounts'];

        foreach ($reports as $report) {
            Permission::firstOrCreate([
                'name' => "reports.$report",
            ]);
        }

        // Assign all permissions to Superadmin
        $superAdmin->syncPermissions(Permission::all());

        // ---------------------
        // HEADS OF ACCOUNTS
        // ---------------------
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // SUB HEADS
        // ---------------------
        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1, 'name' => 'Cash', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'hoa_id' => 1, 'name' => 'Bank', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'hoa_id' => 1, 'name' => 'Accounts Receivable', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'hoa_id' => 1, 'name' => 'Inventory', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'hoa_id' => 2, 'name' => 'Accounts Payable', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'hoa_id' => 2, 'name' => 'Loans', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'hoa_id' => 3, 'name' => 'Owner Capital', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'hoa_id' => 4, 'name' => 'Sales', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'hoa_id' => 5, 'name' => 'Purchases', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10,'hoa_id' => 5, 'name' => 'Salaries', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11,'hoa_id' => 5, 'name' => 'Rent', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12,'hoa_id' => 5, 'name' => 'Utilities', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // CHART OF ACCOUNTS
        // ---------------------
        $coaData = [
            ['account_code' => 'A001', 'name' => 'Cash in Hand', 'account_type' => 'cash', 'shoa_id' => 1, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'A002', 'name' => 'Bank ABC', 'account_type' => 'bank', 'shoa_id' => 2, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'A003', 'name' => 'Customer A', 'account_type' => 'customer', 'shoa_id' => 3, 'receivables' => 1000, 'payables' => 0],
            ['account_code' => 'A004', 'name' => 'Inventory - Raw Material', 'account_type' => 'asset', 'shoa_id' => 4, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'L001', 'name' => 'Vendor X', 'account_type' => 'vendor', 'shoa_id' => 5, 'receivables' => 0, 'payables' => 500],
            ['account_code' => 'L002', 'name' => 'Bank Loan', 'account_type' => 'liability', 'shoa_id' => 6, 'receivables' => 0, 'payables' => 10000],
            ['account_code' => 'E001', 'name' => 'Owner Capital', 'account_type' => 'equity', 'shoa_id' => 7, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'R001', 'name' => 'Sales Income', 'account_type' => 'revenue', 'shoa_id' => 8, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'EX001', 'name' => 'Purchase of Goods', 'account_type' => 'expenses', 'shoa_id' => 9, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'EX002', 'name' => 'Salary Expense', 'account_type' => 'expenses', 'shoa_id' => 10, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'EX003', 'name' => 'Rent Expense', 'account_type' => 'expenses', 'shoa_id' => 11, 'receivables' => 0, 'payables' => 0],
            ['account_code' => 'EX004', 'name' => 'Utility Expense', 'account_type' => 'expenses', 'shoa_id' => 12, 'receivables' => 0, 'payables' => 0],
        ];

        foreach ($coaData as $data) {
            ChartOfAccounts::create(array_merge($data, [
                'opening_date' => $now,
                'credit_limit' => 0,
                'remarks' => null,
                'address' => null,
                'phone_no' => null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
        }


        // 📏 Measurement Units
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Carat', 'shortcode' => 'ct'],
            ['id' => 2, 'name' => 'Milligram', 'shortcode' => 'mg'],
            ['id' => 3, 'name' => 'Kilogram', 'shortcode' => 'kg'],
            ['id' => 4, 'name' => 'Gram', 'shortcode' => 'g'],
            ['id' => 5, 'name' => 'Tola', 'shortcode' => 'tola'],
            ['id' => 6, 'name' => 'Karat', 'shortcode' => 'K'],
            ['id' => 7, 'name' => 'Millimeter', 'shortcode' => 'mm'],
            ['id' => 8, 'name' => 'Pieces', 'shortcode' => 'pcs'],

        ]);

        // 📦 Product Categories
        ProductCategory::insert([
            ['id' => 1, 'name' => 'Diamond',   'code' => 'DIAM', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Gold',      'code' => 'GOLD', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Stone',     'code' => 'STON', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Chain',     'code' => 'CHAI', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Ring',      'code' => 'RING', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Earing',    'code' => 'EARI', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'Pendent',   'code' => 'PEND', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'Bracelet',  'code' => 'BRAC', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'Bangel',    'code' => 'BANG', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Necklace', 'code' => 'NECK', 'created_at' => now(), 'updated_at' => now()],
        ]);

        ProductSubcategory::insert([
            // 🔹 Diamond
            ['category_id' => 1, 'name' => 'Diamond I',     'code' => 'DIAM-I',     'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 1, 'name' => 'Diamond SI',    'code' => 'DIAM-SI',    'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 1, 'name' => 'Diamond VS-SI', 'code' => 'DIAM-SI-VS', 'created_at' => now(), 'updated_at' => now()],

            // 🔹 Gold
            ['category_id' => 2, 'name' => 'WG 14 CR.', 'code' => 'GOLD-WG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'WG 18 CR.', 'code' => 'GOLD-WG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'PG 14 CR.', 'code' => 'GOLD-PG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'PG 18 CR.', 'code' => 'GOLD-PG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'YG 14 CR.', 'code' => 'GOLD-YG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'YG 18 CR.', 'code' => 'GOLD-YG18', 'created_at' => now(), 'updated_at' => now()],

            // 🔹 Stone
            ['category_id' => 3, 'name' => 'Stone 1', 'code' => 'STON-1', 'created_at' => now(), 'updated_at' => now()],

            // 🔹 Chain
            ['category_id' => 4, 'name' => 'WG 18 Cr', 'code' => 'CHAI-WG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'YG 18 Cr', 'code' => 'CHAI-YG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'PG 18 Cr', 'code' => 'CHAI-PG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'WG 14 Cr', 'code' => 'CHAI-WG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'YG 14 Cr', 'code' => 'CHAI-YG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'PG 14 Cr', 'code' => 'CHAI-PG14', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $shape = Attribute::create([
            'name' => 'Shape',
            'slug' => Str::slug('Shape'),
        ]);

        $shapes = [
            'Round', 'Princess', 'Emerald', 'Asscher', 'Marquise',
            'Oval', 'Radiant', 'Pear', 'Cushion', 'Heart',
            'Baguette', 'Trillion', 'Rose Cut', 'Old Mine Cut', 'Cabochon'
        ];

        foreach ($shapes as $s) {
            AttributeValue::create([
                'attribute_id' => $shape->id,
                'value' => $s,
            ]);
        }

        // 2️⃣ Size Attribute
        $size = Attribute::create([
            'name' => 'Size',
            'slug' => Str::slug('Size'),
        ]);

        $sizes = [
            // Carat / mm for diamonds
            '0.01 ct (1.0 mm)', '0.05 ct (2.5 mm)', '0.10 ct (3.0 mm)',
            '0.25 ct (4.0 mm)', '0.50 ct (5.0 mm)', '0.75 ct (5.8 mm)',
            '1.00 ct (6.5 mm)', '1.50 ct (7.5 mm)', '2.00 ct (8.2 mm)', '3.00 ct (9.5 mm)',

            // Rings (UAE/GCC sizes)
            'Size 10', 'Size 12', 'Size 14', 'Size 16', 'Size 18', 'Size 20', 'Size 22', 'Size 24', 'Size 26', 'Size 28',

            // Chains / Bracelets (length)
            '14 cm', '16 cm', '18 cm', '20 cm', '22 cm', '24 cm',

            // Bangles (diameter mm)
            '55 mm', '57 mm', '60 mm', '62 mm', '65 mm', '67 mm', '70 mm',
        ];

        foreach ($sizes as $sz) {
            AttributeValue::create([
                'attribute_id' => $size->id,
                'value' => $sz,
            ]);
        }

        $sequences = [
            ['prefix' => 'GLOBAL', 'next_number' => 1],
            ['prefix' => 'FG', 'next_number' => 1],
            ['prefix' => 'RAW', 'next_number' => 1],
            ['prefix' => 'SRV', 'next_number' => 1],
            ['prefix' => 'PRD', 'next_number' => 1],
            ['prefix' => 'VAR', 'next_number' => 1],
        ];

        foreach ($sequences as $seq) {
            BarcodeSequence::firstOrCreate(
                ['prefix' => $seq['prefix']],
                ['next_number' => $seq['next_number']]
            );
        }
    }
}
