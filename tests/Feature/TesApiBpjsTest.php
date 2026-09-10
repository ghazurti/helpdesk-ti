<?php

use App\Filament\Pages\TesApiBpjs;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->stafIt()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config([
        'bpjs.vclaim.cons_id' => 'cons-uji',
        'bpjs.vclaim.secret_key' => 'rahasia-uji',
        'bpjs.vclaim.user_key' => 'userkey-uji',
    ]);
});

it('shows nothing before the test is run', function () {
    livewire(TesApiBpjs::class)
        ->assertOk()
        ->assertDontSee('Waktu Respon');
});

it('runs the test and displays the response time and status on click', function () {
    Http::fake([
        '*' => Http::response(['metadata' => ['message' => 'OK', 'code' => 200]], 200),
    ]);

    livewire(TesApiBpjs::class)
        ->call('tesSekarang')
        ->assertOk()
        ->assertSee('Waktu Respon')
        ->assertSee('200')
        ->assertSee('OK');
});

it('pre-fills the endpoint field with the configured default', function () {
    config(['bpjs.vclaim.test_endpoint' => 'Referensi/diagnosa/A00']);

    livewire(TesApiBpjs::class)
        ->assertSet('endpoint', 'Referensi/diagnosa/A00');
});

it('tests whatever endpoint the user typed in, not just the default', function () {
    Http::fake(['*' => Http::response(['metadata' => ['message' => 'OK', 'code' => 200]], 200)]);

    livewire(TesApiBpjs::class)
        ->set('endpoint', 'Rujukan/RS/1234567890')
        ->call('tesSekarang');

    Http::assertSent(fn ($request) => $request->url()
        === 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/Rujukan/RS/1234567890');
});

it('displays a clear error when BPJS cannot be reached', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host');
    });

    livewire(TesApiBpjs::class)
        ->call('tesSekarang')
        ->assertOk()
        ->assertSee('Could not resolve host');
});
