<?php

namespace App\Domain\Payments\Resolvers;

use App\Domain\Payments\DTOs\PaymentInstruction;
use App\Models\PaymentMethod;
use App\Models\Salon;
use Exception;

class EligiblePaymentMethodResolver
{
    /**
     * Resolves the eligible payment methods for a given payment instruction and salon context.
     *
     * @param PaymentInstruction $instruction
     * @param string $salonId
     * @return array
     * @throws Exception if configuration is invalid
     */
    public function resolve(PaymentInstruction $instruction, string $salonId): array
    {
        $query = PaymentMethod::where('salon_id', $salonId)->where('is_active', true);

        if ($instruction->customerAction === 'pay_now') {
            $query->where('supports_online_payment', true);
            $methods = $query->get(['id', 'provider', 'type', 'display_name']);

            if ($methods->isEmpty()) {
                throw new Exception('Configuration Invalid: Online payment is required but no online payment methods are enabled.');
            }

            return $methods->toArray();
        }

        if ($instruction->customerAction === 'pay_at_salon') {
            $query->where('supports_in_person_payment', true);
            return $query->get(['id', 'provider', 'type', 'display_name'])->toArray();
        }

        return [];
    }
}
