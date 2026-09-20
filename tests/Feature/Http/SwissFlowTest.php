<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SwissFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function swiss_starts_with_a_single_round(): void
    {
        $manage = $this->withParticipants(8);

        $this->post("$manage/stage", ['format' => 'swiss'])->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->where('tournament.stages.0.format', 'swiss')
            ->where('tournament.stages.0.plannedRounds', 3)
            ->where('tournament.stages.0.awaitsNextRound', false)
            ->has('tournament.stages.0.rounds', 1)
            ->has('tournament.stages.0.rounds.0.fixtures', 4)
            ->has('tournament.stages.0.standings', 8));
    }

    #[Test]
    public function round_count_can_be_chosen(): void
    {
        $manage = $this->withParticipants(6);

        $this->post("$manage/stage", ['format' => 'swiss', 'settings' => ['rounds' => 5]])->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.stages.0.plannedRounds', 5));
    }

    #[Test]
    public function next_round_is_paired_only_after_the_current_one_is_played(): void
    {
        $manage = $this->withParticipants(8);
        $this->post("$manage/stage", ['format' => 'swiss']);
        $stageId = $this->stage($manage)['id'];

        $this->from($manage)->post("$manage/stages/$stageId/round")->assertSessionHasErrors('domain');

        $this->playCurrentRound($manage, $stageId);
        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.stages.0.awaitsNextRound', true));

        $this->post("$manage/stages/$stageId/round")->assertRedirect($manage)->assertSessionHas('success');

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->has('tournament.stages.0.rounds', 2)
            ->where('tournament.stages.0.awaitsNextRound', false)
            ->where('tournament.stages.0.status', 'in_progress'));
    }

    #[Test]
    public function tournament_runs_to_the_end_without_repeating_pairs(): void
    {
        $manage = $this->withParticipants(8);
        $this->post("$manage/stage", ['format' => 'swiss']);
        $stageId = $this->stage($manage)['id'];

        for ($round = 1; $round <= 3; $round++) {
            $this->playCurrentRound($manage, $stageId);
            if ($this->stage($manage)['awaitsNextRound']) {
                $this->post("$manage/stages/$stageId/round")->assertSessionDoesntHaveErrors();
            }
        }

        $stage = $this->stage($manage);
        self::assertSame('completed', $stage['status']);
        self::assertCount(3, $stage['rounds']);

        $seen = [];
        foreach ($stage['rounds'] as $round) {
            foreach ($round['fixtures'] as $fixture) {
                $key = implode('|', [min($fixture['homeId'], $fixture['awayId']), max($fixture['homeId'], $fixture['awayId'])]);
                self::assertArrayNotHasKey($key, $seen, 'пара повторилась');
                $seen[$key] = true;
            }
        }

        $table = $stage['standings'];
        self::assertSame(6, $table[0]['score'], 'лидер выиграл все три тура');
        self::assertGreaterThan(0, $table[0]['buchholz']);
        self::assertSame(range(1, 8), array_column($table, 'rank'));
    }

    #[Test]
    public function odd_field_gets_a_bye_that_counts_in_the_table(): void
    {
        $manage = $this->withParticipants(5);
        $this->post("$manage/stage", ['format' => 'swiss']);
        $stageId = $this->stage($manage)['id'];

        $stage = $this->stage($manage);
        $byes = array_filter($stage['rounds'][0]['fixtures'], static fn (array $f) => $f['status'] === 'bye');
        self::assertCount(1, $byes);

        $this->playCurrentRound($manage, $stageId);

        $table = $this->stage($manage)['standings'];
        self::assertSame([1, 1, 1, 1, 1], array_column($table, 'played'), 'пропуск тура тоже считается сыгранным');
    }

    #[Test]
    public function schedule_covers_paired_rounds(): void
    {
        $manage = $this->withParticipants(6);
        $this->post("$manage/stage", ['format' => 'swiss']);
        $this->post("$manage/venues", ['name' => 'Стол 1']);
        $this->patch("$manage/schedule", ['settings' => [
            'starts_at' => '2026-10-03T10:00:00+00:00',
            'match_minutes' => 20,
            'break_minutes' => 0,
            'rest_minutes' => 0,
        ]]);

        $this->post("$manage/schedule")->assertRedirect($manage);

        self::assertCount(3, $this->props($manage)['schedule']['assignments'], 'пока запланирован только первый тур');
    }

    private function playCurrentRound(string $manage, string $stageId): void
    {
        foreach ($this->stage($manage)['rounds'] as $round) {
            foreach ($round['fixtures'] as $fixture) {
                if ($fixture['status'] !== 'ready') {
                    continue;
                }
                $this->post("$manage/stages/$stageId/fixtures/{$fixture['id']}/result", [
                    'outcome' => ['type' => 'sets', 'sets' => [[11, 6], [11, 6], [11, 6]]],
                ])->assertSessionDoesntHaveErrors();
            }
        }
    }

    /** @return array<string, mixed> */
    private function stage(string $manage): array
    {
        return $this->props($manage)['tournament']['stages'][0];
    }

    private function withParticipants(int $count): string
    {
        $location = $this->post('/tournaments', ['name' => 'Швейцарка', 'sport' => 'table_tennis'])->headers->get('Location');
        self::assertIsString($location);
        $manage = parse_url($location, PHP_URL_PATH) ?: $location;

        $this->post("$manage/participants", [
            'type' => 'player',
            'participants' => array_map(static fn (int $i) => ['name' => "Игрок $i"], range(1, $count)),
        ]);

        return $manage;
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
