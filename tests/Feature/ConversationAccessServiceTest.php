<?php

use App\Events\MessageStatusUpdated;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MetaWhatsappSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappNumber;
use App\Services\ConversationAccessService;

beforeEach(function () {
    MetaWhatsappSetting::create([
        'name' => 'Test Meta App',
        'app_id' => 'app-' . uniqid(),
        'app_secret' => 'secret',
        'verify_token' => 'verify',
        'is_active' => true,
    ]);
});

it('allows both teams on a shared number to access the same conversation', function () {
    $setting = MetaWhatsappSetting::query()->first();

    $number = WhatsappNumber::create([
        'phone_number_id' => 'shared-number-id',
        'waba_id' => 'waba-1',
        'business_account_id' => 'ba-1',
        'phone_number' => '+15550000000',
        'display_phone_number' => '+1 555 000 0000',
        'verified_name' => 'Shared Number',
        'access_token' => 'test-token',
        'meta_whatsapp_setting_id' => $setting->id,
        'is_active' => true,
    ]);

    $teamA = Team::create([
        'name' => 'Team A',
        'slug' => 'team-a',
        'whatsapp_number_id' => $number->id,
        'is_active' => true,
    ]);

    $teamB = Team::create([
        'name' => 'Team B',
        'slug' => 'team-b',
        'whatsapp_number_id' => $number->id,
        'is_active' => true,
    ]);

    $teamA->update(['whatsapp_number_id' => $number->id]);
    $teamB->update(['whatsapp_number_id' => $number->id]);

    $userA = User::factory()->create(['team_id' => $teamA->id]);
    $userB = User::factory()->create(['team_id' => $teamB->id]);

    $customer = Customer::create([
        'name' => 'Alice Customer',
        'phone' => '+15551112222',
        'email' => 'alice@example.com',
        'team_id' => $teamA->id,
        'assigned_to' => $userA->id,
        'old_owner_id' => $userB->id,
    ]);

    $message = Message::create([
        'customer_id' => $customer->id,
        'team_id' => $teamA->id,
        'whatsapp_number_id' => $number->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'hello',
        'status' => 'delivered',
    ]);

    $service = app(ConversationAccessService::class);

    expect($service->teamIdsForWhatsappNumber($number))
        ->toContain($teamA->id)
        ->toContain($teamB->id);

    expect($service->canAccessCustomer($userA, $customer))->toBeTrue();
    expect($service->canAccessCustomer($userB, $customer))->toBeTrue();
    expect($service->canAccessMessage($userA, $message))->toBeTrue();
    expect($service->canAccessMessage($userB, $message))->toBeTrue();
});

it('broadcasts status updates to every team sharing the same whatsapp number', function () {
    $setting = MetaWhatsappSetting::query()->first();

    $number = WhatsappNumber::create([
        'phone_number_id' => 'shared-status-number',
        'waba_id' => 'waba-status',
        'business_account_id' => 'ba-status',
        'phone_number' => '+15550000010',
        'display_phone_number' => '+1 555 000 0010',
        'verified_name' => 'Status Number',
        'access_token' => 'status-token',
        'meta_whatsapp_setting_id' => $setting->id,
        'is_active' => true,
    ]);

    $teamA = Team::create([
        'name' => 'Status Team A',
        'slug' => 'status-team-a',
        'whatsapp_number_id' => $number->id,
        'is_active' => true,
    ]);

    $teamB = Team::create([
        'name' => 'Status Team B',
        'slug' => 'status-team-b',
        'whatsapp_number_id' => $number->id,
        'is_active' => true,
    ]);

    $teamC = Team::create([
        'name' => 'Other Team',
        'slug' => 'other-team',
        'whatsapp_number_id' => null,
        'is_active' => true,
    ]);

    $userA = User::factory()->create(['team_id' => $teamA->id]);
    $userB = User::factory()->create(['team_id' => $teamB->id]);

    $customer = Customer::create([
        'name' => 'Status Customer',
        'phone' => '+15551112224',
        'email' => 'status@example.com',
        'team_id' => $teamA->id,
        'assigned_to' => $userA->id,
        'old_owner_id' => $userB->id,
    ]);

    $message = Message::create([
        'customer_id' => $customer->id,
        'team_id' => $teamA->id,
        'whatsapp_number_id' => $number->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'status update',
        'status' => 'delivered',
    ]);

    $channels = array_map(
        fn ($channel) => $channel->name,
        (new MessageStatusUpdated($message))->broadcastOn()
    );

    expect($channels)->toContain('private-whatsapp.team.' . $teamA->id);
    expect($channels)->toContain('private-whatsapp.team.' . $teamB->id);
    expect($channels)->not->toContain('private-whatsapp.team.' . $teamC->id);
});

