<x-filament-panels::page>
    <x-filament::section heading="Uji Coba">
        <p class="text-sm text-gray-500 mb-4">
            Ketik path endpoint VClaim yang mau dites (mis. <code>Rujukan/RS/{noRujukan}</code> atau
            <code>Referensi/dokter/pelayanan/{kodeDokter}/tglpelayanan/{tanggal}</code>), lalu klik Tes Sekarang.
        </p>

        <div class="flex flex-col gap-1 mb-4 max-w-xl">
            <label for="endpoint" class="text-sm font-medium">Endpoint</label>
            <input type="text" id="endpoint" wire:model="endpoint"
                class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm font-mono" />
        </div>

        <x-filament::button wire:click="tesSekarang" wire:loading.attr="disabled" icon="heroicon-o-bolt">
            <span wire:loading.remove wire:target="tesSekarang">Tes Sekarang</span>
            <span wire:loading wire:target="tesSekarang">Menguji...</span>
        </x-filament::button>
    </x-filament::section>

    @if ($hasil)
        <x-filament::section heading="Hasil">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Terhubung</dt>
                    <dd class="text-sm">{{ $hasil['berhasil_terhubung'] ? 'Ya' : 'Tidak' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Waktu Respon</dt>
                    <dd class="text-sm">{{ number_format($hasil['durasi_ms'], 1, ',', '.') }} ms</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Status HTTP</dt>
                    <dd class="text-sm">{{ $hasil['status_http'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Kode BPJS</dt>
                    <dd class="text-sm">{{ $hasil['metadata_code'] ?? '-' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">Pesan BPJS</dt>
                    <dd class="text-sm">{{ $hasil['metadata_message'] ?? '-' }}</dd>
                </div>
                @if ($hasil['error'])
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-danger-600">Error</dt>
                        <dd class="text-sm text-danger-600">{{ $hasil['error'] }}</dd>
                    </div>
                @endif
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
