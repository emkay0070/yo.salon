<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Exception;

class MediaService
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Upload a file and create a media record (transactional)
     */
    public function upload(UploadedFile $file, array $options = []): Media
    {
        $disk = $options['disk'] ?? 'public';
        $directory = $options['directory'] ?? 'uploads';
        $providerId = $options['provider_id'] ?? null;
        $salonId = $options['salon_id'] ?? null;
        $altText = $options['alt_text'] ?? null;
        $visibility = $options['visibility'] ?? Media::VISIBILITY_PUBLIC;

        // Uploader resolution
        $uploader = $options['uploader'] ?? auth()->user();
        $uploaderType = $uploader ? get_class($uploader) : null;
        $uploaderId = $uploader ? $uploader->getKey() : null;
        // Legacy fallback
        $uploadedBy = ($uploader instanceof \App\Models\User) ? $uploaderId : null;

        // Attachable resolution
        $attachable = $options['attachable'] ?? null;
        $attachableType = $attachable ? get_class($attachable) : null;
        $attachableId = $attachable ? $attachable->getKey() : null;

        // Generate unique filename
        $filename = $this->generateFilename($file);
        $path = $directory . '/' . $filename;

        // Get file info before storage
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Get image dimensions if it's an image (using native PHP for reliability)
        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/')) {
            try {
                // Use native PHP getimagesize() for dimension extraction
                // This is more reliable than Intervention Image for simple metadata
                $imageInfo = getimagesize($file->getRealPath());
                if ($imageInfo !== false) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                }
            } catch (Exception $e) {
                // If we can't read the image dimensions, continue without them
                // Log the error for debugging
                \Log::warning('Failed to read image dimensions', [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Use transaction to ensure atomicity
        return DB::transaction(function () use ($file, $disk, $directory, $filename, $path, $providerId, $salonId, $uploaderType, $uploaderId, $uploadedBy, $attachableType, $attachableId, $visibility, $altText, $mimeType, $size, $width, $height, $options) {
            // Store the file
            $file->storeAs($directory, $filename, $disk);

            // Create media record
            return Media::create([
                'provider_id' => $providerId,
                'salon_id' => $salonId,
                'uploaded_by' => $uploadedBy,
                'uploader_type' => $uploaderType,
                'uploader_id' => $uploaderId,
                'attachable_type' => $attachableType,
                'attachable_id' => $attachableId,
                'visibility' => $visibility,
                'disk' => $disk,
                'path' => $path,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size' => $size,
                'width' => $width,
                'height' => $height,
                'alt_text' => $altText,
                'metadata' => $options['metadata'] ?? [],
            ]);
        });
    }

    /**
     * Upload from base64 string (transactional)
     */
    public function uploadFromBase64(string $base64Data, array $options = []): Media
    {
        // Parse the base64 string
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $type = $matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $base64Data = base64_decode($base64Data);
        } else {
            throw new Exception('Invalid base64 image data');
        }

        // Create a temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'media_');
        file_put_contents($tempPath, $base64Data);

        try {
            // Create UploadedFile instance
            $file = new UploadedFile(
                $tempPath,
                'image.' . $type,
                'image/' . $type,
                null,
                true
            );

            // Upload using the regular upload method
            $media = $this->upload($file, $options);

            return $media;
        } finally {
            // Clean up temp file even if upload fails
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Delete a media file and record (reference-safe)
     */
    public function delete(Media $media): bool
    {
        return DB::transaction(function () use ($media) {
            // Check if media is still referenced by other entities
            if ($this->isReferenced($media)) {
                // Only delete the record, not the file
                return $media->delete();
            }

            // Delete the file from storage
            Storage::disk($media->disk)->delete($media->path);

            // Delete the media record
            return $media->delete();
        });
    }

    /**
     * Check if media is still referenced by other entities
     */
    protected function isReferenced(Media $media): bool
    {
        // Check if referenced by services
        $serviceCount = \App\Models\Service::where('image_media_id', $media->id)->count();
        if ($serviceCount > 0) return true;

        // Check if referenced by staff (photo field)
        $staffCount = \App\Models\Staff::where('photo', $media->id)->count();
        if ($staffCount > 0) return true;

        // Check if referenced by provider (logo or cover_image)
        $providerLogoCount = \App\Models\Provider::where('logo', $media->id)->count();
        if ($providerLogoCount > 0) return true;

        $providerCoverCount = \App\Models\Provider::where('cover_image', $media->id)->count();
        if ($providerCoverCount > 0) return true;

        // Check if referenced by BrandExperience (logo or background_image)
        $brandLogoCount = \App\Models\BrandExperience::where('logo', $media->id)->count();
        if ($brandLogoCount > 0) return true;

        $brandBgCount = \App\Models\BrandExperience::where('background_image', $media->id)->count();
        if ($brandBgCount > 0) return true;

        // Check if referenced by CustomerTimeline (before_photo or after_photo)
        $timelineBeforeCount = \App\Models\CustomerTimeline::where('before_photo', $media->id)->count();
        if ($timelineBeforeCount > 0) return true;

        $timelineAfterCount = \App\Models\CustomerTimeline::where('after_photo', $media->id)->count();
        if ($timelineAfterCount > 0) return true;

        return false;
    }

    /**
     * Generate a unique filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = uniqid('', true);
        
        return $basename . '.' . $extension;
    }

    /**
     * Get image dimensions
     */
    protected function getImageDimensions(string $path): array
    {
        try {
            $image = $this->imageManager->read($path);
            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (Exception $e) {
            return ['width' => null, 'height' => null];
        }
    }
}
