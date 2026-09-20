<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AccountsAndStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anyone_can_register_and_lands_on_their_tournaments(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));

        $this->post('/register', [
            'name' => 'Алексей',
            'email' => 'coach@example.com',
            'password' => 'verysecret123',
            'password_confirmation' => 'verysecret123',
        ])->assertRedirect('/my');

        $this->assertAuthenticated();
        $this->get('/my')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('My/Index')
            ->has('tournaments', 0)
            ->where('auth.user.name', 'Алексей'));
    }

    #[Test]
    public function login_rejects_wrong_password_and_accepts_the_right_one(): void
    {
        $user = User::create(['name' => 'Тренер', 'email' => 'coach@example.com', 'password' => 'verysecret123']);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'verysecret123'])->assertRedirect('/my');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function tournaments_stay_anonymous_without_an_account(): void
    {
        $manage = $this->createTournament();

        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.ownerId', null));
        $this->get('/my')->assertRedirect('/login');
    }

    #[Test]
    public function tournament_created_while_logged_in_belongs_to_the_account(): void
    {
        $user = $this->signIn();
        $manage = $this->createTournament();

        $this->get($manage)->assertInertia(fn (Assert $page) => $page->where('tournament.ownerId', $user->id));
        $this->get('/my')->assertInertia(fn (Assert $page) => $page
            ->has('tournaments', 1)
            ->where('tournaments.0.name', 'Тест')
            ->where('tournaments.0.participants', 0));
    }

    #[Test]
    public function anonymous_tournament_can_be_claimed_later(): void
    {
        $manage = $this->createTournament();
        $user = $this->signIn();

        $this->post("$manage/claim")->assertRedirect($manage)->assertSessionHas('success');

        $this->get('/my')->assertInertia(fn (Assert $page) => $page->has('tournaments', 1));

        // Чужой аккаунт забрать турнир уже не сможет.
        $this->post('/logout');
        $other = User::create(['name' => 'Другой', 'email' => 'other@example.com', 'password' => 'verysecret123']);
        $this->actingAs($other)->from($manage)->post("$manage/claim")->assertSessionHasErrors('domain');
        self::assertNotNull($user);
    }

    #[Test]
    public function statistics_add_up_across_tournaments_of_the_account(): void
    {
        $this->signIn();

        foreach ([1, 2] as $number) {
            $manage = $this->createTournament("Кубок $number");
            $this->post("$manage/participants", ['type' => 'player', 'participants' => [
                ['name' => 'Аня Смирнова'], ['name' => 'Борис Ким'],
            ]]);
            $this->post("$manage/stage", ['format' => 'single_elimination']);

            $props = $this->props($manage);
            $stage = $props['tournament']['stages'][0];
            $final = $stage['rounds'][0]['fixtures'][0];
            $this->post("$manage/stages/{$stage['id']}/fixtures/{$final['id']}/result", [
                'outcome' => ['type' => 'sets', 'sets' => [[11, 5], [11, 7], [11, 9]]],
            ])->assertSessionDoesntHaveErrors();
        }

        $response = $this->get('/players')->assertOk();
        $competitors = $this->propsOf($response)['competitors'];

        self::assertCount(2, $competitors, 'два игрока на два турнира, а не четыре');
        self::assertSame('Аня Смирнова', $competitors[0]['name']);
        self::assertSame(2, $competitors[0]['tournaments']);
        self::assertSame(2, $competitors[0]['matches']);
        self::assertSame(2, $competitors[0]['wins']);
        self::assertSame(0, $competitors[0]['losses']);
        self::assertEquals(100, $competitors[0]['winRate']);
        self::assertSame(6, $competitors[0]['setsWon']);
        self::assertSame(0, $competitors[1]['wins']);

        $profile = $this->propsOf($this->get("/players/{$competitors[0]['id']}")->assertOk());
        self::assertCount(2, $profile['matches']);
        self::assertSame('win', $profile['matches'][0]['result']);
        self::assertSame('Борис Ким', $profile['matches'][0]['opponent']);
        self::assertSame('3:0', $profile['matches'][0]['score']);
        self::assertSame([['name' => 'Борис Ким', 'wins' => 2, 'losses' => 0]], $profile['rivals']);
    }

    #[Test]
    public function names_that_differ_only_by_case_are_the_same_competitor(): void
    {
        $this->signIn();
        $manage = $this->createTournament();

        $this->post("$manage/participants", ['type' => 'player', 'participants' => [
            ['name' => 'Пётр Волков'], ['name' => '  петр   волков  '], ['name' => 'Иван Седов'],
        ]]);

        $competitors = $this->propsOf($this->get('/players'))['competitors'];
        self::assertCount(2, $competitors, 'разное написание — один и тот же человек');
    }

    #[Test]
    public function statistics_ignore_tournaments_without_an_owner(): void
    {
        $manage = $this->createTournament();
        $this->post("$manage/participants", ['type' => 'player', 'participants' => [['name' => 'Ничей'], ['name' => 'Тоже ничей']]]);

        $this->signIn();

        self::assertSame([], $this->propsOf($this->get('/players'))['competitors']);
    }

    #[Test]
    public function statistics_are_private_to_the_account(): void
    {
        $this->signIn();
        $manage = $this->createTournament();
        $this->post("$manage/participants", ['type' => 'player', 'participants' => [['name' => 'Аня'], ['name' => 'Борис']]]);
        $mine = $this->propsOf($this->get('/players'))['competitors'];
        self::assertCount(2, $mine);

        $this->post('/logout');
        $other = User::create(['name' => 'Другой', 'email' => 'other@example.com', 'password' => 'verysecret123']);
        $this->actingAs($other);

        self::assertSame([], $this->propsOf($this->get('/players'))['competitors']);
        $this->get("/players/{$mine[0]['id']}")->assertNotFound();
    }

    #[Test]
    public function statistics_can_be_filtered_by_kind(): void
    {
        $this->signIn();
        $manage = $this->createTournament();
        $this->post("$manage/participants", ['type' => 'pair', 'participants' => [['name' => 'Иванов / Петров'], ['name' => 'Сидоровы']]]);

        self::assertCount(2, $this->propsOf($this->get('/players?type=pair'))['competitors']);
        self::assertSame([], $this->propsOf($this->get('/players?type=team'))['competitors']);
    }

    private function signIn(): User
    {
        $user = User::create(['name' => 'Тренер', 'email' => 'coach@example.com', 'password' => 'verysecret123']);
        $this->actingAs($user);

        return $user;
    }

    private function createTournament(string $name = 'Тест'): string
    {
        $location = $this->post('/tournaments', ['name' => $name, 'sport' => 'table_tennis'])->headers->get('Location');
        self::assertIsString($location);

        return parse_url($location, PHP_URL_PATH) ?: $location;
    }

    /** @return array<string, mixed> */
    private function props(string $url): array
    {
        return $this->propsOf($this->get($url));
    }

    /** @return array<string, mixed> */
    private function propsOf(mixed $response): array
    {
        $decoded = json_decode(json_encode($response->viewData('page'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded['props'];
    }
}
