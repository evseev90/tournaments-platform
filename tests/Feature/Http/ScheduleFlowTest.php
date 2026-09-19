<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tournaments\Scheduling\Domain\MatchId;
use Tournaments\Scheduling\Domain\ScheduleRepository;
use Tournaments\Scheduling\Domain\TournamentRef;

final class ScheduleFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function schedule_starts_empty_and_appears_with_first_venue(): void
    {
        $manage = $this->runningTournament();

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->has('schedule.venues', 0)
            ->has('schedule.assignments', 0)
            ->where('schedule.planned', false)
            ->has('schedule.settings.starts_at'));

        $this->post("$manage/venues", ['name' => 'Стол 1'])->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->has('schedule.venues', 1)
            ->where('schedule.venues.0.name', 'Стол 1')
            ->where('schedule.venues.0.order', 1));
    }

    #[Test]
    public function venue_name_is_validated_and_can_be_changed(): void
    {
        $manage = $this->runningTournament();
        $this->from($manage)->post("$manage/venues", ['name' => ''])->assertSessionHasErrors('name');

        $this->post("$manage/venues", ['name' => 'Корт A']);
        $venue = $this->props($manage)['schedule']['venues'][0];

        $this->patch("$manage/venues/{$venue['id']}", ['name' => 'Корт Б'])->assertRedirect($manage);
        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('schedule.venues.0.name', 'Корт Б'));

        $this->delete("$manage/venues/{$venue['id']}")->assertRedirect($manage);
        $this->get($manage)->assertInertia(fn (Assert $page) => $page->has('schedule.venues', 0));
    }

    #[Test]
    public function planning_fills_every_playable_match(): void
    {
        $manage = $this->runningTournament(participants: 4);
        $this->post("$manage/venues", ['name' => 'Стол 1']);
        $this->post("$manage/venues", ['name' => 'Стол 2']);
        $this->patch("$manage/schedule", ['settings' => [
            'starts_at' => '2026-10-03T10:00:00+00:00',
            'match_minutes' => 30,
            'break_minutes' => 0,
            'rest_minutes' => 15,
        ]])->assertRedirect($manage);

        $this->post("$manage/schedule")->assertRedirect($manage)->assertSessionHas('success');

        $schedule = $this->props($manage)['schedule'];
        self::assertTrue($schedule['planned']);
        self::assertCount(3, $schedule['assignments'], 'два полуфинала и финал');

        $starts = array_map(static fn (array $a) => substr($a['startsAt'], 11, 5), $schedule['assignments']);
        self::assertSame(['10:00', '10:00', '10:45'], $starts, 'финал ждёт полуфиналы и даёт отдых');
        self::assertNotSame($schedule['assignments'][0]['venueId'], $schedule['assignments'][1]['venueId']);
    }

    #[Test]
    public function planning_needs_venues_and_a_bracket(): void
    {
        $draft = $this->createTournament();
        $this->post("$draft/participants", ['type' => 'player', 'participants' => [['name' => 'A'], ['name' => 'B']]]);
        $this->from($draft)->post("$draft/schedule")->assertSessionHasErrors('domain');

        $manage = $this->runningTournament();
        $this->from($manage)->post("$manage/schedule")->assertSessionHasErrors('domain');
    }

    #[Test]
    public function organizer_moves_match_and_cannot_double_book(): void
    {
        $manage = $this->plannedTournament();
        $assignments = $this->props($manage)['schedule']['assignments'];
        [$first, $second] = $assignments;

        $this->patch("$manage/schedule/matches/{$first['matchId']}", [
            'venue_id' => $first['venueId'],
            'starts_at' => '2026-10-03T16:00:00+00:00',
        ])->assertRedirect($manage)->assertSessionHas('success');

        $moved = $this->assignmentOf($manage, $first['matchId']);
        self::assertSame('16:00', substr($moved['startsAt'], 11, 5));

        $this->from($manage)->patch("$manage/schedule/matches/{$second['matchId']}", [
            'venue_id' => $moved['venueId'],
            'starts_at' => $moved['startsAt'],
        ])->assertSessionHasErrors('domain');
    }

    #[Test]
    public function clearing_keeps_venues(): void
    {
        $manage = $this->plannedTournament();

        $this->delete("$manage/schedule")->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->has('schedule.assignments', 0)
            ->has('schedule.venues', 2)
            ->where('schedule.planned', false));
    }

    #[Test]
    public function discarding_bracket_also_clears_the_schedule(): void
    {
        $manage = $this->plannedTournament();

        $this->delete("$manage/stage")->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->where('tournament.status', 'draft')
            ->has('schedule.assignments', 0)
            ->has('schedule.venues', 2));
    }

    #[Test]
    public function result_makes_next_match_competitors_known_to_the_schedule(): void
    {
        $manage = $this->plannedTournament();
        $props = $this->props($manage);
        $stage = $props['tournament']['stages'][0];
        $semi = $stage['rounds'][0]['fixtures'][0];
        $final = $stage['rounds'][1]['fixtures'][0];

        $this->post("$manage/stages/{$stage['id']}/fixtures/{$semi['id']}/result", [
            'outcome' => ['type' => 'sets', 'sets' => [[11, 5], [11, 5], [11, 5]]],
        ])->assertSessionDoesntHaveErrors();

        $schedule = $this->app->make(ScheduleRepository::class)
            ->ofTournament(TournamentRef::fromString($props['tournament']['id']));
        self::assertNotNull($schedule);

        $assignment = $schedule->assignment(MatchId::fromString($final['id']));
        self::assertNotNull($assignment->home, 'победитель полуфинала попал в расписание финала');
        self::assertSame($semi['homeId'], $assignment->home->value);
    }

    private function plannedTournament(): string
    {
        $manage = $this->runningTournament(participants: 4);
        $this->post("$manage/venues", ['name' => 'Стол 1']);
        $this->post("$manage/venues", ['name' => 'Стол 2']);
        $this->patch("$manage/schedule", ['settings' => [
            'starts_at' => '2026-10-03T10:00:00+00:00',
            'match_minutes' => 30,
            'break_minutes' => 0,
            'rest_minutes' => 15,
        ]]);
        $this->post("$manage/schedule");

        return $manage;
    }

    private function runningTournament(int $participants = 4): string
    {
        $manage = $this->createTournament();
        $names = array_map(static fn (int $i) => ['name' => "Игрок $i"], range(1, $participants));
        $this->post("$manage/participants", ['type' => 'player', 'participants' => $names]);
        $this->post("$manage/stage", ['format' => 'single_elimination']);

        return $manage;
    }

    private function createTournament(): string
    {
        $location = $this->post('/tournaments', ['name' => 'Тест', 'sport' => 'table_tennis'])->headers->get('Location');
        self::assertIsString($location);

        return parse_url($location, PHP_URL_PATH) ?: $location;
    }

    /** @return array<string, mixed> */
    private function props(string $url): array
    {
        $page = $this->get($url)->viewData('page');
        $decoded = json_decode(json_encode($page, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded['props'];
    }

    /** @return array<string, mixed> */
    private function assignmentOf(string $manage, string $matchId): array
    {
        foreach ($this->props($manage)['schedule']['assignments'] as $assignment) {
            if ($assignment['matchId'] === $matchId) {
                return $assignment;
            }
        }

        self::fail("Матч $matchId не найден в расписании");
    }
}
