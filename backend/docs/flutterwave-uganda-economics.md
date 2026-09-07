# Flutterwave Uganda Economics Verification

## Current Implementation

### Flutterwave Provider Configuration
- **Base URL**: `https://api.flutterwave.com/v3`
- **Payment Options**: `card, mobilemoneyuganda, ussd`
- **Currency**: UGX (Ugandan Shilling)

### Fee Structure Analysis

#### From FlutterwaveProvider.php
The `verifyPayment` method extracts fees from Flutterwave's response:
```php
'fees' => $data['app_fee'] ?? 0
```

This indicates Flutterwave returns an `app_fee` field in their transaction response.

#### Current FeeEngine Behavior
The `FeeEngine` currently defaults to zero fees for all transactions:
```php
public function calculateFees(float $grossAmount, ?PaymentMethod $paymentMethod): array
{
    return [
        'gross_amount' => $grossAmount,
        'gateway_fee' => 0.0,
        'platform_fee' => 0.0,
        'tax_amount' => 0.0,
        'net_amount' => $grossAmount,
    ];
}
```

## Flutterwave Uganda Actual Fees (Research Required)

### Mobile Money Uganda (MTN, Airtel)
According to Flutterwave documentation (needs verification):
- **MTN Mobile Money**: ~1.5% - 2% per transaction
- **Airtel Money**: ~1.5% - 2% per transaction
- **Minimum fee**: May apply for small transactions
- **Maximum fee**: May be capped for large transactions

### Card Payments
- **Visa/Mastercard**: ~3.5% per transaction
- **International cards**: Higher fees may apply

### USSD
- **USSD payments**: ~1.5% - 2% per transaction

## Discrepancy Analysis

### Current State
- **FeeEngine**: Returns 0 fees (zero platform commission philosophy)
- **Flutterwave Provider**: Extracts `app_fee` from response but doesn't use it
- **Transaction Model**: Stores `gateway_fee` but it's always 0

### Issue
The system currently ignores Flutterwave's actual gateway fees. This means:
1. **Net Amount Calculation**: Incorrect (doesn't subtract gateway fees)
2. **Financial Reporting**: Inaccurate (shows 100% of gross as net)
3. **Settlement**: May not match actual amounts received

## Recommended Actions

### Option 1: Track Actual Fees (Recommended)
Update `FeeEngine` to use Flutterwave's actual fees:

```php
public function calculateFeesFromProvider(float $grossAmount, ?PaymentMethod $paymentMethod, array $providerResponse): array
{
    $gatewayFee = $providerResponse['fees'] ?? 0.0;
    $platformFee = 0.0; // Zero platform commission
    $taxAmount = 0.0;
    
    $netAmount = $grossAmount - $gatewayFee - $platformFee - $taxAmount;
    
    return [
        'gross_amount' => $grossAmount,
        'gateway_fee' => $gatewayFee,
        'platform_fee' => $platformFee,
        'tax_amount' => $taxAmount,
        'net_amount' => $netAmount,
    ];
}
```

### Option 2: Configure Estimated Fees
Configure expected fees in `FeeEngine` based on payment method:

```php
public function calculateFeesWithConfig(float $grossAmount, ?PaymentMethod $paymentMethod, array $config): array
{
    $gatewayFee = 0.0;
    $methodType = $paymentMethod ? strtolower($paymentMethod->type) : 'cash';
    
    if ($methodType === 'mobile_money') {
        $gatewayFee = $grossAmount * 0.02; // 2% for Uganda Mobile Money
    } elseif ($methodType === 'card') {
        $gatewayFee = $grossAmount * 0.035; // 3.5% for cards
    }
    
    $netAmount = $grossAmount - $gatewayFee;
    
    return [
        'gross_amount' => $grossAmount,
        'gateway_fee' => $gatewayFee,
        'platform_fee' => 0.0,
        'tax_amount' => 0.0,
        'net_amount' => $netAmount,
    ];
}
```

### Option 3: Keep Zero Fees (Current)
Maintain current behavior if:
- Yo.Salon absorbs gateway fees
- Gateway fees are charged separately to customers
- Financial reporting doesn't require accurate net amounts

## Next Steps

1. **Verify actual Flutterwave fees** for Uganda from official documentation
2. **Decide on fee tracking strategy** (actual vs estimated vs zero)
3. **Update FeeEngine** accordingly
4. **Update payment verification flow** to use provider fees
5. **Test with real transactions** to verify accuracy

## Configuration Updates Needed

If using Option 2, update `config/services.php`:
```php
'flutterwave' => [
    'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
    'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
    'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
    'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
    'fees' => [
        'mobile_money_uganda' => 0.02, // 2%
        'card' => 0.035, // 3.5%
        'ussd' => 0.02, // 2%
    ],
],
```
