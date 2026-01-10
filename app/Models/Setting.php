<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    /**
     * Helper to get Asset URL from Cloudinary or Local storage.
     */
    public static function asset($path)
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Check if Cloudinary is configured
        $cloudName = config('filesystems.disks.cloudinary.cloud') ?: env('CLOUDINARY_CLOUD_NAME');
        $cloudUrl = config('cloudinary.cloud_url') ?: env('CLOUDINARY_URL');

        if ($cloudName && (config('filesystems.default') === 'cloudinary' || $cloudUrl)) {
            // If it's a chat document, use local storage as requested
            if (str_contains($path, 'chat/documents/')) {
                return asset('storage/' . $path);
            }

            // Determine resource type: images are 'image', videos are 'video'
            $resourceType = (preg_match('/\.(mp4|mov|avi|wmv|mkv)$/i', $path)) ? 'video' : 'image';
            $cleanPath = ltrim($path, '/');

            return "https://res.cloudinary.com/{$cloudName}/{$resourceType}/upload/{$cleanPath}";
        }

        return asset('storage/' . $path);
    }
}
