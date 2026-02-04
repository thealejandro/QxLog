<?php

return [
    'version' => '1.0.0',

    'voucher_legend' => 'Por servicios de instrumentación prestados, se realiza el pago correspondiente.',
    'org_name' => 'Hospital Nuestra Señora del Carmen',

    'default_rate' => 200.00,

    //Condiciones de pago "Schema"
    'conditions_payment' => [
        'cash' => 'Efectivo',
        'check' => 'Cheque',
        'transfer' => 'Transferencia',
        'other' => 'Otro',
    ],

    //Roles
    'roles' => [
        'instrumentist' => 'Instrumentista',
        'doctor' => 'Doctor',
        'circulating' => 'Circulante',
    ],

    'special' => [
        'enabled' => true,

        //Horario especial
        'business_hours' => [
            'start' => '05:00',
            'end' => '21:00',
        ],

        //Place Holders
        'rates' => [
            'business_hours' => 200.00,
            'night' => 250.00,
            'over_2hours' => 300.00,
            'video' => 300.00,
        ],
    ],

];