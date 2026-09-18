<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Сквозные HTTP-сценарии организатора и зрителя через Inertia. */
final class OrganizerFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_renders_create_form(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('sports', 3)
            ->where('sports.0.value', 'volleyball'));
    }

    #[Test]
    public function creating_redirects_to_organizer_page(): void
    {
        $response = $this->post('/tournaments', ['name' => 'Пятничный', 'sport' => 'table_tennis']);

        $response->assertRedirect();
        $manageUrl = $response->headers->get('Location');
        self::assertIsString($manageUrl);
        self::assertMatchesRegularExpression('#/manage/[0-9a-f]{40}$#', $manageUrl);

        $this->get($manageUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Show')
            ->where('tournament.name', 'Пятничный')
            ->where('tournament.status', 'draft')
            ->where('tournament.rules.points_per_set', 11)
            ->has('urls.public')
            ->missing('tournament.adminToken'));
    }

    #[Test]
    public function creation_is_validated(): void
    {
        $this->from('/')->post('/tournaments', ['name' => '', 'sport' => 'chess'])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['name', 'sport']);
    }

    #[Test]
    public function wrong_token_is_not_found(): void
    {
        $this->get('/manage/'.str_repeat('0', 40))->assertNotFound();
        $this->get('/manage/short')->assertNotFound();
        $this->get('/t/unknownslug')->assertNotFound();
    }

    #[Test]
    public function full_flow_from_participants_to_public_bracket(): void
    {
        $manage = $this->createTournament();

        $this->post("$manage/participants", ['type' => 'player', 'participants' => [
            ['name' => 'Аня'], ['name' => 'Борис'], ['name' => 'Вера'], ['name' => 'Глеб'],
        ]])->assertRedirect($manage);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page->has('tournament.participants', 4));
        $participants = $this->props($manage)['tournament']['participants'];
        $this->post("$manage/participants/reorder", ['order' => array_reverse(array_column($participants, 'id'))])->assertRedirect($manage);
        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.participants.0.name', 'Глеб'));

        $this->post("$manage/stage", ['format' => 'single_elimination', 'settings' => ['third_place_match' => true]])
            ->assertRedirect($manage)->assertSessionHas('success');

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->where('tournament.status', 'in_progress')
            ->has('tournament.stages', 1)
            ->has('tournament.stages.0.rounds', 2)
            ->where('tournament.stages.0.standings', null));
        $props = $this->props($manage);

        $stage = $props['tournament']['stages'][0];
        $semi = $stage['rounds'][0]['fixtures'][0];

        $this->post("$manage/stages/{$stage['id']}/fixtures/{$semi['id']}/result", ['outcome' => ['type' => 'sets', 'sets' => [[11, 5], [11, 5], [11, 5]]]])
            ->assertRedirect($manage)->assertSessionDoesntHaveErrors();

        $slug = $props['tournament']['publicSlug'];
        $this->get("/t/$slug")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Show')
            ->where('tournament.stages.0.rounds.0.fixtures.0.status', 'finished')
            ->where('tournament.stages.0.rounds.0.fixtures.0.winnerId', $semi['homeId'])
            ->where('tournament.stages.0.rounds.1.fixtures.0.homeId', $semi['homeId']));

        $this->delete("$manage/stages/{$stage['id']}/fixtures/{$semi['id']}/result")->assertRedirect($manage);
        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.stages.0.rounds.0.fixtures.0.status', 'ready'));
    }

    #[Test]
    public function domain_violation_comes_back_as_form_error(): void
    {
        $manage = $this->createTournament('volleyball');
        $this->post("$manage/participants", ['type' => 'team', 'participants' => [['name' => 'A'], ['name' => 'B']]]);
        $this->post("$manage/stage", ['format' => 'single_elimination']);
        $props = $this->props($manage);
        $stage = $props['tournament']['stages'][0];
        $final = $stage['rounds'][0]['fixtures'][0];

        $this->from($manage)
            ->post("$manage/stages/{$stage['id']}/fixtures/{$final['id']}/result", ['outcome' => ['type' => 'sets', 'sets' => [[25, 24], [25, 20], [25, 20]]]])
            ->assertRedirect($manage)
            ->assertSessionHasErrors('domain');

        $this->from($manage)->post("$manage/participants", ['type' => 'team', 'participants' => [['name' => 'C']]])
            ->assertSessionHasErrors('domain');
    }

    #[Test]
    public function round_robin_shows_standings(): void
    {
        $manage = $this->createTournament();
        $this->post("$manage/participants", ['type' => 'player', 'participants' => [['name' => 'A'], ['name' => 'B'], ['name' => 'C']]]);
        $this->post("$manage/stage", ['format' => 'round_robin']);

        $this->get($manage)->assertInertia(fn (Assert $page) => $page
            ->has('tournament.stages.0.rounds', 3)
            ->has('tournament.stages.0.standings', 3)
            ->where('tournament.stages.0.standings.0.rank', 1));

        $this->delete("$manage/stage")->assertRedirect($manage);
        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.status', 'draft')->has('tournament.stages', 0));
    }

    /** @return array<string, mixed> пропсы страницы как их увидит фронт */
    private function props(string $url): array
    {
        $page = $this->get($url)->viewData('page');
        $decoded = json_decode(json_encode($page, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded['props'];
    }

    private function createTournament(string $sport = 'table_tennis'): string
    {
        $location = $this->post('/tournaments', ['name' => 'Тест', 'sport' => $sport])->headers->get('Location');
        self::assertIsString($location);

        return parse_url($location, PHP_URL_PATH) ?: $location;
    }
}
