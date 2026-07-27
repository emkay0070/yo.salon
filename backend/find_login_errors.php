<?php
$lines = file('storage/logs/laravel.log');
foreach ($lines as $line) {
    if (strpos($line, 'Login failed') !== false) {
        echo $line;
    }
}
