<?php

namespace App\Filament\Pages;

use App\Services\BpjsVclaimClient;
use Filament\Pages\Page;

class TesApiBpjs extends Page
{
    protected static ?string $navigationGroup = 'Pemantauan';
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Tes API BPJS';
    protected static ?string $title = 'Tes API BPJS (VClaim)';
    protected static string $view = 'filament.pages.tes-api-bpjs';

    public ?array $hasil = null;
    public ?string $endpoint = null;

    public function mount(): void
    {
        $this->endpoint = (string) config('bpjs.vclaim.test_endpoint');
    }

    public function tesSekarang(): void
    {
        $this->hasil = (new BpjsVclaimClient)->testResponseTime($this->endpoint);
    }
}
