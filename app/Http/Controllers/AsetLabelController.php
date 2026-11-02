<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman cetak label QR aset. Menerima ?ids=1,2,3 dan menampilkan satu
 * label per aset (QR + kode + nama + unit), siap dicetak lewat browser.
 */
class AsetLabelController
{
    public function __invoke(Request $request)
    {
        Gate::authorize('viewAny', Asset::class);

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $assets = Asset::with('unit')->whereIn('id', $ids)->orderBy('kode')->get();

        abort_if($assets->isEmpty(), 404, 'Aset tidak ditemukan.');

        return view('aset.label', ['assets' => $assets]);
    }
}
