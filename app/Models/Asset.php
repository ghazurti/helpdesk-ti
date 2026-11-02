<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode','nama','asset_category_id','merk','model','serial_number',
        'tanggal_beli','harga','garansi_sampai','kondisi','unit_id',
        'masa_manfaat_bulan','catatan','foto',
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

    public function movements()
    {
        return $this->hasMany(AssetMovement::class)->latest('tanggal');
    }

    public function pindahKe(Unit $tujuan, ?string $keterangan, User $oleh): AssetMovement
    {
        return DB::transaction(function () use ($tujuan, $keterangan, $oleh) {
            $move = $this->movements()->create([
                'from_unit_id' => $this->unit_id,
                'to_unit_id'   => $tujuan->id,
                'tanggal'      => now()->toDateString(),
                'keterangan'   => $keterangan,
                'user_id'      => $oleh->id,
            ]);
            $this->update(['unit_id' => $tujuan->id]);
            return $move;
        });
    }

    public function repairs()
    {
        return $this->hasMany(Ticket::class)->latest('tanggal');
    }
}