it('adds sender context to outbound status broadcasts', function () {
    $setting = MetaWhatsappSetting::query()->first();

    $number = WhatsappNumber::create([
        'phone_number_id' => 'sender-context-number',
        'waba_id' => 'waba-sender',
        'business_account_id' => 'ba-sender',
        'phone_number' => '+15550000020',
        'display_phone_number' => '+1 555 000 0020',
        'verified_name' => 'Sender Context Number',
        'access_token' => 'sender-token',
        'meta_whatsapp_setting_id' => $setting->id,
        'is_active' => true,
    ]);

    $team = Team::create([
        'name' => 'Sender Team',
        'slug' => 'sender-team',
        'whatsapp_number_id' => $number->id,
        'is_active' => true,
    ]);

    $assignedUser = User::factory()->create(['team_id' => $team->id]);

    $customer = Customer::create([
        'name' => 'Sender Customer',
        'phone' => '+15551112225',
        'email' => 'sender@example.com',
        'team_id' => $team->id,
        'assigned_to' => $assignedUser->id,
    ]);

    $message = Message::create([
        'customer_id' => $customer->id,
        'team_id' => $team->id,
        'whatsapp_number_id' => $number->id,
        'sent_by' => $assignedUser->id,
        'direction' => 'outbound',
        'type' => 'text',
        'body' => 'hello sender context',
        'status' => 'sent',
    ]);

    $payload = (new MessageStatusUpdated($message))->broadcastWith();

    expect($payload['message']['sender_context']['type'])->toBe('assigned');
    expect($payload['message']['sender_context']['role'])->toBe('Assigned Executive');
});

it('keeps separate whatsapp numbers as separate conversations', function () {
    $setting = MetaWhatsappSetting::query()->first();

    $numberA = WhatsappNumber::create([
        'phone_number_id' => 'shared-a',
        'waba_id' => 'waba-a',
        'business_account_id' => 'ba-a',
        'phone_number' => '+15550000001',
        'display_phone_number' => '+1 555 000 0001',
        'verified_name' => 'A Number',
        'access_token' => 'test-token-a',
        'meta_whatsapp_setting_id' => $setting->id,
        'is_active' => true,
    ]);

    $numberB = WhatsappNumber::create([
        'phone_number_id' => 'shared-b',
        'waba_id' => 'waba-b',
        'business_account_id' => 'ba-b',
        'phone_number' => '+15550000002',
        'display_phone_number' => '+1 555 000 0002',
        'verified_name' => 'B Number',
        'access_token' => 'test-token-b',
        'meta_whatsapp_setting_id' => $setting->id,
        'is_active' => true,
    ]);

    $teamA = Team::create([
        'name' => 'Team A',
        'slug' => 'team-a-2',
        'whatsapp_number_id' => $numberA->id,
        'is_active' => true,
    ]);

    $teamB = Team::create([
        'name' => 'Team B',
        'slug' => 'team-b-2',
        'whatsapp_number_id' => $numberB->id,
        'is_active' => true,
    ]);

    $userA = User::factory()->create(['team_id' => $teamA->id]);
    $userB = User::factory()->create(['team_id' => $teamB->id]);

    $customer = Customer::create([
        'name' => 'Bob Customer',
        'phone' => '+15551112223',
        'email' => 'bob@example.com',
        'team_id' => $teamA->id,
        'assigned_to' => $userA->id,
        'old_owner_id' => $userB->id,
    ]);

    $messageA = Message::create([
        'customer_id' => $customer->id,
        'team_id' => $teamA->id,
        'whatsapp_number_id' => $numberA->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'from a',
        'status' => 'delivered',
    ]);

    $messageB = Message::create([
        'customer_id' => $customer->id,
        'team_id' => $teamB->id,
        'whatsapp_number_id' => $numberB->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'from b',
        'status' => 'delivered',
    ]);

    $service = app(ConversationAccessService::class);

    expect($service->canAccessMessage($userA, $messageA))->toBeTrue();
    expect($service->canAccessMessage($userB, $messageB))->toBeTrue();
    expect($service->canAccessMessage($userA, $messageB))->toBeFalse();
    expect($service->canAccessMessage($userB, $messageA))->toBeFalse();
});
