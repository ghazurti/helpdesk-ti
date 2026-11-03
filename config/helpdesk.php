<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Target Waktu Layanan per Prioritas
    |--------------------------------------------------------------------------
    |
    | Batas waktu respons (menit) dan penyelesaian (jam) gangguan, dihitung
    | sejak tiket dibuat. Dipakai untuk menghitung KPI di halaman Laporan.
    | Tiket dianggap direspons saat statusnya pertama kali keluar dari "baru".
    |
    */

    'sla' => [
        'tinggi' => ['respons_menit' => 15, 'selesai_jam' => 4],
        'sedang' => ['respons_menit' => 30, 'selesai_jam' => 24],
        'rendah' => ['respons_menit' => 60, 'selesai_jam' => 72],
    ],

    /*
    |--------------------------------------------------------------------------
    | Target Capaian KPI (%)
    |--------------------------------------------------------------------------
    */

    'target_kpi' => [
        'respons' => 90,
        'penyelesaian' => 85,
    ],

];
