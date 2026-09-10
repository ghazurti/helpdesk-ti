<?php
// tests/Feature/DashboardTest.php
use App\Models\{User, Ticket};
use Filament\Facades\Filament;
use function Pest\Livewire\livewire;

it('renders the stat overview widget', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Ticket::factory()->count(2)->create(['status' => 'selesai']);

    livewire(\App\Filament\Widgets\StatOverview::class)
        ->assertOk()
        ->assertSee('Tiket Selesai')
        ->assertSee('Rata-rata Penyelesaian');
});
