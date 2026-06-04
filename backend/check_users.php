<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('username', 'admin')->first();
if ($admin) {
    $check = Hash::check('admin123', $admin->password);
    echo "Password check for 'admin123': " . ($check ? "MATCH" : "NO MATCH") . "\n";
    echo "Current hash: " . $admin->password . "\n";

    if (!$check) {
        $admin->password = bcrypt('admin123');
        $admin->save();
        echo "\nPassword reset done.\n";
        $check2 = Hash::check('admin123', $admin->fresh()->password);
        echo "Password check after reset: " . ($check2 ? "MATCH" : "NO MATCH") . "\n";
    }
} else {
    echo "User 'admin' not found.\n";
}
