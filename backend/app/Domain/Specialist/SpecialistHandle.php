<?php

namespace App\Domain\Specialist;

use Illuminate\Support\Str;

class SpecialistHandle
{
    public static function generate(string $name): string
    {
        // Convert to lowercase and replace spaces with hyphens
        $handle = Str::lower(Str::slug($name));
        
        // Remove any special characters except hyphens
        $handle = preg_replace('/[^a-z0-9-]/', '', $handle);
        
        // Ensure it starts with a letter
        $handle = preg_replace('/^[0-9-]+/', '', $handle);
        
        // If empty after cleaning, use a default
        if (empty($handle)) {
            $handle = 'specialist';
        }
        
        return $handle;
    }

    public static function ensureUnique(string $baseHandle, callable $existsCallback): string
    {
        $handle = $baseHandle;
        $counter = 1;
        
        while ($existsCallback($handle)) {
            $handle = $baseHandle . '-' . $counter;
            $counter++;
        }
        
        return $handle;
    }

    public static function isValid(string $handle): bool
    {
        // Handle must be 3-30 characters
        if (strlen($handle) < 3 || strlen($handle) > 30) {
            return false;
        }
        
        // Only lowercase letters, numbers, and hyphens
        if (!preg_match('/^[a-z0-9-]+$/', $handle)) {
            return false;
        }
        
        // Must start with a letter
        if (!preg_match('/^[a-z]/', $handle)) {
            return false;
        }
        
        // Must end with a letter or number
        if (preg_match('/-$/', $handle)) {
            return false;
        }
        
        // No consecutive hyphens
        if (preg_match('/--/', $handle)) {
            return false;
        }
        
        return true;
    }

    public static function normalize(string $handle): string
    {
        return Str::lower(trim($handle));
    }
}
