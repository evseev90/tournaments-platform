<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GroupsFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function groups_split_participants_by_snake_seeding(): void
    {
        $manage = $this->withParticipants(8);

        $this->post("$manage/groups", ['groups' => 2])->assertRedirect($manage)->assertSessionHas('success');

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->where('tournament.status', 'in_progress')
            ->has('tournament.stages', 2)
            ->where('tournament.stages.0.name', 'Группа A')
            ->where('tournament.stages.0.phase', 1)
            ->where('tournament.stages.0.format', 'round_robin')
            ->has('tournament.stages.0.standings', 4)
            ->where('tournament.stages.1.name', 'Группа B'));

        $stages = $this->props($manage)['tournament']['stages'];
        $names = static fn (array $stage) => array_map(
            static fn (array $row) => $row['participantId'],
            $stage['standings'],
        );
        self::assertCount(4, $names($stages[0]));
        self::assertEmpty(array_intersect($names($stages[0]), $names($stages[1])), 'участник играет только в своей группе');
    }

    #[Test]
    public function groups_need_enough_participants(): void
    {
        $manage = $this->withParticipants(3);

        $this->from($manage)->post("$manage/groups", ['groups' => 2])->assertSessionHasErrors('domain');
        $this->from($manage)->post("$manage/groups", ['groups' => 1])->assertSessionHasErrors('groups');
    }

    #[Test]
    public function playoff_waits_until_every_group_is_played(): void
    {
        $manage = $this->withParticipants(8);
        $this->post("$manage/groups", ['groups' => 2]);

        $this->from($manage)->post("$manage/playoff", ['advance_per_group' => 2])->assertSessionHasErrors('domain');

        $this->playGroups($manage);

        $this->post("$manage/playoff", ['advance_per_group' => 2, 'third_place_match' => true])
            ->assertRedirect($manage)->assertSessionHas('success');

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->has('tournament.stages', 3)
            ->where('tournament.stages.2.name', 'Плей-офф')
            ->where('tournament.stages.2.phase', 2)
            ->where('tournament.stages.2.format', 'single_elimination')
            ->where('tournament.stages.2.standings', null)
            ->has('tournament.stages.2.rounds', 2));
    }

    #[Test]
    public function playoff_pairs_group_winners_against_other_runners_up(): void
    {
        $manage = $this->withParticipants(8);
        $this->post("$manage/groups", ['groups' => 2]);
        $this->playGroups($manage);

        $before = $this->props($manage)['tournament']['stages'];
        $winners = array_map(static fn (array $group) => $group['standings'][0]['participantId'], [$before[0], $before[1]]);
        $seconds = array_map(static fn (array $group) => $group['standings'][1]['participantId'], [$before[0], $before[1]]);

        $this->post("$manage/playoff", ['advance_per_group' => 2]);

        $semis = $this->props($manage)['tournament']['stages'][2]['rounds'][0]['fixtures'];
        self::assertSame($winners[0], $semis[0]['homeId']);
        self::assertSame($seconds[1], $semis[0]['awayId'], 'победитель A против второго места B');
        self::assertSame($winners[1], $semis[1]['homeId']);
        self::assertSame($seconds[0], $semis[1]['awayId']);
    }

    #[Test]
    public function playoff_cannot_be_built_twice(): void
    {
        $manage = $this->withParticipants(8);
        $this->post("$manage/groups", ['groups' => 2]);
        $this->playGroups($manage);
        $this->post("$manage/playoff", ['advance_per_group' => 1]);

        $this->from($manage)->post("$manage/playoff", ['advance_per_group' => 2])->assertSessionHasErrors('domain');
    }

    #[Test]
    public function discarding_removes_only_the_last_phase(): void
    {
        $manage = $this->withParticipants(8);
        $this->post("$manage/groups", ['groups' => 2]);
        $this->playGroups($manage);
        $this->post("$manage/playoff", ['advance_per_group' => 2]);

        $this->delete("$manage/stage")->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->has('tournament.stages', 2)
            ->where('tournament.status', 'in_progress'), 'группы остались, турнир идёт');

        // Групповой этап уже сыгран, поэтому снять его нельзя.
        $this->from($manage)->delete("$manage/stage")->assertSessionHasErrors('domain');
    }

    #[Test]
    public function schedule_plans_groups_in_parallel_and_playoff_after(): void
    {
        $manage = $this->withParticipants(8);
        $this->post("$manage/groups", ['groups' => 2]);
        $this->post("$manage/venues", ['name' => 'Стол 1']);
        $this->post("$manage/venues", ['name' => 'Стол 2']);
        $this->patch("$manage/schedule", ['settings' => [
            'starts_at' => '2026-10-03T10:00:00+00:00',
            'match_minutes' => 30,
            'break_minutes' => 0,
            'rest_minutes' => 0,
        ]]);

        $this->post("$manage/schedule")->assertRedirect($manage);

        $assignments = $this->props($manage)['schedule']['assignments'];
        self::assertCount(12, $assignments, 'по шесть матчей в каждой группе');
        $firstTwo = array_slice(array_map(static fn (array $a) => substr($a['startsAt'], 11, 5), $assignments), 0, 2);
        self::assertSame(['10:00', '10:00'], $firstTwo, 'группы играют параллельно на двух столах');
    }

    private function playGroups(string $manage): void
    {
        foreach ($this->props($manage)['tournament']['stages'] as $stage) {
            foreach ($stage['rounds'] as $round) {
                foreach ($round['fixtures'] as $fixture) {
                    if ($fixture['status'] !== 'ready') {
                        continue;
                    }
                    // Выше по посеву выигрывает: таблицы групп получаются предсказуемыми.
                    $this->post("$manage/stages/{$stage['id']}/fixtures/{$fixture['id']}/result", [
                        'outcome' => ['type' => 'sets', 'sets' => [[11, 5], [11, 5], [11, 5]]],
                    ])->assertSessionDoesntHaveErrors();
                }
            }
        }
    }

    private function withParticipants(int $count): string
    {
        $location = $this->post('/tournaments', ['name' => 'Тест', 'sport' => 'table_tennis'])->headers->get('Location');
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
