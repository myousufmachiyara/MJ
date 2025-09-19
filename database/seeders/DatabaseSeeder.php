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

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();

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
            'attributes',

            // Stock Management
            'locations',
            'stock_transfer',

            // Purchases
            'purchase_invoices',
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
        // 1️⃣ Heads of Accounts
        // ---------------------        
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets'],
            ['id' => 2, 'name' => 'Liabilities'],
            ['id' => 3, 'name' => 'Expenses'],
            ['id' => 4, 'name' => 'Revenue'],
            ['id' => 5, 'name' => 'Equity'],
        ]);

        // ---------------------
        // 2️⃣ Sub Heads
        // ---------------------
        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1, 'name' => "Current Assets"],      
            ['id' => 2, 'hoa_id' => 1, 'name' => "Inventory"],           
            ['id' => 3, 'hoa_id' => 2, 'name' => "Current Liabilities"], 
            ['id' => 4, 'hoa_id' => 2, 'name' => "Long-Term Liabilities"], 
            ['id' => 5, 'hoa_id' => 4, 'name' => "Sales"],               
            ['id' => 6, 'hoa_id' => 3, 'name' => "Expenses"],            
            ['id' => 7, 'hoa_id' => 5, 'name' => "Equity"],              
        ]);

        // ---------------------
        // 3️⃣ Chart of Accounts
        // ---------------------
        ChartOfAccounts::insert([
            // Assets
            ['id'=>1, 'shoa_id'=>1, 'account_code'=>'101001','name'=>"Cash",'account_type'=>"asset",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Asset",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>2, 'shoa_id'=>1, 'account_code'=>'101002','name'=>"Bank",'account_type'=>"asset",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Asset",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>3, 'shoa_id'=>1, 'account_code'=>'101003','name'=>"Accounts Receivable",'account_type'=>"asset",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Customer Accounts",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>5, 'shoa_id'=>2, 'account_code'=>'102001','name'=>"Raw Material Inventory",'account_type'=>"asset",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Inventory",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>7, 'shoa_id'=>2, 'account_code'=>'102002','name'=>"WIP Inventory",'account_type'=>"asset",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Work-in-Progress Inventory",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>8, 'shoa_id'=>2, 'account_code'=>'102003','name'=>"Finished Goods Inventory",'account_type'=>"asset",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Finished Goods Inventory",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Liabilities
            ['id'=>4, 'shoa_id'=>3, 'account_code'=>'201001','name'=>"Accounts Payable",'account_type'=>"liability",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Supplier Accounts",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>9, 'shoa_id'=>4, 'account_code'=>'202001','name'=>"Long-Term Loans",'account_type'=>"liability",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Long-Term Liabilities",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Expenses
            ['id'=>6, 'shoa_id'=>6, 'account_code'=>'301001','name'=>"Expense Account",'account_type'=>"expense",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Expense",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>10,'shoa_id'=>6,'account_code'=>'301002','name'=>"Raw Material Expense",'account_type'=>"expense",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Raw Material Cost",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>11,'shoa_id'=>6,'account_code'=>'301003','name'=>"Labor / Wages Expense",'account_type'=>"expense",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Labor Cost",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>12,'shoa_id'=>6,'account_code'=>'301004','name'=>"Production Overheads",'account_type'=>"expense",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Overhead Cost",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Revenue
            ['id'=>13,'shoa_id'=>5,'account_code'=>'401001','name'=>"Sales",'account_type'=>"revenue",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Production Sales",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>14,'shoa_id'=>5,'account_code'=>'401002','name'=>"Other Income",'account_type'=>"revenue",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Other Income",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Equity
            ['id'=>15,'shoa_id'=>7,'account_code'=>'501001','name'=>"Equity",'account_type'=>"equity",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Owner Equity",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>16,'shoa_id'=>7,'account_code'=>'501002','name'=>"Capital",'account_type'=>"equity",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Capital Account",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>17,'shoa_id'=>7,'account_code'=>'501003','name'=>"Retained Earnings",'account_type'=>"equity",'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Retained Earnings",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
        ]);


        // 🏷️ Seed Attributes
        Attribute::insert([
            ['id' => 1, 'name' => 'SIZE', 'slug' => 'SIZE', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'COLOR', 'slug' => 'COLOR', 'deleted_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        AttributeValue::insert([
            // SIZE attribute_id = 1
            ['id' => 1, 'attribute_id' => 1, 'value' => 'XX-SMALL', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 2, 'attribute_id' => 1, 'value' => 'X-SMALL',  'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 3, 'attribute_id' => 1, 'value' => 'SMALL',    'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 4, 'attribute_id' => 1, 'value' => 'MEDIUM',   'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 5, 'attribute_id' => 1, 'value' => 'LARGE',    'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 6, 'attribute_id' => 1, 'value' => 'XL',       'created_at' => '2025-07-11 18:22:55', 'updated_at' => '2025-07-11 18:22:55'],
            ['id' => 7, 'attribute_id' => 1, 'value' => 'X-LARGE',  'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 8, 'attribute_id' => 1, 'value' => 'XX-LARGE', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 9, 'attribute_id' => 1, 'value' => '3X-LARGE', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],
            ['id' => 10,'attribute_id' => 1, 'value' => '4X-LARGE', 'created_at' => '2025-07-11 19:31:15', 'updated_at' => '2025-07-11 19:31:15'],

            // COLOR attribute_id = 2
            ['id' => 11, 'attribute_id' => 2, 'value' => 'BLACK',    'created_at' => '2025-07-11 18:23:46', 'updated_at' => '2025-07-11 18:23:46'],
            ['id' => 12, 'attribute_id' => 2, 'value' => 'RED',      'created_at' => '2025-07-11 18:23:46', 'updated_at' => '2025-07-11 18:23:46'],
            ['id' => 13, 'attribute_id' => 2, 'value' => 'BLUE',     'created_at' => '2025-07-11 18:23:46', 'updated_at' => '2025-07-11 18:23:46'],
            ['id' => 14, 'attribute_id' => 2, 'value' => 'BROWN',    'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 15, 'attribute_id' => 2, 'value' => 'BURGUNDY', 'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 16, 'attribute_id' => 2, 'value' => 'COGNAC',   'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 17, 'attribute_id' => 2, 'value' => 'GREEN',    'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 18, 'attribute_id' => 2, 'value' => 'PINK',     'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 19, 'attribute_id' => 2, 'value' => 'PURPLE',   'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 20, 'attribute_id' => 2, 'value' => 'RUB',      'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 21, 'attribute_id' => 2, 'value' => 'TAN',      'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 22, 'attribute_id' => 2, 'value' => 'WHITE',    'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
            ['id' => 23, 'attribute_id' => 2, 'value' => 'YELLOW',   'created_at' => '2025-07-11 19:16:09', 'updated_at' => '2025-07-11 19:16:09'],
        ]);

        // 📦 Product Categories
        ProductCategory::insert([
            ['id' => 1, 'name' => 'Raw Leather', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 18:04:58', 'updated_at' => '2025-07-11 18:04:58'],
            ['id' => 2, 'name' => 'Women Jackets', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:04:47', 'updated_at' => '2025-07-11 19:04:47'],
            ['id' => 3, 'name' => 'Bags', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:05:02', 'updated_at' => '2025-07-11 19:05:02'],
            ['id' => 4, 'name' => 'Skirts', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:05:16', 'updated_at' => '2025-07-11 19:05:16'],
            ['id' => 5, 'name' => 'Mens Jackets', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:05:32', 'updated_at' => '2025-07-11 19:05:32'],
            ['id' => 6, 'name' => 'Pant', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:10:57', 'updated_at' => '2025-07-11 19:10:57'],
            ['id' => 7, 'name' => 'Wallets', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:11:23', 'updated_at' => '2025-07-11 19:11:23'],
            ['id' => 8, 'name' => 'Rugs', 'description' => null, 'status' => 'active', 'created_at' => '2025-07-11 19:11:33', 'updated_at' => '2025-07-11 19:11:33'],
        ]);

        // 📏 Measurement Units
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Piece', 'shortcode' => 'pcs'],
            ['id' => 2, 'name' => 'Meter', 'shortcode' => 'm'],
            ['id' => 3, 'name' => 'Square Feet', 'shortcode' => 'sq.ft'],
            ['id' => 4, 'name' => 'Yards', 'shortcode' => 'yrds'],
        ]);

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
