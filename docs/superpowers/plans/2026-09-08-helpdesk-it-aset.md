# Helpdesk IT & Inventaris Aset — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a standalone Laravel + Filament web app for a hospital IT team to log completed work (as auto-numbered tickets) and manage a full asset inventory, with filtered, exportable reports and a dashboard.

**Architecture:** Laravel 11 backend with a Filament v3 admin panel as the whole UI. All data lives in a dedicated MySQL/MariaDB database (`helpdesk_it`); no connection to SIMRS. Two roles (Admin, Staf IT) via spatie/laravel-permission gate every resource. Business logic (ticket numbering, status logging, asset movement, depreciation) lives in Eloquent models and is unit-tested with Pest; Filament resources are the CRUD surface over those models.

**Tech Stack:** PHP 8.2, Laravel 11, Filament v3, spatie/laravel-permission, barryvdh/laravel-dompdf, pestphp/pest, MariaDB 10.4 (XAMPP).

**Spec:** `docs/superpowers/specs/2026-09-08-helpdesk-it-aset-design.md`

## Global Constraints

- PHP `^8.2`; Laravel `^11.0`; Filament `^3.2`.
- Database connection `mysql`, database name `helpdesk_it`, host `127.0.0.1`, port `3306`, user `root`, empty password (XAMPP MariaDB 10.4.28). Charset `utf8mb4`.
- All UI labels and enum display text in **Bahasa Indonesia**.
- Roles are exactly two: `admin` and `staf_it` (spatie guard `web`).
- Ticket number format: `TKT-{YYYY}-{NNNN}` where `NNNN` is a 4-digit sequence, reset per calendar year, based on the ticket's `tanggal`.
- Money stored as `decimal(15,2)`; asset `kode` and ticket `nomor` are unique.
- Enums: asset `kondisi` ∈ {`baik`,`rusak`,`perbaikan`,`dihapus`}; ticket `status` ∈ {`baru`,`proses`,`pending`,`selesai`}; ticket `prioritas` ∈ {`rendah`,`sedang`,`tinggi`}.
- Tests use Pest. Run the full suite with `php artisan test`.
- Commit after every task with the message shown in its final step.

---

### Task 1: Scaffold Laravel 11 + MySQL + Pest

**Files:**
- Create: whole Laravel skeleton in project root (`/Users/ghazur_it/Documents/helpdesk-it`)
- Modify: `.env`, `.env.example`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: a booting Laravel app on the `mysql` connection; Pest available via `php artisan test`.

- [ ] **Step 1: Create the Laravel app into the existing folder**

The folder already exists (git initialized, contains `docs/` and `.gitignore`). Create Laravel into a temp dir and move files in so git history is preserved:

```bash
cd /Users/ghazur_it/Documents/helpdesk-it
composer create-project laravel/laravel _laravel_tmp "^11.0"
# move everything including dotfiles, without clobbering docs/ and .git/
shopt -s dotglob
for f in _laravel_tmp/*; do
  base=$(basename "$f")
  [ "$base" = ".git" ] && continue
  cp -R "$f" ./
done
shopt -u dotglob
rm -rf _laravel_tmp
```

- [ ] **Step 2: Install Pest**

```bash
composer remove phpunit/phpunit --dev --no-update 2>/dev/null || true
composer require pestphp/pest pestphp/pest-plugin-laravel --dev -W
php artisan pest:install --no-interaction || ./vendor/bin/pest --init
```

- [ ] **Step 3: Configure `.env` for MySQL**

Edit `.env` (and mirror non-secret keys in `.env.example`) so the DB block reads:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=helpdesk_it
DB_USERNAME=root
DB_PASSWORD=
```

Also set `APP_NAME="Helpdesk IT"` and `APP_TIMEZONE=Asia/Makassar`.

- [ ] **Step 4: Write the smoke test**

```php
<?php
// tests/Feature/SmokeTest.php
it('boots and reaches the database', function () {
    expect(\Illuminate\Support\Facades\DB::connection()->getDatabaseName())
        ->toBe('helpdesk_it');
});

it('serves the welcome page', function () {
    $this->get('/')->assertOk();
});
```

- [ ] **Step 5: Run migrations and the test**

```bash
php artisan migrate --force
php artisan test --filter=SmokeTest
```
Expected: migrations run against `helpdesk_it`; both tests PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 11 with MySQL and Pest"
```

---

### Task 2: Install Filament v3 panel + admin login

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php` (generated), `database/seeders/AdminUserSeeder.php`
- Modify: `app/Models/User.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/PanelAccessTest.php`

**Interfaces:**
- Consumes: booting Laravel app (Task 1).
- Produces: Filament panel at `/admin`; `User` implements `FilamentUser`; seeder creates `admin@helpdesk.test` / `password`.

- [ ] **Step 1: Install Filament panel**

```bash
composer require filament/filament "^3.2" -W
php artisan filament:install --panels --no-interaction
```
This generates `app/Providers/Filament/AdminPanelProvider.php` with panel id `admin` at path `/admin`.

- [ ] **Step 2: Make `User` a Filament user (temporary allow-all, tightened in Task 3)**

In `app/Models/User.php`, implement the contract:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // replaced by role check in Task 3
    }
}
```

- [ ] **Step 3: Write the admin seeder**

```php
<?php
// database/seeders/AdminUserSeeder.php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@helpdesk.test'],
            ['name' => 'Administrator', 'password' => Hash::make('password')],
        );
    }
}
```

Call it from `DatabaseSeeder::run()`: `$this->call(AdminUserSeeder::class);`

