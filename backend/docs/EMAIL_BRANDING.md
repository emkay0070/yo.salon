# Email Branding Documentation

## Overview
The Brand Experience System includes email branding capabilities that allow each salon to send branded emails with their custom colors, logos, and styling.

## BrandEmailService

The `BrandEmailService` provides methods to generate branded email templates:

### Usage

```php
use App\Services\BrandEmailService;
use App\Models\Salon;

$brandEmailService = new BrandEmailService();
$salon = Salon::find($salonId);

// Get email styles array
$styles = $brandEmailService->generateEmailStyles($salon);
// Returns: primary_color, secondary_color, accent_color, logo, etc.

// Get branded header
$header = $brandEmailService->getEmailHeader($salon);
// Returns HTML with logo or salon name

// Get branded footer
$footer = $brandEmailService->getEmailFooter($salon);
// Returns HTML with salon info

// Get branded button
$button = $brandEmailService->getButton('Click Here', $url, $salon);
// Returns HTML button with brand colors

// Get branded card container
$card = $brandEmailService->getCard($content, $salon);
// Returns HTML card with brand styling

// Get complete email template
$html = $brandEmailService->getEmailTemplate($content, $salon);
// Returns complete HTML email with header, content, footer
```

## Creating Branded Email Notifications

### Example: Booking Confirmation

```php
namespace App\Notifications;

use App\Models\Booking;
use App\Models\Salon;
use App\Services\BrandEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    protected $booking;
    protected $salon;
    protected $brandEmailService;

    public function __construct(Booking $booking, Salon $salon)
    {
        $this->booking = $booking;
        $this->salon = $salon;
        $this->brandEmailService = new BrandEmailService();
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $styles = $this->brandEmailService->generateEmailStyles($this->salon);
        
        $content = $this->brandEmailService->getCard(
            sprintf(
                '<h2 style="color: %s;">Booking Confirmed</h2>',
                $styles['primary_color']
            ),
            $this->salon
        );

        $html = $this->brandEmailService->getEmailTemplate($content, $this->salon);

        return (new MailMessage)
            ->subject("Booking Confirmed - {$this->salon->name}")
            ->html($html);
    }
}
```

## Available Email Templates

### 1. BookingConfirmed
- Triggered when a booking is confirmed
- Includes booking details (service, date, time, stylist)
- Uses brand colors for styling

### 2. BookingReminder
- Triggered before an appointment
- Includes booking details and reschedule link
- Uses brand colors for styling

### 3. WelcomeEmail
- Triggered when a new customer registers
- Includes salon welcome message
- Uses brand colors for styling

## Email Styles

The `generateEmailStyles()` method returns an array with the following keys:

- `primary_color` - Salon's primary brand color
- `secondary_color` - Salon's secondary brand color
- `accent_color` - Salon's accent color
- `logo` - Salon logo URL (or null)
- `font_heading` - Heading font family
- `font_body` - Body font family
- `button_background` - Button background color
- `button_text` - Button text color
- `card_background` - Card background color
- `text_primary` - Primary text color
- `text_secondary` - Secondary text color
- `border_color` - Border color
- `background_color` - Email background color

## Best Practices

1. **Always use inline styles** - Email clients don't support external CSS
2. **Use the BrandEmailService methods** - They handle brand consistency
3. **Include salon context** - Pass the salon model to all service methods
4. **Test across email clients** - Different clients render HTML differently
5. **Keep it simple** - Complex layouts may break in some email clients
6. **Use tables for layout** - More reliable than divs in email clients

## Adding New Email Templates

When creating a new email notification:

1. Create a new notification class in `app/Notifications/`
2. Inject `BrandEmailService` in the constructor
3. Use `generateEmailStyles()` to get brand colors
4. Use service methods for consistent styling
5. Build content with inline styles
6. Wrap in `getEmailTemplate()` for complete email

## Testing

To test email branding:

```php
$salon = Salon::find($salonId);
$brandEmailService = new BrandEmailService();

// Test header
echo $brandEmailService->getEmailHeader($salon);

// Test button
echo $brandEmailService->getButton('Test Button', 'https://example.com', $salon);

// Test full template
echo $brandEmailService->getEmailTemplate('<p>Test content</p>', $salon);
```
