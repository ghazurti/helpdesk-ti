<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode','nama','asset_category_id','merk','model','serial_number',
        'tanggal_beli','harga','garansi_sampai','kondisi','unit_id',
        'masa_manfaat_bulan','catatan',
    ];

    protected $casts = [
        'tanggal_beli' => 'date',
        'garansi_sampai' => 'date',
        'harga' => 'decimal:2',
    ];

    public function kategori()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function nilaiBuku(?Carbon $per = null): float
    {
        if (! $this->harga || ! $this->tanggal_beli || ! $this->masa_manfaat_bulan) {
            return (float) ($this->harga ?? 0);
        }
        $per ??= Carbon::now();
        $bulanTerpakai = max(0, $this->tanggal_beli->diffInMonths($per));
        $perBulan = $this->harga / $this->masa_manfaat_bulan;
        $akumulasi = min((float) $this->harga, $perBulan * $bulanTerpakai);
        return round((float) $this->harga - $akumulasi, 2);
    }
}
