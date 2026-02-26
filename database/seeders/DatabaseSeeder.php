<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@capstone.com'],
            [
                'name'      => 'Admin User',
                'password'  => Hash::make('admin123'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // Create cashier user
        User::firstOrCreate(
            ['email' => 'cashier@capstone.com'],
            [
                'name'      => 'Cashier User',
                'password'  => Hash::make('cashier123'),
                'role'      => 'cashier',
                'is_active' => true,
            ]
        );

        // Create categories
        $categories = ['Computers', 'Peripherals', 'Accessories', 'Software', 'Networking'];
        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat]);
        }

        // Create sample products
        $products = [
            ['name' => 'Desktop PC',         'category' => 'Computers',   'price' => 25000, 'stock' => 10],
            ['name' => 'Laptop',             'category' => 'Computers',   'price' => 35000, 'stock' => 8],
            ['name' => 'Mechanical Keyboard','category' => 'Peripherals', 'price' => 2500,  'stock' => 20],
            ['name' => 'Gaming Mouse',       'category' => 'Peripherals', 'price' => 1500,  'stock' => 25],
            ['name' => 'Monitor 24"',        'category' => 'Peripherals', 'price' => 8000,  'stock' => 12],
            ['name' => 'USB Hub',            'category' => 'Accessories', 'price' => 800,   'stock' => 30],
            ['name' => 'HDMI Cable',         'category' => 'Accessories', 'price' => 350,   'stock' => 50],
            ['name' => 'Antivirus 1yr',      'category' => 'Software',    'price' => 1200,  'stock' => 15],
            ['name' => 'WiFi Router',        'category' => 'Networking',  'price' => 3500,  'stock' => 10],
            ['name' => 'Network Switch',     'category' => 'Networking',  'price' => 2800,  'stock' => 7],
        ];

        foreach ($products as $p) {
            $category = Category::where('name', $p['category'])->first();
            if ($category) {
                Product::firstOrCreate(
                    ['name' => $p['name']],
                    [
                        'category_id'    => $category->id,
                        'price'          => $p['price'],
                        'stock_quantity' => $p['stock'],
                        'is_active'      => true,
                    ]
                );
            }
        }
    }
}
