<x-filament-panels::page>
    <form wire:submit.prevent class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6 items-end">
        <div class="flex flex-col gap-1">
            <label for="dari" class="text-sm font-medium">Dari Tanggal</label>
            <input type="date" id="dari" wire:model="dari" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
        </div>

        <div class="flex flex-col gap-1">
            <label for="sampai" class="text-sm font-medium">Sampai Tanggal</label>
            <input type="date" id="sampai" wire:model="sampai" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
        </div>

        <div class="flex flex-col gap-1">
            <label for="user_id" class="text-sm font-medium">Petugas</label>
            <select id="user_id" wire:model="user_id" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                <option value="">Semua Petugas</option>
                @foreach ($this->petugasOptions as $petugas)
                    <option value="{{ $petugas->id }}">{{ $petugas->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="unit_id" class="text-sm font-medium">Unit</label>
            <select id="unit_id" wire:model="unit_id" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                <option value="">Semua Unit</option>
                @foreach ($this->unitOptions as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="work_category_id" class="text-sm font-medium">Kategori</label>
            <select id="work_category_id" wire:model="work_category_id" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                <option value="">Semua Kategori</option>
                @foreach ($this->kategoriOptions as $kategori)
                    <option value="{{ $kategori->id }}">{{ $kategori->nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="status" class="text-sm font-medium">Status</label>
            <select id="status" wire:model="status" class="fi-select-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                <option value="">Semua Status</option>
                <option value="baru">Baru</option>
                <option value="proses">Proses</option>
                <option value="pending">Pending</option>
                <option value="selesai">Selesai</option>
            </select>
        </div>
    </form>

    <div class="flex gap-2">
        <x-filament::button wire:click="exportPdf" color="danger" icon="heroicon-o-document-arrow-down">
            Export PDF
        </x-filament::button>
        <x-filament::button wire:click="exportExcel" color="success" icon="heroicon-o-table-cells">
            Export Excel
        </x-filament::button>
    </div>

    @php $rows = $this->hasilQuery()->get(); @endphp

    @php $kpi = $this->kpi(); @endphp

    <x-filament::section heading="Indikator Kinerja (KPI)">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 text-start font-medium">No</th>
                        <th class="px-3 py-2 text-start font-medium">Indikator</th>
                        <th class="px-3 py-2 text-start font-medium">Rumus</th>
                        <th class="px-3 py-2 text-start font-medium">Capaian</th>
                        <th class="px-3 py-2 text-start font-medium">Target</th>
                        <th class="px-3 py-2 text-start font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (\App\Filament\Pages\LaporanPekerjaan::indikatorKpi() as $kunci => $indikator)
                        @php $k = $kpi[$kunci]; @endphp
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-3 py-2">{{ $loop->iteration }}</td>
                            <td class="px-3 py-2">{{ $indikator['nama'] }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $indikator['rumus'] }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($k['persen'] === null)
                                    -
                                @else
                                    <span class="font-semibold">{{ number_format($k['persen'], 1, ',', '.') }}%</span>
                                    <span class="text-gray-500">({{ $k['tepat'] }}/{{ $k['total'] }})</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">≥ {{ $k['target'] }}%</td>
                            <td class="px-3 py-2">
                                @if ($k['tercapai'] === null)
                                    <x-filament::badge color="gray">Belum ada data</x-filament::badge>
                                @elseif ($k['tercapai'])
                                    <x-filament::badge color="success">Tercapai</x-filament::badge>
                                @else
                                    <x-filament::badge color="danger">Belum tercapai</x-filament::badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-500">
            Target waktu dihitung sejak tiket dibuat:
            @foreach (config('helpdesk.sla') as $prioritas => $sla)
                {{ ucfirst($prioritas) }} respons ≤ {{ $sla['respons_menit'] }} menit, selesai ≤ {{ $sla['selesai_jam'] }} jam{{ $loop->last ? '.' : ';' }}
            @endforeach
            Tiket yang masih dalam batas waktu dan belum direspons/selesai belum dihitung.
        </p>
    </x-filament::section>

    <x-filament::section heading="Rata-rata Waktu Penyelesaian">
        <p class="text-sm">
            {{ $this->rataRataPenyelesaian() }}
            <span class="text-gray-500">(dari tiket selesai sesuai filter di atas)</span>
        </p>
    </x-filament::section>

    <x-filament::section heading="Hasil">
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 text-start font-medium">Nomor</th>
                        <th class="px-3 py-2 text-start font-medium">Tanggal</th>
                        <th class="px-3 py-2 text-start font-medium">Petugas</th>
                        <th class="px-3 py-2 text-start font-medium">Unit</th>
                        <th class="px-3 py-2 text-start font-medium">Kategori</th>
                        <th class="px-3 py-2 text-start font-medium">Judul</th>
                        <th class="px-3 py-2 text-start font-medium">Status</th>
                        <th class="px-3 py-2 text-start font-medium">Durasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-3 py-2">{{ $row->nomor }}</td>
                            <td class="px-3 py-2">{{ $row->tanggal?->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">{{ $row->petugas?->name }}</td>
                            <td class="px-3 py-2">{{ $row->unit?->nama }}</td>
                            <td class="px-3 py-2">{{ $row->kategori?->nama }}</td>
                            <td class="px-3 py-2">{{ $row->judul }}</td>
                            <td class="px-3 py-2">{{ $row->status }}</td>
                            <td class="px-3 py-2">{{ \App\Models\Ticket::formatDurasiJam($row->durasiPenyelesaianJam()) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-gray-500">Tidak ada data untuk filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
