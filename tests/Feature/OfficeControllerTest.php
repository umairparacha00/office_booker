<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListsAllOfficesInPaginateWay()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
    }

    /**
     * @test
     */
    public function itOnlyListsOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();
        Office::factory(3)->create(['hidden' => true]);
        Office::factory(3)->create([
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function itFiltersByUserId()
    {
        Office::factory()->create();

        $host = User::factory()->create();

        $office = Office::factory()
            ->for($host)
            ->create();

        $response = $this->get('/api/offices?user_id=' . $host->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();

        $office = Office::factory()->create();

        Reservation::factory()
            ->for($office)
            ->for($user)
            ->create();

        Reservation::factory()
            ->for(Office::factory())
            ->create();

        $response = $this->get('/api/offices?visitor_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office = Office::factory()
            ->for($user)
            ->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get('/api/offices');
        $response->assertOk();

        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertEquals(
            $user->id,
            $response->json('data')[0]['user']['id']
        );
    }

    /**
     * @test
     */
    public function itReturnsTheNumberOfActiveReservations()
    {
        $office = Office::factory()->create();

        Reservation::factory()
            ->for($office)
            ->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()
            ->for($office)
            ->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices');
        $this->assertEquals(
            1,
            $response->json('data')[0]['reservations_count']
        );
        $response->assertOk();
    }

    /**
     * @test
     */
    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        $office1 = Office::factory()->create([
            'lat' => '39.74051727562952',
            'lng' => '-8.770375324893696',
            'title' => 'Leiria'
        ]);

        $office2 = Office::factory()->create([
            'lat' => '39.07753883078113',
            'lng' => '-9.281266331143293',
            'title' => 'Torres Vedras'
        ]);

        $response = $this->get(
            '/api/offices?lat=38.720661384644046&lng=-9.16044783453807'
        );
        $response->assertOk();
        $this->assertEquals(
            'Torres Vedras',
            $response->json('data')[0]['title']
        );
        $this->assertEquals('Leiria', $response->json('data')[1]['title']);

        $response = $this->get('/api/offices');
        $response->assertOk();
        $this->assertEquals('Leiria', $response->json('data')[0]['title']);
        $this->assertEquals(
            'Torres Vedras',
            $response->json('data')[1]['title']
        );
    }

    /**
     * @test
     */
    public function itShowsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office = Office::factory()
            ->for($user)
            ->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        Reservation::factory()
            ->for($office)
            ->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()
            ->for($office)
            ->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices/' . $office->id);

        $response->assertOk();

        $this->assertEquals(1, $response->json('data')['reservations_count']);

        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }

    /**
     * @test
     */
    public function itCreatesAnOffice()
    {
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();
        $this->actingAs($user = User::factory()->createQuietly());

        $response = $this->postJson('/api/offices', [
            'title' => 'Office in Faisalabad',
            'description' => 'description',
            'lat' => '39.74051727562952',
            'lng' => '-8.770375324893696',
            'address_line1' => 'address',
            'price_per_day' => 35_000,
            'monthly_discount' => 5,
            'tags' => [$tag1->id, $tag2->id]
        ]);
        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Office in Faisalabad')
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING);

        $this->assertDatabaseHas('offices', [
            'title' => 'Office in Faisalabad'
        ]);
    }
}
