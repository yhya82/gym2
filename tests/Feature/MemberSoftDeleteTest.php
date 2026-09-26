<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_members_are_hidden_from_default_and_active_queries(): void
    {
        $active = Member::factory()->create();
        $archived = Member::factory()->create();
        $archived->delete();

        $this->assertTrue(Member::query()->whereKey($active->id)->exists());
        $this->assertFalse(Member::query()->whereKey($archived->id)->exists());
        $this->assertTrue(Member::withTrashed()->whereKey($archived->id)->exists());
        $this->assertTrue(Member::onlyTrashed()->whereKey($archived->id)->exists());
    }

    /**
     * A second, active member cannot reuse a phone number that's still in
     * use — enforced by the unique index on phone_number.
     */
    public function test_a_second_active_member_cannot_reuse_a_phone_number_in_use(): void
    {
        Member::factory()->create(['phone_number' => '+2207001234']);

        $this->expectException(QueryException::class);

        Member::factory()->create(['phone_number' => '+2207001234']);
    }

    /**
     * A phone number is unique forever, not just while its member is
     * active — archiving the original member does not free it up.
     */
    public function test_an_archived_members_phone_number_cannot_be_reused(): void
    {
        $original = Member::factory()->create(['phone_number' => '+2207001234']);
        $original->delete();

        $this->expectException(QueryException::class);

        Member::factory()->create(['phone_number' => '+2207001234']);
    }
}
