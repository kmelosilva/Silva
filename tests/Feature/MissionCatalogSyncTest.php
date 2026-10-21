<?php

namespace Tests\Feature;

use App\Models\Coordinate;
use App\Models\CoordinateCompletion;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserMission;
use App\Services\MissionCatalogSync;
use App\Support\EnlistmentText;
use Database\Seeders\MissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MissionCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_rf02_carga_do_zero_grava_8_missoes_e_11_coordenadas(): void
    {
        $this->seed(MissionCatalogSeeder::class);

        $this->assertSame(8, Mission::count());
        $this->assertSame(11, Coordinate::count());
        $this->assertSame(11, Coordinate::published()->count());

        $entry = Mission::where('slug', 'seja-um-agente')->firstOrFail();
        $this->assertTrue($entry->is_entry);
        $this->assertNull($entry->category);
    }

    public function test_rn08_coordenada_de_entrada_e_o_codigo_de_etica_do_alistamento(): void
    {
        $this->seed(MissionCatalogSeeder::class);

        $content = Coordinate::where('slug', 'siga-o-codigo-de-etica')->firstOrFail()->content;
        $items = EnlistmentText::codeOfConduct();

        $this->assertNotEmpty($items);
        foreach ($items as $index => $item) {
            $this->assertStringContainsString(
                ($index + 1).'. '.$item['title'].' '.$item['text'],
                $content,
                'A coordenada deveria trazer o item '.($index + 1).' do código, igual ao do alistamento.'
            );
        }
    }

    public function test_rf04_carga_dupla_nao_duplica_nem_regrava(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $this->assertGreaterThan(0, Coordinate::count(), 'A primeira carga precisa ter gravado algo.');
        $before = $this->snapshot();

        $this->travel(5)->minutes();
        $this->seed(MissionCatalogSeeder::class);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_rf04_carga_nao_mexe_no_progresso_dos_agentes(): void
    {
        $this->seed(MissionCatalogSeeder::class);
        $user = User::factory()->create();
        $user->agent->update(['level' => 3, 'xp' => 120, 'streak_current' => 2, 'streak_best' => 5]);
        $mission = Mission::where('slug', 'negociando-com-cronos')->firstOrFail();
        UserMission::create([
            'user_id' => $user->id,
            'mission_id' => $mission->id,
            'accepted_at' => '2026-10-18 09:00:00',
            'completed_at' => '2026-10-18 10:00:00',
        ]);
        CoordinateCompletion::create([
            'user_id' => $user->id,
            'coordinate_id' => $mission->coordinates()->value('id'),
            'xp' => 20,
            'completed_at' => '2026-10-18 10:00:00',
        ]);
        $before = $this->progressSnapshot();
        $this->assertGreaterThan(0, count($before['coordinate_completions']));

        $this->travel(5)->minutes();
        $this->seed(MissionCatalogSeeder::class);

        $this->assertEquals($before, $this->progressSnapshot());
    }

    public function test_rf04_coordenada_fora_do_catalogo_e_aposentada_e_volta_quando_reaparece(): void
    {
        $catalog = $this->catalog();
        $sync = app(MissionCatalogSync::class);
        $sync->sync($catalog);

        $withoutOne = $catalog;
        $withoutOne[2]['coordinates'] = array_slice($withoutOne[2]['coordinates'], 0, 2);
        $result = $sync->sync($withoutOne);

        $coordinate = Coordinate::where('slug', 'escreva-quem-voce-e-hoje')->firstOrFail();
        $this->assertSame(1, $result['retired_coordinates']);
        $this->assertNotNull($coordinate->retired_at);
        $this->assertSame(10, Coordinate::published()->count());
        $this->assertSame(11, Coordinate::count(), 'Aposentar não pode apagar a linha.');

        $sync->sync($catalog);

        $this->assertNull($coordinate->fresh()->retired_at);
        $this->assertSame(11, Coordinate::published()->count());
    }

    public function test_rf02_missoes_geradas_sao_aposentadas_sem_ser_apagadas(): void
    {
        $generated = Mission::factory()->generated()->count(3)->create();

        $this->seed(MissionCatalogSeeder::class);

        $this->assertSame(11, Mission::count());
        foreach ($generated as $mission) {
            $this->assertNotNull($mission->fresh()->retired_at);
        }
    }

    public function test_rf02_texto_em_varias_linhas_vira_paragrafo(): void
    {
        $catalog = $this->catalog();
        $catalog[1]['coordinates'][0]['content'] = "primeira linha\n   segunda linha\n\n   outro parágrafo\n";

        app(MissionCatalogSync::class)->sync($catalog);

        $this->assertSame(
            "primeira linha segunda linha\n\noutro parágrafo",
            Coordinate::where('slug', 'acorde-meia-hora-antes')->value('content')
        );
    }

    /**
     * @param  callable(array): array  $break
     */
    #[DataProvider('invalidCatalogs')]
    public function test_rf03_catalogo_invalido_e_recusado_sem_gravar_nada(callable $break, string $message): void
    {
        $catalog = $break($this->catalog());

        try {
            app(MissionCatalogSync::class)->sync($catalog);
            $this->fail('A carga deveria ter sido recusada.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertSame(0, Mission::count());
        $this->assertSame(0, Coordinate::count());
    }

    /**
     * @return array<string, array{callable(array): array, string}>
     */
    public static function invalidCatalogs(): array
    {
        return [
            'categoria fora da lista' => [fn (array $c) => self::with($c, 1, 'category', 'corpo'), 'Categoria fora da lista'],
            'missão de módulo sem categoria' => [fn (array $c) => self::with($c, 1, 'category', null), 'Categoria fora da lista'],
            'missão de entrada com categoria' => [fn (array $c) => self::with($c, 0, 'category', 'mente'), 'não pode ter categoria'],
            'slug de missão repetido' => [fn (array $c) => self::with($c, 2, 'slug', 'nao-espere-o-sol'), 'Slug repetido'],
            'slug fora do padrão' => [fn (array $c) => self::with($c, 1, 'slug', 'Não Espere'), 'Slug inválido'],
            'missão sem coordenadas' => [fn (array $c) => self::with($c, 1, 'coordinates', []), 'não tem coordenadas'],
            'título vazio' => [fn (array $c) => self::with($c, 1, 'title', '  '), 'Título inválido'],
            'coordenada com XP zero' => [function (array $c) {
                $c[1]['coordinates'][0]['xp'] = 0;

                return $c;
            }, 'XP precisa ser um inteiro maior que zero'],
            'coordenada repetida na missão' => [function (array $c) {
                $c[2]['coordinates'][1]['slug'] = $c[2]['coordinates'][0]['slug'];

                return $c;
            }, 'Slug repetido na missão corpo-sao-mente-sa'],
            'catálogo vazio' => [fn (array $c) => [], 'está vazio'],
        ];
    }

    private function catalog(): array
    {
        return require database_path('data/mission_catalog.php');
    }

    private static function with(array $catalog, int $mission, string $key, mixed $value): array
    {
        $catalog[$mission][$key] = $value;

        return $catalog;
    }

    private function snapshot(): array
    {
        return [
            Mission::count(),
            Coordinate::count(),
            Mission::max('updated_at'),
            Coordinate::max('updated_at'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function progressSnapshot(): array
    {
        return collect(['agents', 'user_missions', 'coordinate_completions'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->toArray()])
            ->all();
    }
}
