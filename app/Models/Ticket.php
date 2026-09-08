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

    public function petugas()    { return $this->belongsTo(User::class, 'user_id'); }
    public function unit()       { return $this->belongsTo(Unit::class); }
    public function kategori()   { return $this->belongsTo(WorkCategory::class, 'work_category_id'); }
    public function asset()      { return $this->belongsTo(Asset::class); }
    public function logs()       { return $this->hasMany(TicketLog::class)->latest(); }
    public function attachments(){ return $this->hasMany(TicketAttachment::class); }
}
