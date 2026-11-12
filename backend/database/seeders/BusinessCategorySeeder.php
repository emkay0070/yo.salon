<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'provider_type' => 'salon',
                'slug' => 'hair_salon',
                'name' => 'Hair Salon',
                'short_name' => 'Salon',
                'description' => 'Hair • Styling • Beauty',
                'icon' => 'scissors',
                'is_active' => true,
                'sort_order' => 1,
                'default_specialist_label' => 'Stylist',
                'default_specialist_plural' => 'Stylists',
                'default_appointment_label' => 'Appointment',
                'default_customer_term' => 'Client',
            ],
            [
                'provider_type' => 'salon',
                'slug' => 'barbershop',
                'name' => 'Barbershop',
                'short_name' => 'Barber',
                'description' => 'Cuts • Grooming • Beard',
                'icon' => 'barber',
                'is_active' => true,
                'sort_order' => 2,
                'default_specialist_label' => 'Barber',
                'default_specialist_plural' => 'Barbers',
                'default_appointment_label' => 'Booking',
                'default_customer_term' => 'Customer',
            ],
            [
                'provider_type' => 'salon',
                'slug' => 'nail_studio',
                'name' => 'Nail Studio',
                'short_name' => 'Nails',
                'description' => 'Nails • Art • Care',
                'icon' => 'hand',
                'is_active' => true,
                'sort_order' => 3,
                'default_specialist_label' => 'Nail Technician',
                'default_specialist_plural' => 'Nail Technicians',
                'default_appointment_label' => 'Appointment',
                'default_customer_term' => 'Client',
            ],
            [
                'provider_type' => 'spa',
                'slug' => 'spa',
                'name' => 'Spa',
                'short_name' => 'Spa',
                'description' => 'Treatments • Relaxation • Wellness',
                'icon' => 'flower',
                'is_active' => true,
                'sort_order' => 4,
                'default_specialist_label' => 'Therapist',
                'default_specialist_plural' => 'Therapists',
                'default_appointment_label' => 'Treatment',
                'default_customer_term' => 'Guest',
            ],
            [
                'provider_type' => 'spa',
                'slug' => 'wellness_center',
                'name' => 'Wellness Center',
                'short_name' => 'Wellness',
                'description' => 'Care • Movement • Wellbeing',
                'icon' => 'heart',
                'is_active' => true,
                'sort_order' => 5,
                'default_specialist_label' => 'Practitioner',
                'default_specialist_plural' => 'Practitioners',
                'default_appointment_label' => 'Session',
                'default_customer_term' => 'Member',
            ],
            [
                'provider_type' => 'mobile_team',
                'slug' => 'mobile_beauty',
                'name' => 'Mobile Beauty',
                'short_name' => 'Mobile',
                'description' => 'On-demand beauty services at your location',
                'icon' => 'car',
                'is_active' => true,
                'sort_order' => 6,
                'default_specialist_label' => 'Mobile Specialist',
                'default_specialist_plural' => 'Mobile Specialists',
                'default_appointment_label' => 'Appointment',
                'default_customer_term' => 'Client',
            ],
        ];

        foreach ($categories as $category) {
            // Get existing category if it exists
            $existing = DB::table('business_categories')->where('slug', $category['slug'])->first();

            if ($existing) {
                // Update existing category without changing ID
                DB::table('business_categories')
                    ->where('slug', $category['slug'])
                    ->update([
                        'provider_type' => $category['provider_type'],
                        'name' => $category['name'],
                        'short_name' => $category['short_name'],
                        'description' => $category['description'],
                        'icon' => $category['icon'],
                        'is_active' => $category['is_active'],
                        'sort_order' => $category['sort_order'],
                        'default_specialist_label' => $category['default_specialist_label'],
                        'default_specialist_plural' => $category['default_specialist_plural'],
                        'default_appointment_label' => $category['default_appointment_label'],
                        'default_customer_term' => $category['default_customer_term'],
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert new category with new ID
                DB::table('business_categories')->insert(array_merge($category, [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $this->command->info('Business categories seeded successfully.');
    }
}