- [ ] **Step 4: Write the panel access test**

```php
<?php
// tests/Feature/PanelAccessTest.php
use App\Models\User;

it('redirects guests away from the panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets a seeded user reach the dashboard', function () {
    $this->seed(\Database\Seeders\AdminUserSeeder::class);
    $user = User::where('email', 'admin@helpdesk.test')->first();
    $this->actingAs($user)->get('/admin')->assertOk();
});
```

- [ ] **Step 5: Run the test**

```bash
php artisan test --filter=PanelAccessTest
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add Filament admin panel and seeded admin user"
```

---

### Task 3: Roles & access control (spatie)

**Files:**
- Create: `database/seeders/RoleSeeder.php`
- Modify: `app/Models/User.php`, `config/permission.php` (published), `database/seeders/DatabaseSeeder.php`, `database/seeders/AdminUserSeeder.php`
- Test: `tests/Feature/RoleAccessTest.php`

**Interfaces:**
- Consumes: `User` model + panel (Task 2).
- Produces: roles `admin`, `staf_it`; `User` uses `HasRoles`; `canAccessPanel()` requires either role; helper `User::factory()` states `admin()` and `stafIt()`.

- [ ] **Step 1: Install & migrate spatie/laravel-permission**

```bash
composer require spatie/laravel-permission -W
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate --force
```

- [ ] **Step 2: Add `HasRoles` and tighten panel access**

In `app/Models/User.php`:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'staf_it']);
    }
}
```

- [ ] **Step 3: Write the role seeder and assign admin**

```php
<?php
// database/seeders/RoleSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'staf_it'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
```

In `AdminUserSeeder::run()` after creating the user: `$user->assignRole('admin');` (assign to the `firstOrCreate` result). In `DatabaseSeeder::run()`, call `RoleSeeder` **before** `AdminUserSeeder`.

- [ ] **Step 4: Add factory states**

In `database/factories/UserFactory.php` add:

```php
public function admin(): static
{
    return $this->afterCreating(fn ($u) => $u->assignRole('admin'));
}

