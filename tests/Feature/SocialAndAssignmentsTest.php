<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MemberEnquiry;
use App\Models\MemberSpecialistAssignment;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Property;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAndAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_belongs_to_booking_property_author(): void
    {
        $booking = Booking::factory()->create();
        $review = Review::factory()->create([
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'author_user_id' => $booking->traveler_id,
        ]);

        $this->assertTrue($review->booking->is($booking));
        $this->assertSame($booking->property_id, $review->property_id);
    }

    public function test_review_response_is_one_to_one(): void
    {
        $review = Review::factory()->create();
        $host = User::factory()->create();
        $resp = ReviewResponse::create([
            'review_id' => $review->id,
            'responder_user_id' => $host->id,
            'body' => 'Thanks for staying!',
        ]);

        $this->assertTrue($review->fresh()->response->is($resp));
    }

    public function test_one_review_per_booking_per_author(): void
    {
        $booking = Booking::factory()->create();
        Review::factory()->create([
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'author_user_id' => $booking->traveler_id,
        ]);

        $this->expectException(QueryException::class);

        Review::factory()->create([
            'booking_id' => $booking->id,
            'property_id' => $booking->property_id,
            'author_user_id' => $booking->traveler_id,
        ]);
    }

    public function test_message_thread_aggregates_messages_in_chronological_order(): void
    {
        $thread = MessageThread::factory()->create();
        Message::factory()->create([
            'thread_id' => $thread->id,
            'body' => 'first',
            'occurred_at' => now()->subMinutes(2),
        ]);
        Message::factory()->create([
            'thread_id' => $thread->id,
            'body' => 'second',
            'occurred_at' => now()->subMinute(),
        ]);

        $bodies = $thread->messages->pluck('body')->toArray();
        $this->assertSame(['first', 'second'], $bodies);
    }

    public function test_wishlist_can_hold_many_properties_via_pivot(): void
    {
        $user = User::factory()->create();
        $list = Wishlist::factory()->create(['user_id' => $user->id]);
        $a = Property::factory()->create();
        $b = Property::factory()->create();

        $list->properties()->attach([
            $a->id => ['added_at' => now()],
            $b->id => ['added_at' => now()],
        ]);

        $this->assertCount(2, $list->fresh()->properties);
    }

    public function test_member_specialist_assignment_links_specialist_to_enquiry(): void
    {
        $specialist = User::factory()->create();
        $enquiry = MemberEnquiry::factory()->create();

        $assignment = MemberSpecialistAssignment::create([
            'specialist_user_id' => $specialist->id,
            'enquiry_id' => $enquiry->id,
            'assignment_method' => 'round_robin',
            'assigned_at' => now(),
        ]);

        $this->assertTrue($assignment->specialist->is($specialist));
        $this->assertTrue($assignment->enquiry->is($enquiry));
    }
}
