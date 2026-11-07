<?php

/**
 * Only what the sign-up, login, and password forms use; every other form
 * writes its own messages in its FormRequest. The rest falls back to English.
 */
return [
    'confirmed' => 'Isian :attribute tidak sama.',
    'current_password' => 'Password salah.',
    'email' => 'Tulis :attribute yang benar, misalnya nama@contoh.com.',
    'lowercase' => ':Attribute harus huruf kecil.',
    'max' => [
        'string' => ':Attribute paling banyak :max huruf.',
    ],
    'min' => [
        'string' => ':Attribute paling sedikit :min huruf.',
    ],
    'password' => [
        'letters' => ':Attribute harus memuat huruf.',
        'mixed' => ':Attribute harus memuat huruf besar dan huruf kecil.',
        'numbers' => ':Attribute harus memuat angka.',
        'symbols' => ':Attribute harus memuat simbol.',
        'uncompromised' => ':Attribute ini pernah bocor di internet. Pakai yang lain.',
    ],
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute ini sudah dipakai.',

    'attributes' => [
        'name' => 'nama',
        'email' => 'email',
        'password' => 'password',
    ],
];
