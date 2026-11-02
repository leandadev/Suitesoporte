<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clientes Stellar ERP Registrados
    |--------------------------------------------------------------------------
    | Cada cliente define sus instancias SQL Server VAD10 y VAD20.
    */
    "clients" => [
        "COISA_HOGAR" => [
            "name" => "COISA HOGAR",
            "branches" => [
                "01" => [
                    "name" => "COISA Hogar - Principal",
                    "server" => env("COISA_BRANCH_1_SERVER", "26.0.0.1"),
                    "vad10" => "VAD10",
                    "vad20" => "VAD20",
                    "uid" => env("COISA_BRANCH_1_UID", "sa"),
                    "pwd" => env("COISA_BRANCH_1_PWD", ""),
                ],
                "02" => [
                    "name" => "COISA Hogar - Sucursal 2",
                    "server" => env("COISA_BRANCH_2_SERVER", "26.0.0.2"),
                    "vad10" => "VAD10",
                    "vad20" => "VAD20",
                    "uid" => env("COISA_BRANCH_2_UID", "sa"),
                    "pwd" => env("COISA_BRANCH_2_PWD", ""),
                ],
                "03" => [
                    "name" => "COISA Hogar - Sucursal Maracay",
                    "server" => env("COISA_BRANCH_3_SERVER", "26.0.0.3"),
                    "vad10" => "VAD10",
                    "vad20" => "VAD20",
                    "uid" => "sa",
                    "pwd" => "",
                ],
                "04" => [
                    "name" => "COISA Hogar - Caracas",
                    "server" => env("COISA_BRANCH_4_SERVER", "26.0.0.4"),
                    "vad10" => "VAD10",
                    "vad20" => "VAD20",
                    "uid" => "sa",
                    "pwd" => "",
                ],
            ]
        ],
        "LOCAL_DEV" => [
            "name" => "Servidor Local / Laboratorio",
            "branches" => [
                "01" => [
                    "name" => "Instancia Principal (SOPORTE)",
                    "server" => "DESKTOP-6B0Q8VN\\SOPORTE",
                    "vad10" => "VAD10",
                    "vad20" => "VAD20",
                    "trusted" => true,
                    "uid" => "sa",
                    "pwd" => "",
                ]
            ]
        ]
    ]
];
