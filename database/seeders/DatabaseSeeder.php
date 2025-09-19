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


        // 📏 Measurement Units
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Carat', 'shortcode' => 'ct'],
            ['id' => 2, 'name' => 'Milligram', 'shortcode' => 'mg'],
            ['id' => 3, 'name' => 'Kilogram', 'shortcode' => 'kg'],
            ['id' => 4, 'name' => 'Gram', 'shortcode' => 'g'],
            ['id' => 5, 'name' => 'Tola', 'shortcode' => 'tola'],
            ['id' => 6, 'name' => 'Karat', 'shortcode' => 'K'],
            ['id' => 7, 'name' => 'Millimeter', 'shortcode' => 'mm'],
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
