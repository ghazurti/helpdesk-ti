<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor','user_id','unit_id','work_category_id','asset_id',
        'judul','deskripsi','tindakan','prioritas','status','tanggal','selesai_at',
    ];

    protected $casts = ['tanggal' => 'date', 'selesai_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (blank($ticket->nomor)) {
                $ticket->nomor = static::nomorBerikutnya($ticket->tanggal);
            }

            if ($ticket->status === 'selesai' && blank($ticket->selesai_at)) {
                $ticket->selesai_at = now();
            }
        });

        static::updating(function (Ticket $ticket) {
            if ($ticket->isDirty('status') && $ticket->status === 'selesai' && blank($ticket->selesai_at)) {
                $ticket->selesai_at = now();
            }
        });

        static::updated(function (Ticket $ticket) {
            if ($ticket->wasChanged('status')) {
                $ticket->logs()->create([
                    'user_id'     => auth()->id(),
                    'status_lama' => $ticket->getOriginal('status'),
                    'status_baru' => $ticket->status,
                ]);
            }
        });
    }

    public static function nomorBerikutnya($tanggal): string
    {
        $tahun = Carbon::parse($tanggal ?? now())->format('Y');
        $prefix = "TKT-{$tahun}-";

        return DB::transaction(function () use ($prefix) {
            $last = static::where('nomor', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('nomor')
                ->value('nomor');
            $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
            return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Ensure number generation and the INSERT happen inside the same outer
     * transaction, so the FOR UPDATE row lock taken in nomorBerikutnya()
     * (see the "creating" event above) is held until the insert commits.
     * Without this, the locked SELECT's own transaction commits before the
     * INSERT runs (which happens later, outside any transaction, inside
     * Eloquent's performInsert), so two concurrent creates for the same
     * year can compute the same nomor and one fails with a unique-
     * constraint violation. Existing records (already saved) and any
     * caller that pre-assigns nomor bypass this and behave exactly as
     * before.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists || filled($this->nomor)) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            return parent::save($options);
        });
    }

    /**
     * Durasi penyelesaian tiket ini dalam jam (desimal), dihitung dari
     * created_at (kapan tiket benar-benar dibuat, lengkap dengan jam)
     * sampai selesai_at. Null jika tiket belum selesai.
     */
    public function durasiPenyelesaianJam(): ?float
    {
        if (! $this->selesai_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->selesai_at) / 3600;
    }

    /**
     * Rata-rata durasi penyelesaian (jam) atas tiket berstatus selesai,
     * dihitung di level database. Terima query yang sudah difilter
     * (mis. dari halaman Laporan) supaya hasilnya ikut mengikuti filter
     * yang sedang aktif; default ke semua tiket bila tidak diberikan.
     */
    public static function rataRataPenyelesaianJam(?\Illuminate\Database\Eloquent\Builder $query = null): ?float
    {
        $avgSeconds = ($query ?? static::query())
            ->reorder()
            ->where('status', 'selesai')
            ->whereNotNull('selesai_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, selesai_at)) as rata')
            ->value('rata');

        return $avgSeconds === null ? null : ((float) $avgSeconds) / 3600;
    }

    /**
     * Format durasi jam (desimal) jadi teks Indonesia: "X,X jam" di bawah
     * 24 jam, "X,X hari" dari 24 jam ke atas, atau "-" jika null.
     */
    public static function formatDurasiJam(?float $jam): string
    {
        if ($jam === null) {
            return '-';
        }

        if ($jam < 24) {
            return number_format($jam, 1, ',', '.').' jam';
        }

        return number_format($jam / 24, 1, ',', '.').' hari';
    }

    public function petugas()    { return $this->belongsTo(User::class, 'user_id'); }
    public function unit()       { return $this->belongsTo(Unit::class); }
    public function kategori()   { return $this->belongsTo(WorkCategory::class, 'work_category_id'); }
    public function asset()      { return $this->belongsTo(Asset::class); }
    public function logs()       { return $this->hasMany(TicketLog::class)->latest(); }
    public function attachments(){ return $this->hasMany(TicketAttachment::class); }
}
