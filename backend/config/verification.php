<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paid Channel Policy (Zero-Cost Operating Mode)
    |--------------------------------------------------------------------------
    |
    | By default, TYBUEL prohibits any verification channel that incurs
    | monetary charges or third-party usage costs.
    |
    */
    'allow_paid_channels' => (bool) env('ALLOW_PAID_VERIFICATION_CHANNELS', false),

    /*
    |--------------------------------------------------------------------------
    | Onboarding Verification Policy
    |--------------------------------------------------------------------------
    |
    | Onboarding must never be blocked by messaging credits or verification
    | barriers. Users can complete onboarding and verify identity later.
    |
    */
    'enforce_mandatory_otp_onboarding' => false,

    /*
    |--------------------------------------------------------------------------
    | Verification Channels
    |--------------------------------------------------------------------------
    |
    | Each channel defines its activation status, cost structure, and supported
    | purposes. Paused channels will never send messages.
    |
    */
    'channels' => [
        'whatsapp' => [
            'name'                  => 'WhatsApp',
            'enabled'               => (bool) env('VERIFICATION_WHATSAPP_ENABLED', false),
            'is_paid'               => true, // Meta charges for outbound authentication templates
            'has_zero_cost_gateway' => (bool) env('VERIFICATION_WHATSAPP_ZERO_COST', false),
            'provider'              => env('VERIFICATION_WHATSAPP_PROVIDER', 'meta_cloud_api'),
            'status'                => 'PAUSED', // Paused until a verified zero-cost gateway is confirmed
            'supported_purposes'    => [
                'phone_verification',
                'registration',
                'account_recovery',
                'phone_change',
                'sensitive_action',
            ],
            'credentials' => [
                'token'          => env('WHATSAPP_API_TOKEN'),
                'phone_number_id'=> env('WHATSAPP_PHONE_NUMBER_ID'),
                'template_name'  => env('WHATSAPP_OTP_TEMPLATE', 'tybuel_verification_otp'),
            ],
        ],

        'email' => [
            'name'                  => 'Email',
            'enabled'               => (bool) env('VERIFICATION_EMAIL_ENABLED', true),
            'is_paid'               => false, // Operates over existing SMTP, system mailer, or log
            'has_zero_cost_gateway' => true,
            'provider'              => env('MAIL_MAILER', 'log'),
            'status'                => 'ACTIVE',
            'supported_purposes'    => [
                'email_verification',
                'registration',
                'account_recovery',
                'sensitive_action',
            ],
        ],

        'sms_africas_talking' => [
            'name'                  => "Africa's Talking SMS",
            'enabled'               => false,
            'is_paid'               => true,
            'has_zero_cost_gateway' => false,
            'provider'              => 'africas_talking',
            'status'                => 'PAUSED',
            'supported_purposes'    => [
                'phone_verification',
                'account_recovery',
                'sensitive_action',
            ],
        ],

        'sms_twilio' => [
            'name'                  => 'Twilio SMS',
            'enabled'               => false,
            'is_paid'               => true,
            'has_zero_cost_gateway' => false,
            'provider'              => 'twilio',
            'status'                => 'PAUSED',
            'supported_purposes'    => [
                'phone_verification',
                'account_recovery',
                'sensitive_action',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP Lifecycle Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'otp_length'              => 6,
        'ttl_minutes'             => 15,
        'resend_cooldown_seconds' => 60,
        'max_attempts'            => 5,
    ],
];
