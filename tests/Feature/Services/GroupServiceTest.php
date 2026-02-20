<?php

namespace Tests\Feature\Services;

use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_message_group_returns_null_for_deactivated_group(): void
    {
        $service = app(GroupService::class);

        Group::create([
            'line_group_id' => 'C33333333333333333333333333333333',
            'name' => 'Dorm',
            'is_active' => false,
        ]);

        $group = $service->resolveMessageGroup('C33333333333333333333333333333333', 'Dorm v2');

        $this->assertNull($group);
        $this->assertDatabaseHas('groups', [
            'line_group_id' => 'C33333333333333333333333333333333',
            'name' => 'Dorm',
            'is_active' => false,
        ]);
    }

    public function test_resolve_message_group_creates_group_when_not_found(): void
    {
        $service = app(GroupService::class);

        $group = $service->resolveMessageGroup('C44444444444444444444444444444444', 'Road Trip');

        $this->assertNotNull($group);
        $this->assertTrue($group->is_active);
        $this->assertSame('Road Trip', $group->name);
    }

    public function test_it_tracks_group_members_and_assigns_default_roles(): void
    {
        $service = app(GroupService::class);

        $group = Group::create([
            'line_group_id' => 'C11111111111111111111111111111111',
            'name' => 'Trip Team',
            'is_active' => true,
        ]);

        $firstUser = $service->resolveGroupMember($group, 'U11111111111111111111111111111111', [
            'displayName' => 'Alice',
            'pictureUrl' => 'https://example.com/alice.jpg',
        ]);

        $secondUser = $service->resolveGroupMember($group, 'U22222222222222222222222222222222', [
            'displayName' => 'Bob',
        ]);

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $firstUser->id,
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $secondUser->id,
            'role' => 'member',
        ]);
    }
}
