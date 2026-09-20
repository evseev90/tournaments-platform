<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DoubleEliminationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function double_bracket_is_generated_with_both_branches(): void
    {
        $manage = $this->withParticipants(8);

        $this->post("$manage/stage", ['format' => 'double_elimination'])->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->where('tournament.stages.0.format', 'double_elimination')
            ->has('tournament.stages.0.rounds', 8)
            ->where('tournament.stages.0.rounds.0.fixtures.0.bracket', 'winners')
            ->where('tournament.stages.0.standings', null));

        $fixtures = $this->fixtures($manage);
        self::assertCount(14, $fixtures);
        self::assertSame(
            ['winners' => 7, 'losers' => 6, 'grand_final' => 1],
            array_count_values(array_column($fixtures, 'bracket')),
        );
    }

    #[Test]
    public function bracket_survives_a_round_trip_through_the_database(): void
    {
        $manage = $this->withParticipants(5);
        $this->post("$manage/stage", ['format' => 'double_elimination']);

        $fixtures = $this->fixtures($manage);
        $playable = array_filter($fixtures, static fn (array $f) => $f['status'] !== 'bye');
        self::assertCount(8, $playable, 'пять участников играют восемь матчей');
        self::assertCount(3, array_diff_key($fixtures, $playable), 'остальное — проходы в верхней сетке');

        foreach ($fixtures as $fixture) {
            self::assertContains($fixture['bracket'], ['winners', 'losers', 'grand_final']);
        }
        $loserLinks = array_filter($fixtures, static fn (array $f) => $f['nextLose'] !== null);
        self::assertNotEmpty($loserLinks, 'проигравшие верхней сетки должны иметь адрес в нижней');
    }

    #[Test]
    public function losing_once_keeps_a_participant_in_the_tournament(): void
    {
        $manage = $this->withParticipants(4);
        $this->post("$manage/stage", ['format' => 'double_elimination']);

        $stageId = $this->props($manage)['tournament']['stages'][0]['id'];
        $first = $this->fixtures($manage)[0];
        $loser = $first['awayId'];

        $this->post("$manage/stages/$stageId/fixtures/{$first['id']}/result", [
            'outcome' => ['type' => 'sets', 'sets' => [[11, 5], [11, 5], [11, 5]]],
        ])->assertSessionDoesntHaveErrors();

        $lower = array_values(array_filter($this->fixtures($manage), static fn (array $f) => $f['bracket'] === 'losers'));
        $waiting = array_filter($lower, static fn (array $f) => $f['homeId'] === $loser || $f['awayId'] === $loser);
        self::assertNotEmpty($waiting, 'проигравший ждёт своего матча в нижней сетке');
    }

    #[Test]
    public function whole_double_bracket_can_be_played_through_the_web(): void
    {
        $manage = $this->withParticipants(6);
        $this->post("$manage/stage", ['format' => 'double_elimination']);
        $stageId = $this->props($manage)['tournament']['stages'][0]['id'];

        $played = 0;
        for ($pass = 0; $pass < 20; $pass++) {
            $ready = array_filter($this->fixtures($manage), static fn (array $f) => $f['status'] === 'ready');
            if ($ready === []) {
                break;
            }
            foreach ($ready as $fixture) {
                $this->post("$manage/stages/$stageId/fixtures/{$fixture['id']}/result", [
                    'outcome' => ['type' => 'sets', 'sets' => [[11, 5], [11, 5], [11, 5]]],
                ])->assertSessionDoesntHaveErrors();
                $played++;
            }
        }

        self::assertSame(10, $played, 'шесть участников дают десять матчей');
        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.stages.0.status', 'completed'));

        $grandFinal = array_values(array_filter($this->fixtures($manage), static fn (array $f) => $f['bracket'] === 'grand_final'))[0];
        self::assertNotNull($grandFinal['winnerId']);
    }

    #[Test]
    public function schedule_covers_the_lower_bracket_too(): void
    {
        $manage = $this->withParticipants(4);
        $this->post("$manage/stage", ['format' => 'double_elimination']);
        $this->post("$manage/venues", ['name' => 'Стол 1']);
        $this->patch("$manage/schedule", ['settings' => [
            'starts_at' => '2026-10-03T10:00:00+00:00',
            'match_minutes' => 30,
            'break_minutes' => 0,
            'rest_minutes' => 0,
        ]]);

        $this->post("$manage/schedule")->assertRedirect($manage);

        self::assertCount(6, $this->props($manage)['schedule']['assignments']);
    }

    private function withParticipants(int $count): string
    {
        $location = $this->post('/tournaments', ['name' => 'Двойная', 'sport' => 'table_tennis'])->headers->get('Location');
        self::assertIsString($location);
        $manage = parse_url($location, PHP_URL_PATH) ?: $location;

        $this->post("$manage/participants", [
            'type' => 'player',
            'participants' => array_map(static fn (int $i) => ['name' => "Игрок $i"], range(1, $count)),
        ]);

        return $manage;
    }

    /** @return list<array<string, mixed>> */
    private function fixtures(string $manage): array
    {
        $rounds = $this->props($manage)['tournament']['stages'][0]['rounds'];

        return array_merge(...array_map(static fn (array $round) => $round['fixtures'], $rounds));
    }

    /** @return array<string, mixed> */
    private function props(string $url): array
    {
        $page = $this->get($url)->viewData('page');
        $decoded = json_decode(json_encode($page, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded['props'];
    }
}