public function stafIt(): static
{
    return $this->afterCreating(fn ($u) => $u->assignRole('staf_it'));
}
```

- [ ] **Step 5: Write the role access test**

```php
<?php
// tests/Feature/RoleAccessTest.php
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('grants panel access to both roles', function () {
    $admin = User::factory()->admin()->create();
    $staf  = User::factory()->stafIt()->create();

    expect($admin->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue()
        ->and($staf->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue();
});

it('denies panel access to a roleless user', function () {
    $user = User::factory()->create();
    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeFalse();
});
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter=RoleAccessTest
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add admin and staf_it roles with panel gating"
```

---

### Task 4: Master data (Unit, Kategori Pekerjaan, Kategori Aset)

**Files:**
- Create: migrations `create_units_table`, `create_work_categories_table`, `create_asset_categories_table`; models `app/Models/Unit.php`, `app/Models/WorkCategory.php`, `app/Models/AssetCategory.php`; factories for each; Filament resources `app/Filament/Resources/UnitResource.php`, `WorkCategoryResource.php`, `AssetCategoryResource.php` (+ generated pages).
- Test: `tests/Feature/MasterDataTest.php`

**Interfaces:**
- Consumes: roles (Task 3).
- Produces: models `Unit`, `WorkCategory`, `AssetCategory`, each with `$fillable = ['nama','keterangan']` (categories: `['nama']`); tables `units`, `work_categories`, `asset_categories`; factories `Unit::factory()`, `WorkCategory::factory()`, `AssetCategory::factory()`.

- [ ] **Step 1: Create migrations**

```bash
php artisan make:migration create_units_table
php artisan make:migration create_work_categories_table
php artisan make:migration create_asset_categories_table
```

`units` schema:

```php
Schema::create('units', function (Blueprint $t) {
    $t->id();
    $t->string('nama')->unique();
    $t->string('keterangan')->nullable();
    $t->timestamps();
});
```

`work_categories` and `asset_categories` schema (identical, table name differs):

```php
Schema::create('work_categories', function (Blueprint $t) {
    $t->id();
    $t->string('nama')->unique();
    $t->timestamps();
});
```

- [ ] **Step 2: Create models + factories**

```bash
php artisan make:model Unit -f
php artisan make:model WorkCategory -f
php artisan make:model AssetCategory -f
```

Set `protected $fillable = ['nama', 'keterangan'];` on `Unit`; `['nama']` on the two category models. Factories return `['nama' => fake()->unique()->words(2, true)]` (Unit adds `'keterangan' => fake()->sentence()`).

- [ ] **Step 3: Write the master-data test**

```php
<?php
// tests/Feature/MasterDataTest.php
use App\Models\{Unit, WorkCategory, AssetCategory};

it('creates a unit', function () {
    $u = Unit::create(['nama' => 'IGD', 'keterangan' => 'Instalasi Gawat Darurat']);
    expect($u->fresh()->nama)->toBe('IGD');
});

it('enforces unique unit name', function () {
    Unit::create(['nama' => 'Farmasi']);
    Unit::create(['nama' => 'Farmasi']);
})->throws(\Illuminate\Database\QueryException::class);

it('creates work and asset categories', function () {
    expect(WorkCategory::create(['nama' => 'Jaringan'])->exists)->toBeTrue()
        ->and(AssetCategory::create(['nama' => 'Komputer'])->exists)->toBeTrue();
});
```

- [ ] **Step 4: Run migrate + test**

```bash
php artisan migrate --force
php artisan test --filter=MasterDataTest
```
Expected: PASS.

- [ ] **Step 5: Generate Filament resources**

```bash
php artisan make:filament-resource Unit --generate --no-interaction
php artisan make:filament-resource WorkCategory --generate --no-interaction
php artisan make:filament-resource AssetCategory --generate --no-interaction
```

In each resource set navigation into a group and Indonesian labels, e.g. in `UnitResource`:

```php
protected static ?string $navigationGroup = 'Master Data';
protected static ?string $navigationIcon = 'heroicon-o-building-office';
protected static ?string $modelLabel = 'Unit';
protected static ?string $pluralModelLabel = 'Unit';
```

Do the same for the category resources (`modelLabel` "Kategori Pekerjaan" / "Kategori Aset", icons `heroicon-o-wrench` / `heroicon-o-cube`).

- [ ] **Step 6: Smoke-test the resource pages render**

Add to `tests/Feature/MasterDataTest.php`:

```php
use App\Models\User;
use Filament\Facades\Filament;
use function Pest\Livewire\livewire;

it('renders the unit list for an admin', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    livewire(\App\Filament\Resources\UnitResource\Pages\ListUnits::class)
        ->assertOk();
});
```

Run: `php artisan test --filter=MasterDataTest` → PASS.

- [ ] **Step 7: Add a User management resource (Admin only)**

```bash
php artisan make:filament-resource User --generate --no-interaction
```

In `UserResource`: `navigationGroup = 'Master Data'`, label "Pengguna". Form fields: `name`, `email` (unique ignoring record), `password` (`->password()->dehydrated(fn ($state) => filled($state))->dehydrateStateUsing(fn ($state) => \Illuminate\Support\Facades\Hash::make($state))->required(fn (string $context) => $context === 'create')`), and a role Select:

```php
Forms\Components\Select::make('roles')
    ->label('Role')
    ->relationship('roles', 'name')
    ->multiple()->preload(),
```

Table columns: `name`, `email`, `roles.name` (badge). Access is restricted to admin by `UserPolicy` in Task 9.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add master data (units, work & asset categories, users)"
```

---

### Task 5: Asset inventory model + depreciation (TDD)

**Files:**
- Create: migration `create_assets_table`; `app/Models/Asset.php`; `database/factories/AssetFactory.php`
- Test: `tests/Unit/AssetDepreciationTest.php`

**Interfaces:**
- Consumes: `Unit`, `AssetCategory` (Task 4).
- Produces: `Asset` with relations `kategori()` (belongsTo AssetCategory), `unit()` (belongsTo Unit); casts `tanggal_beli`→date, `garansi_sampai`→date, `harga`→decimal:2; method `nilaiBuku(?Carbon $per = null): float` (straight-line book value); enum-backed `kondisi` values from Global Constraints.

- [ ] **Step 1: Create migration**

```php
Schema::create('assets', function (Blueprint $t) {
    $t->id();
    $t->string('kode')->unique();
    $t->string('nama');
    $t->foreignId('asset_category_id')->constrained()->cascadeOnDelete();
    $t->string('merk')->nullable();
    $t->string('model')->nullable();
    $t->string('serial_number')->nullable();
    $t->date('tanggal_beli')->nullable();
    $t->decimal('harga', 15, 2)->nullable();
    $t->date('garansi_sampai')->nullable();
    $t->enum('kondisi', ['baik','rusak','perbaikan','dihapus'])->default('baik');
    $t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
    $t->unsignedInteger('masa_manfaat_bulan')->nullable();
    $t->text('catatan')->nullable();
    $t->timestamps();
});
```

- [ ] **Step 2: Write the failing depreciation test**

```php
<?php
// tests/Unit/AssetDepreciationTest.php
use App\Models\Asset;
use Carbon\Carbon;

function buatAset(array $override = []): Asset
{
    return (new Asset)->forceFill(array_merge([
        'harga' => 12_000_000,
        'tanggal_beli' => Carbon::parse('2024-01-01'),
        'masa_manfaat_bulan' => 48, // Rp250.000/bulan
    ], $override));
}

it('returns full price before any month passes', function () {
    expect(buatAset()->nilaiBuku(Carbon::parse('2024-01-01')))->toBe(12000000.00);
});

it('depreciates straight-line after 12 months', function () {
    // 12 * 250.000 = 3.000.000 accumulated
    expect(buatAset()->nilaiBuku(Carbon::parse('2025-01-01')))->toBe(9000000.00);
});

it('never goes below zero past useful life', function () {
    expect(buatAset()->nilaiBuku(Carbon::parse('2035-01-01')))->toBe(0.0);
});

it('returns price when depreciation inputs are missing', function () {
    expect(buatAset(['masa_manfaat_bulan' => null])->nilaiBuku())->toBe(12000000.00);
});
```

- [ ] **Step 3: Run it to confirm failure**

```bash
php artisan test --filter=AssetDepreciationTest
```
Expected: FAIL (`nilaiBuku` undefined / class relations missing).

- [ ] **Step 4: Implement the `Asset` model**

```php
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
```

Create `AssetFactory` returning valid data (unique `kode` like `AST-{n}`, `asset_category_id` => `AssetCategory::factory()`, `unit_id` => `Unit::factory()`, random `kondisi` `baik`).

- [ ] **Step 5: Run tests to confirm pass**

```bash
php artisan migrate --force
php artisan test --filter=AssetDepreciationTest
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add asset model with straight-line depreciation"
```

---

### Task 6: Asset movement / mutation (TDD)

**Files:**
- Create: migration `create_asset_movements_table`; `app/Models/AssetMovement.php`; factory
- Modify: `app/Models/Asset.php` (add `movements()` + `pindahKe()`)
- Test: `tests/Feature/AssetMovementTest.php`

**Interfaces:**
- Consumes: `Asset`, `Unit`, `User`.
- Produces: `Asset::movements()` (hasMany, latest first), `Asset::pindahKe(Unit $tujuan, ?string $keterangan, User $oleh): AssetMovement` — records a row and updates `assets.unit_id` atomically.

- [ ] **Step 1: Create migration**

```php
Schema::create('asset_movements', function (Blueprint $t) {
    $t->id();
    $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
    $t->foreignId('from_unit_id')->nullable()->constrained('units')->nullOnDelete();
    $t->foreignId('to_unit_id')->constrained('units')->cascadeOnDelete();
    $t->date('tanggal');
    $t->string('keterangan')->nullable();
    $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $t->timestamps();
});
```

- [ ] **Step 2: Write the failing movement test**

```php
<?php
// tests/Feature/AssetMovementTest.php
use App\Models\{Asset, Unit, User};

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('moves an asset and records history', function () {
    $igd = Unit::factory()->create(['nama' => 'IGD']);
    $poli = Unit::factory()->create(['nama' => 'Poli']);
    $user = User::factory()->stafIt()->create();
    $asset = Asset::factory()->create(['unit_id' => $igd->id]);

    $move = $asset->pindahKe($poli, 'Relokasi', $user);

    expect($asset->fresh()->unit_id)->toBe($poli->id)
        ->and($move->from_unit_id)->toBe($igd->id)
        ->and($move->to_unit_id)->toBe($poli->id)
        ->and($asset->movements()->count())->toBe(1);
});
```

- [ ] **Step 3: Run it to confirm failure**

```bash
php artisan test --filter=AssetMovementTest
```
Expected: FAIL (`pindahKe` undefined).

- [ ] **Step 4: Implement model + method**

`AssetMovement` model: `$fillable = ['asset_id','from_unit_id','to_unit_id','tanggal','keterangan','user_id']`, cast `tanggal`→date, relations `asset()`, `fromUnit()`, `toUnit()`, `user()`.

Add to `Asset`:

```php
use Illuminate\Support\Facades\DB;

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
```

- [ ] **Step 5: Run tests to confirm pass**

```bash
php artisan migrate --force && php artisan test --filter=AssetMovementTest
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add asset movement history with atomic unit update"
```

---

### Task 7: Ticket model + auto number + status log (TDD)

**Files:**
- Create: migrations `create_tickets_table`, `create_ticket_logs_table`, `create_ticket_attachments_table`; models `app/Models/Ticket.php`, `TicketLog.php`, `TicketAttachment.php`; factories
- Test: `tests/Feature/TicketNumberTest.php`, `tests/Feature/TicketStatusLogTest.php`

**Interfaces:**
- Consumes: `User`, `Unit`, `WorkCategory`, `Asset`.
- Produces: `Ticket` with auto `nomor` (`TKT-YYYY-NNNN`) generated on create from `tanggal`; relations `petugas()` (belongsTo User), `unit()`, `kategori()` (WorkCategory), `asset()`, `logs()` (hasMany, latest), `attachments()`; writing a changed `status` appends a `TicketLog` and setting `selesai` fills `selesai_at`.

- [ ] **Step 1: Create migrations**

```php
Schema::create('tickets', function (Blueprint $t) {
    $t->id();
    $t->string('nomor')->unique();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();       // petugas IT
    $t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
    $t->foreignId('work_category_id')->nullable()->constrained()->nullOnDelete();
    $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
    $t->string('judul');
    $t->text('deskripsi');
    $t->text('tindakan')->nullable();
    $t->enum('prioritas', ['rendah','sedang','tinggi'])->default('sedang');
    $t->enum('status', ['baru','proses','pending','selesai'])->default('baru');
    $t->date('tanggal');
    $t->timestamp('selesai_at')->nullable();
    $t->timestamps();
});

Schema::create('ticket_logs', function (Blueprint $t) {
    $t->id();
    $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
    $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $t->string('status_lama')->nullable();
    $t->string('status_baru');
    $t->string('catatan')->nullable();
    $t->timestamps();
});

Schema::create('ticket_attachments', function (Blueprint $t) {
    $t->id();
    $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
    $t->string('path');
    $t->string('nama_asli')->nullable();
    $t->timestamps();
});
```

- [ ] **Step 2: Write the failing number test**

```php
<?php
// tests/Feature/TicketNumberTest.php
use App\Models\{Ticket, User, Unit, WorkCategory};

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->user = User::factory()->stafIt()->create();
});

function baseTicket(array $o = []): array
{
    return array_merge([
        'user_id' => test()->user->id,
        'judul' => 'Perbaikan printer',
        'deskripsi' => 'Ganti roller',
        'tanggal' => '2026-02-10',
    ], $o);
}

it('assigns the first number of the year', function () {
    $t = Ticket::create(baseTicket());
    expect($t->nomor)->toBe('TKT-2026-0001');
});

it('increments within the same year', function () {
    Ticket::create(baseTicket());
    $t2 = Ticket::create(baseTicket());
    expect($t2->nomor)->toBe('TKT-2026-0002');
});

it('resets the sequence for a new year', function () {
    Ticket::create(baseTicket(['tanggal' => '2026-12-31']));
    $t = Ticket::create(baseTicket(['tanggal' => '2027-01-02']));
    expect($t->nomor)->toBe('TKT-2027-0001');
});
```

- [ ] **Step 3: Write the failing status-log test**

```php
<?php
// tests/Feature/TicketStatusLogTest.php
use App\Models\{Ticket, User};

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->user = User::factory()->stafIt()->create();
});

it('logs a status change and stamps completion', function () {
    $t = Ticket::create([
        'user_id' => $this->user->id, 'judul' => 'x', 'deskripsi' => 'y',
        'tanggal' => '2026-02-10', 'status' => 'baru',
    ]);

    $t->update(['status' => 'selesai']);

    expect($t->logs()->count())->toBe(1)
        ->and($t->logs()->first()->status_lama)->toBe('baru')
        ->and($t->logs()->first()->status_baru)->toBe('selesai')
        ->and($t->fresh()->selesai_at)->not->toBeNull();
});

it('does not log when status is unchanged', function () {
    $t = Ticket::create([
        'user_id' => $this->user->id, 'judul' => 'x', 'deskripsi' => 'y',
        'tanggal' => '2026-02-10', 'status' => 'baru',
    ]);
    $t->update(['judul' => 'diubah']);
    expect($t->logs()->count())->toBe(0);
});
```

- [ ] **Step 4: Run both to confirm failure**

```bash
php artisan migrate --force
php artisan test --filter="TicketNumberTest|TicketStatusLogTest"
```
Expected: FAIL.

- [ ] **Step 5: Implement `Ticket` (+ `TicketLog`, `TicketAttachment`)**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

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
        $tahun = \Illuminate\Support\Carbon::parse($tanggal ?? now())->format('Y');
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

    public function petugas()    { return $this->belongsTo(User::class, 'user_id'); }
    public function unit()       { return $this->belongsTo(Unit::class); }
    public function kategori()   { return $this->belongsTo(WorkCategory::class, 'work_category_id'); }
    public function asset()      { return $this->belongsTo(Asset::class); }
    public function logs()       { return $this->hasMany(TicketLog::class)->latest(); }
    public function attachments(){ return $this->hasMany(TicketAttachment::class); }
}
```

`TicketLog`: `$fillable = ['ticket_id','user_id','status_lama','status_baru','catatan']`, relations `ticket()`, `user()`.
`TicketAttachment`: `$fillable = ['ticket_id','path','nama_asli']`, relation `ticket()`.
Create a `TicketFactory` producing valid rows (`user_id` => `User::factory()`, `judul`, `deskripsi`, `tanggal` => today).

Add `repairs()` to `Asset`: `return $this->hasMany(Ticket::class)->latest('tanggal');`

- [ ] **Step 6: Run tests to confirm pass**

```bash
php artisan test --filter="TicketNumberTest|TicketStatusLogTest"
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add tickets with auto numbering and status logging"
```

---

### Task 8: Filament resources for Asset & Ticket (+ relation managers, attachments, movement action)

**Files:**
- Create: `app/Filament/Resources/AssetResource.php`, `TicketResource.php` (+ generated pages); relation managers `AssetResource/RelationManagers/MovementsRelationManager.php`, `AssetResource/RelationManagers/RepairsRelationManager.php`, `TicketResource/RelationManagers/LogsRelationManager.php`
- Modify: `AdminPanelProvider` (register plugins if needed — none required)
- Test: `tests/Feature/AssetTicketResourceTest.php`

**Interfaces:**
- Consumes: `Asset`, `Ticket`, `AssetMovement`, `TicketLog` models and relations from Tasks 5–7.
- Produces: full CRUD UI for assets and tickets under nav group "Operasional"; a "Pindah Unit" action on assets calling `Asset::pindahKe()`; file upload writing `ticket_attachments`.

- [ ] **Step 1: Generate resources**

```bash
php artisan make:filament-resource Asset --generate --no-interaction
php artisan make:filament-resource Ticket --generate --no-interaction
```

- [ ] **Step 2: Configure `AssetResource` form/table**

Set `navigationGroup = 'Operasional'`, Indonesian labels. Form fields: `kode` (required, unique ignoring record), `nama` (required), `asset_category_id` (Select relationship `kategori`,`nama`), `merk`, `model`, `serial_number`, `tanggal_beli` (DatePicker), `harga` (numeric, prefix "Rp"), `garansi_sampai` (DatePicker), `kondisi` (Select of the four enum values with Indonesian labels), `unit_id` (Select relationship `unit`,`nama`), `masa_manfaat_bulan` (numeric), `catatan` (Textarea). Table columns: `kode`, `nama`, `kategori.nama`, `unit.nama`, `kondisi` (badge), plus a computed `nilai_buku` column:

```php
Tables\Columns\TextColumn::make('nilai_buku')
    ->label('Nilai Buku')
    ->money('IDR')
    ->state(fn (\App\Models\Asset $r) => $r->nilaiBuku()),
