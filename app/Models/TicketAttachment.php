<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id','path','nama_asli'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
