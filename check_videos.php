<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::find(51);
if ($user) {
    echo 'User: ' . $user->name . PHP_EOL;
    foreach ($user->posts as $post) {
        if ($post->video) {
            echo 'Post ' . $post->id . ' Video: ' . $post->video . PHP_EOL;
        }
    }
} else {
    echo 'User 51 not found' . PHP_EOL;
}