```

Add table filters: `kondisi` (SelectFilter), `unit` (SelectFilter relationship), `asset_category_id` (SelectFilter relationship `kategori`).

- [ ] **Step 3: Add the "Pindah Unit" action**

In `AssetResource` table `->actions([...])`:

```php
Tables\Actions\Action::make('pindah')
    ->label('Pindah Unit')
    ->icon('heroicon-o-arrows-right-left')
    ->form([
        Forms\Components\Select::make('to_unit_id')
            ->label('Unit Tujuan')->relationship('unit', 'nama')->required(),
        Forms\Components\TextInput::make('keterangan')->label('Keterangan'),
    ])
    ->action(function (\App\Models\Asset $record, array $data) {
        $record->pindahKe(
            \App\Models\Unit::find($data['to_unit_id']),
            $data['keterangan'] ?? null,
            auth()->user(),
        );
    }),
```

- [ ] **Step 4: Configure `TicketResource` form/table**

Form: `judul` (required), `user_id` (Select relationship `petugas`,`name`, default `auth()->id()`), `tanggal` (DatePicker, default today), `unit_id`, `work_category_id` (Select relationship `kategori`,`nama`), `asset_id` (Select relationship `asset`,`nama`, searchable, nullable), `prioritas` (Select), `status` (Select), `deskripsi` (Textarea, required), `tindakan` (Textarea), and attachments:

```php
Forms\Components\FileUpload::make('lampiran')
    ->label('Foto/Lampiran')->multiple()->image()->directory('ticket-attachments'),
