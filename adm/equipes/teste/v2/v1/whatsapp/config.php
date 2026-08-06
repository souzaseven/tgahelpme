<?php
// =====================================================
// CONFIGURAÇÃO WHATSAPP — APIBRASIL (API WhatsApp Wpp)
// =====================================================

return [
    'provider' => 'APIBRASIL',

    'apibrasil' => [

        // Apenas informativo (não usado pela API)
        'instance_id' => 'IphoneAnderson',

        // 🔐 BEARER TOKEN (SEM "Bearer ")
        'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2dhdGV3YXkuYXBpYnJhc2lsLmlvL2FwaS92Mi9hdXRoL3JlZ2lzdGVyIiwiaWF0IjoxNzUwMzQ5MjA2LCJleHAiOjE3ODE4ODUyMDYsIm5iZiI6MTc1MDM0OTIwNiwianRpIjoiRzdMTG01QXNSalhod1Z3ViIsInN1YiI6IjE1NzUzIiwicHJ2IjoiMjNiZDVjODk0OWY2MDBhZGIzOWU3MDFjNDAwODcyZGI3YTU5NzZmNyJ9.h9EA9ZTh_FrnAF7NOyS5UmSxoKaohYKLWu6g_LQ0KBY',

        // 🔥 DEVICE TOKEN REAL (COPIADO DO PAINEL)
        'device_token' => 'e26e6a29-1334-44d0-bf14-f4fd16422d73',

        // 🌐 BASE URL CORRETA
        'base_url' => 'https://gateway.apibrasil.io'
    ]
];
