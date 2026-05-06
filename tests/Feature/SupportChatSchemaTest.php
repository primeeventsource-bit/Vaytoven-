<?php

namespace Tests\Feature;

use App\Enums\Surface;
use App\Models\SupportChatSession;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportChatSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_holds_messages_in_chronological_order(): void
    {
        $session = SupportChatSession::factory()->create();
        SupportMessage::factory()->create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => 'first',
            'occurred_at' => now()->subMinutes(2),
        ]);
        SupportMessage::factory()->create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'second',
            'occurred_at' => now()->subMinute(),
        ]);

        $contents = $session->messages->pluck('content')->toArray();
        $this->assertSame(['first', 'second'], $contents);
    }

    public function test_session_surface_casts_to_enum(): void
    {
        $session = SupportChatSession::factory()->create(['surface' => Surface::Admin->value]);
        $this->assertSame(Surface::Admin, $session->surface);
    }

    public function test_ticket_links_to_session_and_assignee(): void
    {
        $session = SupportChatSession::factory()->create();
        $assignee = User::factory()->create();
        $ticket = SupportTicket::factory()->create([
            'session_id' => $session->id,
            'assigned_to_user_id' => $assignee->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $this->assertTrue($ticket->session->is($session));
        $this->assertTrue($ticket->assignedTo->is($assignee));
    }

    public function test_ticket_messages_support_internal_notes(): void
    {
        $ticket = SupportTicket::factory()->create();
        $admin = User::factory()->create();

        SupportTicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'sender_user_id' => $admin->id,
            'is_internal_note' => true,
            'body' => 'Suspect this is a refund-rule confusion',
            'occurred_at' => now(),
        ]);
        SupportTicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'sender_user_id' => $admin->id,
            'is_internal_note' => false,
            'body' => 'Hi! Happy to help — checking your booking now.',
            'occurred_at' => now()->addSecond(),
        ]);

        $this->assertCount(2, $ticket->messages);
        $this->assertTrue($ticket->messages->first()->is_internal_note);
        $this->assertFalse($ticket->messages->last()->is_internal_note);
    }

    public function test_tool_calls_and_results_persist_as_json(): void
    {
        $session = SupportChatSession::factory()->create();
        SupportMessage::factory()->create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Looking that up.',
            'tool_calls' => [['name' => 'get_booking_status', 'args' => ['confirmation_code' => 'VYT-K3M9P2']]],
            'occurred_at' => now(),
        ]);
        $msg = $session->fresh()->messages->first();

        $this->assertIsArray($msg->tool_calls);
        $this->assertSame('get_booking_status', $msg->tool_calls[0]['name']);
    }
}