```

(The attachment field's persistence is defined concretely in Step 6 — use that version; this line just names where it goes in the form layout.) Make `nomor` a read-only display (not in create form — it is auto-generated). Table columns: `nomor`, `judul`, `petugas.name`, `unit.nama`, `kategori.nama`, `status` (badge with colors: baru=gray, proses=info, pending=warning, selesai=success), `prioritas` (badge), `tanggal`. Filters: `status`, `prioritas`, `unit`, `work_category_id`, `user_id` (petugas), and a date-range filter on `tanggal`.

- [ ] **Step 5: Relation managers**

Generate and wire:

```bash
php artisan make:filament-relation-manager AssetResource movements to_unit_id --no-interaction
php artisan make:filament-relation-manager AssetResource repairs nomor --no-interaction
php artisan make:filament-relation-manager TicketResource logs status_baru --no-interaction
```

Register all three in the respective resource `getRelations()`. Set `movements` and `logs` and `repairs` read-only where appropriate (repairs & logs: no create; movements create is handled by the Pindah action, so disable create there too). Show relevant columns (movements: `tanggal`, `fromUnit.nama`, `toUnit.nama`, `keterangan`, `user.name`; logs: `created_at`, `status_lama`, `status_baru`, `user.name`; repairs: `nomor`, `judul`, `status`, `tanggal`).

- [ ] **Step 6: Handle ticket attachments on save**

In `TicketResource\Pages\CreateTicket` and `EditTicket`, add an `afterCreate()/afterSave()` that persists uploaded files into `ticket_attachments`. Minimal approach — use a dedicated `Repeater`/`FileUpload` bound to the relation instead. Implement with a relation-based FileUpload in the form:

```php
Forms\Components\FileUpload::make('attachments_paths')
    ->label('Foto/Lampiran')->multiple()->image()
    ->directory('ticket-attachments')->dehydrated(false)
    ->afterStateHydrated(fn ($component, ?\App\Models\Ticket $record) =>
        $component->state($record?->attachments->pluck('path')->all() ?? []))
    ->saveRelationshipsUsing(function (\App\Models\Ticket $record, $state) {
        $record->attachments()->delete();
        foreach ((array) $state as $path) {
            $record->attachments()->create(['path' => $path]);
        }
    }),
