<?php

namespace App\Services;

use App\Models\BrandExperience;
use App\Models\Salon;

class BrandEmailService
{
    /**
     * Generate inline CSS styles for email templates based on brand settings
     */
    public function generateEmailStyles(Salon $salon): array
    {
        $brand = $salon->brandExperience ?? $this->createDefaultBrand($salon);
        
        $experienceFamily = config('experience_families.' . $brand->experience_family, config('experience_families.luxury_noir'));
        
        return [
            'primary_color' => $brand->primary_color,
            'secondary_color' => $brand->secondary_color,
            'accent_color' => $brand->accent_color,
            'logo' => $brand->logo ?? $salon->logo,
            'font_heading' => $brand->font_heading,
            'font_body' => $brand->font_body,
            'button_background' => $brand->primary_color,
            'button_text' => '#000000',
            'card_background' => '#ffffff',
            'text_primary' => '#333333',
            'text_secondary' => '#666666',
            'border_color' => '#e5e5e5',
            'background_color' => '#f5f5f5',
        ];
    }

    /**
     * Get email header HTML with salon logo
     */
    public function getEmailHeader(Salon $salon): string
    {
        $styles = $this->generateEmailStyles($salon);
        $logoUrl = $styles['logo'];
        
        if ($logoUrl) {
            return sprintf(
                '<div style="text-align: center; padding: 20px; background-color: %s;">' .
                '<img src="%s" alt="%s" style="max-width: 200px; max-height: 60px;" />' .
                '</div>',
                $styles['primary_color'],
                $logoUrl,
                $salon->name
            );
        }
        
        return sprintf(
            '<div style="text-align: center; padding: 20px; background-color: %s;">' .
            '<h1 style="color: #000000; font-size: 24px; font-weight: bold; margin: 0;">%s</h1>' .
            '</div>',
            $styles['primary_color'],
            $salon->name
        );
    }

    /**
     * Get email footer HTML
     */
    public function getEmailFooter(Salon $salon): string
    {
        $styles = $this->generateEmailStyles($salon);
        
        return sprintf(
            '<div style="text-align: center; padding: 20px; background-color: %s; color: #000000; font-size: 12px;">' .
            '<p style="margin: 0 0 10px 0;">&copy; %d %s. All rights reserved.</p>' .
            '<p style="margin: 0;">Powered by Yo Salon Platform</p>' .
            '</div>',
            $styles['secondary_color'],
            date('Y'),
            $salon->name
        );
    }

    /**
     * Get button HTML with brand styling
     */
    public function getButton(string $text, string $url, Salon $salon): string
    {
        $styles = $this->generateEmailStyles($salon);
        
        return sprintf(
            '<a href="%s" style="display: inline-block; background-color: %s; color: %s; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">%s</a>',
            $url,
            $styles['button_background'],
            $styles['button_text'],
            $text
        );
    }

    /**
     * Get card container HTML with brand styling
     */
    public function getCard(string $content, Salon $salon): string
    {
        $styles = $this->generateEmailStyles($salon);
        
        return sprintf(
            '<div style="background-color: %s; border: 1px solid %s; border-radius: 8px; padding: 20px; margin: 20px 0;">%s</div>',
            $styles['card_background'],
            $styles['border_color'],
            $content
        );
    }

    /**
     * Create default brand settings for salon
     */
    private function createDefaultBrand(Salon $salon): BrandExperience
    {
        return BrandExperience::create([
            'salon_id' => $salon->id,
            'experience_family' => 'luxury_noir',
            'logo' => $salon->logo,
            'primary_color' => '#FF622B',
            'secondary_color' => '#FF8C5A',
            'accent_color' => '#FFD700',
            'font_heading' => 'sora',
            'font_body' => 'inter',
            'white_label_enabled' => false,
        ]);
    }

    /**
     * Get complete email template with brand styling
     */
    public function getEmailTemplate(string $content, Salon $salon): string
    {
        $styles = $this->generateEmailStyles($salon);
        
        return sprintf(
            '<!DOCTYPE html>' .
            '<html>' .
            '<head>' .
            '<meta charset="UTF-8">' .
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">' .
            '<title>%s</title>' .
            '</head>' .
            '<body style="margin: 0; padding: 0; background-color: %s; font-family: Arial, sans-serif;">' .
            '<table role="presentation" style="width: 100%%; max-width: 600px; margin: 0 auto; background-color: %s;">' .
            '%s' . // Header
            '<tr><td style="padding: 20px;">%s</td></tr>' . // Content
            '%s' . // Footer
            '</table>' .
            '</body>' .
            '</html>',
            $salon->name,
            $styles['background_color'],
            $styles['card_background'],
            $this->getEmailHeader($salon),
            $content,
            $this->getEmailFooter($salon)
        );
    }
}
