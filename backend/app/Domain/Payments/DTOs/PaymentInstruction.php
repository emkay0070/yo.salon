<?php

namespace App\Domain\Payments\DTOs;

class PaymentInstruction
{
    public int $version = 1;
    public string $strategy; // deposit, full_payment, pay_at_salon, no_payment
    public string $customerAction; // pay_now, pay_at_salon, none
    public bool $paymentRequiredAtBooking;
    public int $amountDueNow;
    public int $amountRemaining;
    public int $totalAmount;
    public string $currency;
    public array $eligibleMethods; // populated by Resolver
    public string $message;
    public ?int $expiresInMinutes;
    public array $cancellationPolicy;
}