```

- [ ] **Step 7: Write the resource test**

```php
<?php
// tests/Feature/AssetTicketResourceTest.php
use App\Models\{User, Asset, Unit, AssetCategory, WorkCategory};
use Filament\Facades\Filament;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('creates a ticket through the panel and auto-numbers it', function () {
    $unit = Unit::factory()->create();
    $kat  = WorkCategory::factory()->create();
    livewire(\App\Filament\Resources\TicketResource\Pages\CreateTicket::class)
        ->fillForm([
            'user_id' => auth()->id(),
            'judul' => 'Instalasi ulang Windows',
            'deskripsi' => 'PC front office',
            'tanggal' => now()->toDateString(),
            'unit_id' => $unit->id,
            'work_category_id' => $kat->id,
            'status' => 'baru',
            'prioritas' => 'sedang',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(\App\Models\Ticket::first()->nomor)
        ->toStartWith('TKT-'.now()->format('Y').'-');
});

it('lists assets', function () {
    Asset::factory()->count(3)->create();
    livewire(\App\Filament\Resources\AssetResource\Pages\ListAssets::class)
        ->assertOk()->assertCanSeeTableRecords(Asset::all());
});
```

- [ ] **Step 8: Run tests**

```bash
php artisan test --filter=AssetTicketResourceTest
```
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add Filament resources for assets and tickets"
```

---

### Task 9: Access policies (Staf IT cannot delete or manage master data)

**Files:**
- Create: `app/Policies/UnitPolicy.php`, `WorkCategoryPolicy.php`, `AssetCategoryPolicy.php`, `AssetPolicy.php`, `TicketPolicy.php`, `UserPolicy.php`
- Modify: master-data & user resources to respect policies (Filament reads policies automatically)
- Test: `tests/Feature/PolicyTest.php`

**Interfaces:**
- Consumes: roles (Task 3), all models.
- Produces: policies where `admin` can do everything; `staf_it` can `viewAny/view/create/update` Asset & Ticket but not `delete`; `staf_it` cannot `viewAny` master data or users.

- [ ] **Step 1: Write the failing policy test**

```php
<?php
// tests/Feature/PolicyTest.php
use App\Models\{User, Asset, Ticket, Unit};

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('lets admin delete but forbids staf_it', function () {
    $admin = User::factory()->admin()->create();
    $staf  = User::factory()->stafIt()->create();
    $asset = Asset::factory()->create();

    expect($admin->can('delete', $asset))->toBeTrue()
        ->and($staf->can('delete', $asset))->toBeFalse();
});

it('hides master data from staf_it', function () {
    $staf = User::factory()->stafIt()->create();
    expect($staf->can('viewAny', Unit::class))->toBeFalse();
});

it('allows staf_it to create tickets', function () {
    $staf = User::factory()->stafIt()->create();
    expect($staf->can('create', Ticket::class))->toBeTrue();
});
```

- [ ] **Step 2: Run it to confirm failure**

```bash
php artisan test --filter=PolicyTest
```
Expected: FAIL (default policies allow/deny incorrectly).

- [ ] **Step 3: Implement policies**

`AssetPolicy` and `TicketPolicy` (same shape):

```php
<?php
namespace App\Policies;

use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $u): bool { return $u->hasAnyRole(['admin','staf_it']); }
    public function view(User $u, $m): bool { return $u->hasAnyRole(['admin','staf_it']); }
    public function create(User $u): bool { return $u->hasAnyRole(['admin','staf_it']); }
    public function update(User $u, $m): bool { return $u->hasAnyRole(['admin','staf_it']); }
    public function delete(User $u, $m): bool { return $u->hasRole('admin'); }
}
```

Master-data & user policies (`UnitPolicy`, `WorkCategoryPolicy`, `AssetCategoryPolicy`, `UserPolicy`): every method `return $u->hasRole('admin');`.

Laravel 11 auto-discovers policies in `app/Policies` matching model names — no manual registration needed. Verify model/policy names line up (`Unit`→`UnitPolicy`, etc.).

- [ ] **Step 4: Run tests to confirm pass**

```bash
php artisan test --filter=PolicyTest
```
Expected: PASS.

- [ ] **Step 5: Manually confirm Filament hides nav**

```bash
php artisan test
```
Expected: whole suite green (Filament uses `viewAny` to hide navigation for staf_it).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: enforce role policies on resources"
```

---

### Task 10: Dashboard widgets

**Files:**
- Create: `app/Filament/Widgets/StatOverview.php`, `app/Filament/Widgets/TicketsByCategoryChart.php`, `app/Filament/Widgets/MonthlyTrendChart.php`
- Modify: `AdminPanelProvider` (auto-discovers widgets; confirm `->widgets([...])` or discovery path)
- Test: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `Ticket`, `Asset` models.
- Produces: three dashboard widgets rendering counts and charts.

- [ ] **Step 1: Stat overview widget**

```php
<?php
namespace App\Filament\Widgets;

use App\Models\{Ticket, Asset};
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatOverview extends Base
{
    protected function getStats(): array
    {
        return [
            Stat::make('Tiket Belum Selesai', Ticket::whereIn('status', ['baru','proses','pending'])->count()),
            Stat::make('Tiket Selesai', Ticket::where('status', 'selesai')->count()),
            Stat::make('Aset Rusak/Perbaikan', Asset::whereIn('kondisi', ['rusak','perbaikan'])->count()),
            Stat::make('Total Aset', Asset::count()),
        ];
    }
}
```

- [ ] **Step 2: Category chart widget (tickets grouped by work category)**

```php
<?php
namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketsByCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Tiket per Kategori';

    protected function getData(): array
    {
        $rows = Ticket::selectRaw('work_category_id, count(*) c')
            ->with('kategori')->groupBy('work_category_id')->get();
        return [
            'datasets' => [['label' => 'Tiket', 'data' => $rows->pluck('c')->all()]],
            'labels' => $rows->map(fn ($r) => $r->kategori?->nama ?? 'Lainnya')->all(),
        ];
    }

    protected function getType(): string { return 'bar'; }
}
```

- [ ] **Step 3: Monthly trend chart**

```php
<?php
namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class MonthlyTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Tren Pekerjaan per Bulan';

    protected function getData(): array
    {
        $rows = Ticket::selectRaw("DATE_FORMAT(tanggal, '%Y-%m') ym, count(*) c")
            ->groupBy('ym')->orderBy('ym')->get();
        return [
            'datasets' => [['label' => 'Tiket', 'data' => $rows->pluck('c')->all()]],
            'labels' => $rows->pluck('ym')->all(),
        ];
    }

    protected function getType(): string { return 'line'; }
}
```

- [ ] **Step 4: Write the dashboard test**

```php
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
        ->assertSee('Tiket Selesai');
});
```

- [ ] **Step 5: Run test**

```bash
php artisan test --filter=DashboardTest
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add dashboard stat and chart widgets"
```

---

### Task 11: Reports page with PDF & Excel export

**Files:**
- Create: `app/Filament/Pages/LaporanPekerjaan.php`, view `resources/views/filament/pages/laporan-pekerjaan.blade.php`, PDF view `resources/views/laporan/pekerjaan-pdf.blade.php`
- Modify: `composer.json` (dompdf), `AdminPanelProvider` (page auto-discovered)
- Test: `tests/Feature/LaporanTest.php`

**Interfaces:**
- Consumes: `Ticket` model + relations.
- Produces: a custom Filament page with a filter form (rentang tanggal, petugas, unit, kategori, status), a results table, and two actions `exportPdf` (dompdf stream) and `exportExcel` (CSV/Excel download). Query builder method `terapkanFilter(Builder $q, array $filter): Builder`.

- [ ] **Step 1: Install dompdf**

```bash
composer require barryvdh/laravel-dompdf -W
```

- [ ] **Step 2: Create the page class**

```php
<?php
namespace App\Filament\Pages;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Filament\Pages\Page;

class LaporanPekerjaan extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Laporan';
    protected static ?string $title = 'Laporan Pekerjaan';
    protected static string $view = 'filament.pages.laporan-pekerjaan';

    public ?string $dari = null;
    public ?string $sampai = null;
    public ?int $user_id = null;
    public ?int $unit_id = null;
    public ?int $work_category_id = null;
    public ?string $status = null;

    public static function terapkanFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
            ->when($f['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '<=', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['unit_id'] ?? null, fn ($q, $v) => $q->where('unit_id', $v))
            ->when($f['work_category_id'] ?? null, fn ($q, $v) => $q->where('work_category_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
    }

    public function hasilQuery(): Builder
    {
        return static::terapkanFilter(
            Ticket::query()->with(['petugas','unit','kategori'])->latest('tanggal'),
            $this->only(['dari','sampai','user_id','unit_id','work_category_id','status']),
        );
    }

    public function exportPdf()
    {
        $tickets = $this->hasilQuery()->get();
        $pdf = Pdf::loadView('laporan.pekerjaan-pdf', ['tickets' => $tickets]);
        return response()->streamDownload(fn () => print($pdf->output()), 'laporan-pekerjaan.pdf');
    }

    public function exportExcel()
    {
        $tickets = $this->hasilQuery()->get();
        return response()->streamDownload(function () use ($tickets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nomor','Tanggal','Petugas','Unit','Kategori','Judul','Status']);
            foreach ($tickets as $t) {
                fputcsv($out, [
                    $t->nomor, $t->tanggal?->format('Y-m-d'), $t->petugas?->name,
                    $t->unit?->nama, $t->kategori?->nama, $t->judul, $t->status,
                ]);
            }
            fclose($out);
        }, 'laporan-pekerjaan.csv', ['Content-Type' => 'text/csv']);
    }
}
```

- [ ] **Step 3: Create the Blade views**

`resources/views/filament/pages/laporan-pekerjaan.blade.php`: a `<x-filament-panels::page>` with a filter form bound to the public properties (date inputs for `dari`/`sampai`, selects for petugas/unit/kategori/status), buttons `wire:click="exportPdf"` and `wire:click="exportExcel"`, and a `@php $rows = $this->hasilQuery()->get(); @endphp` table listing results.

`resources/views/laporan/pekerjaan-pdf.blade.php`: a plain HTML table (nomor, tanggal, petugas, unit, kategori, judul, status) with a heading "Laporan Pekerjaan IT" and print date.

- [ ] **Step 4: Write the report test**

```php
<?php
// tests/Feature/LaporanTest.php
use App\Models\{User, Ticket, Unit};
use App\Filament\Pages\LaporanPekerjaan;
use Illuminate\Database\Eloquent\Builder;

beforeEach(fn () => $this->seed(\Database\Seeders\RoleSeeder::class));

it('filters tickets by date range', function () {
    $u = User::factory()->stafIt()->create();
    Ticket::factory()->create(['user_id' => $u->id, 'tanggal' => '2026-01-10']);
    Ticket::factory()->create(['user_id' => $u->id, 'tanggal' => '2026-03-10']);

    $q = LaporanPekerjaan::terapkanFilter(
        Ticket::query(),
        ['dari' => '2026-02-01', 'sampai' => '2026-03-31'],
    );

    expect($q->count())->toBe(1);
});

it('filters by unit and status together', function () {
    $u = User::factory()->stafIt()->create();
    $unit = Unit::factory()->create();
    Ticket::factory()->create(['user_id' => $u->id, 'unit_id' => $unit->id, 'status' => 'selesai']);
    Ticket::factory()->create(['user_id' => $u->id, 'unit_id' => $unit->id, 'status' => 'baru']);

    $q = LaporanPekerjaan::terapkanFilter(
        Ticket::query(),
        ['unit_id' => $unit->id, 'status' => 'selesai'],
    );
    expect($q->count())->toBe(1);
});
```

- [ ] **Step 5: Run test**

```bash
php artisan test --filter=LaporanTest
```
Expected: PASS.

- [ ] **Step 6: Full suite + manual export check**

```bash
php artisan test
```
Expected: entire suite green. (Manually: open `/admin`, go to Laporan, set a range, click Export PDF and Export Excel — files download.)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add filtered work report with PDF and Excel export"
```

---

### Task 12: Seed demo data + final verification

**Files:**
- Create: `database/seeders/DemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: existing suite

**Interfaces:**
- Consumes: all models.
- Produces: realistic sample units, categories, assets, and tickets for a walkthrough.

- [ ] **Step 1: Write `DemoSeeder`**

Seed ~6 units (IGD, Poli, Farmasi, Laboratorium, Radiologi, Administrasi), work categories (Hardware, Jaringan, Software, SIMRS, Printer), asset categories (Komputer, Printer, Switch, UPS), ~15 assets across units, and ~30 tickets across the current year with mixed statuses/categories. Guard it behind `app()->environment('local')` inside `DatabaseSeeder` or call explicitly. Do **not** call `DemoSeeder` automatically in production seeding.

- [ ] **Step 2: Run the seeder against the dev DB**

```bash
php artisan migrate:fresh --seed --force
php artisan db:seed --class=DemoSeeder --force
```
Expected: no errors; `/admin` shows populated dashboard, assets, tickets, and a working report.

- [ ] **Step 3: Run the full test suite**

```bash
php artisan test
```
Expected: all tests PASS.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: add demo seeder and finalize"
```

---

## Notes for the executor

- Run `php artisan migrate:fresh --seed` freely in dev; the DB `helpdesk_it` is disposable.
- If Filament asset publishing is needed after install, run `php artisan filament:assets`.
- To switch DB later, only `.env` changes — no code depends on the driver.
- `auth()->id()` is null in unit context; the ticket status log tolerates a null `user_id` (nullable FK).
