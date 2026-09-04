# Message Flow Diagram & Race Condition Analysis

## ✅ CORRECT FLOW (Without Race Condition)

```
User sends message via UI
    ↓
Message created in DB (status: pending, no whatsapp_message_id yet)
    ↓
SendWhatsappMessageJob queued
    ↓
[DELAY] Queue processes job
    ↓
Send to Meta API → Get whatsapp_message_id = "wamid.xxx"
    ↓
Update Message (whatsapp_message_id: "wamid.xxx", status: "sent")
    ↓
Message SAVED in database ✓
    ↓
Meta sends "delivered" webhook
    ↓
Webhook handler searches: Message.where(whatsapp_message_id = "wamid.xxx")
    ↓
FOUND! ✓ Update status to "delivered"
    ↓
✅ SUCCESS - Logs show: "Message status updated {status: delivered}"
```

---

## ❌ RACE CONDITION FLOW (With timing issue)

```
User sends message via UI
    ↓
Message created in DB (status: pending, no whatsapp_message_id yet)
    ↓
SendWhatsappMessageJob queued
    ↓
[DELAY] Queue processes job
    ↓
Send to Meta API → Get whatsapp_message_id = "wamid.xxx"
    ↓
PARALLEL: Update DB starts...          Meta starts sending webhook...
    ↓                                    ↓
Updating Message table...               Webhook arrives!
    ↓                                    ↓
Still in progress... 🔄                 Webhook searches: 
    ↓                                    Message.where(whatsapp_message_id = "wamid.xxx")
    ↓                                    ↓
Transaction not committed yet! ❌        NOT FOUND! ❌
    ↓                                    ↓
    UPDATE completes                     Webhook silently returns
    ↓                                    ↓
✅ Message finally saved in DB          ❌ Status update was lost!
    ↓
Logs show: "Message not found for status update"
```

---

## Why Does This Happen?

1. **Database transactions have latency**: Updates don't happen instantly
2. **Meta is fast**: Status webhooks arrive almost immediately (50-200ms)
3. **Network timing is unpredictable**: Sometimes queue processing is slow
4. **No retry mechanism**: Current webhook doesn't retry if message not found

---

## Symptoms of Race Condition

### ❌ In Logs:
```
[INFO] Processing status updates from webhook {count: 1}
[WARNING] Message not found for status update {
  whatsapp_message_id: "wamid.xxx",
  status: "delivered"
}
```

### ❌ In Database:
Message exists but:
- `whatsapp_message_id` is NULL or wrong
- `status` is still "pending" instead of "delivered"
- `delivered_at` is NULL

### ❌ In UI:
- Message shows as "pending" or "sent" even after several seconds
- No update to delivered status
- No timeline/activity log entry

---

## Why Your Current Setup Has This Issue

Looking at your `SendWhatsappMessageJob`:

```php
$message->update([
    'whatsapp_message_id' => $whatsappMessageId,
    'status' => 'sent',
    'failure_reason' => null,
]);

// Message event dispatched immediately
event(new \App\Events\MessageStatusUpdated($message));
```

The job updates the message quickly, but:
1. If the database update takes 50-100ms
2. And Meta's webhook arrives within 50ms
3. The webhook search fails ❌

---

## How to Identify If This Is Your Problem

### Check 1: Watch Real-Time Logs
```bash
tail -f storage/logs/laravel.log | grep -E "Message|status"
```

Send a message and watch for:
- ✅ Good: "Message status updated {new_status: delivered}"
- ❌ Bad: "Message not found for status update"

### Check 2: Check Database Timing
```bash
# Before sending message:
SELECT COUNT(*) as pending FROM messages WHERE status = 'pending';

# Send message via UI

# Immediately after (within 1 second):
SELECT id, status, whatsapp_message_id, created_at FROM messages ORDER BY created_at DESC LIMIT 5;

# Check if whatsapp_message_id is populated
# If NULL or all "pending" → Queue hasn't processed yet
# If populated but status is "sent" → Good
# If status should be "delivered" but is "sent" → Race condition hit
```

### Check 3: Compare Timestamps
In logs, look for:
```
[10:30:45] Sent to Meta: whatsapp_message_id = "wamid.xxx"
[10:30:45.050] Webhook arrived with status update
[10:30:45.150] Message status updated successfully
```

If webhook time is BEFORE update time → Race condition

---

## Solutions

### Solution 1: Add Database Index (Quick Fix) ⚡
```php
// In a migration:
Schema::table('messages', function (Blueprint $table) {
    $table->index('whatsapp_message_id');
    $table->index(['whatsapp_number_id', 'whatsapp_message_id']);
});

// Run: php artisan migrate
```

This speeds up the search, reducing the race condition window.

### Solution 2: Delayed Webhook Processing (Better) 🔄
Store webhook data and retry if message not found:

```php
// In processStatus():
if (!$message) {
    // Store in cache to retry later
    Cache::put(
        "webhook_status_{$whatsappMessageId}",
        $status,
        now()->addMinutes(5)
    );
    
    // Schedule a retry
    dispatch(new RetryWebhookStatusJob($whatsappMessageId))
        ->delay(now()->addSeconds(5));
    
    return;
}
```

### Solution 3: Use Message ID from Request Body (Best) ✅
When sending message, use Meta's response immediately:

```php
// Current (problematic):
$dbMessage = Message::create([
    'whatsapp_message_id' => null, // ← NULL until job runs
]);
SendWhatsappMessageJob::dispatch($dbMessage->id);

// Better:
$metaResponse = $whatsapp->sendText(...);
$whatsappMessageId = data_get($metaResponse, 'messages.0.id');

$dbMessage = Message::create([
    'whatsapp_message_id' => $whatsappMessageId, // ← Set immediately
    'status' => 'sent',
]);
```

This eliminates the race condition entirely.

---

## Monitoring After Fix

### Log These Metrics:
```bash
# Count messages with pending status (should be minimal)
grep "status.*pending" storage/logs/laravel.log | wc -l

# Count successful status updates (should match sent messages)
grep "Message status updated" storage/logs/laravel.log | wc -l

# Count race conditions hit (should be 0 after fix)
grep "Message not found for status update" storage/logs/laravel.log | wc -l
```

### Expected Behavior After Fixes:
1. Send message → See "Message status updated {status: sent}"
2. Wait 2-3 seconds → See "Message status updated {status: delivered}"
3. Zero race condition warnings in logs
4. No "Message not found" messages

---

## Testing Without Meta

To test locally without sending real WhatsApp messages:

```bash
# Terminal 1: Watch logs
tail -f storage/logs/laravel.log

# Terminal 2: Send test webhook
curl -X POST http://localhost:8000/webhooks/meta/whatsapp \
  -H "Content-Type: application/json" \
  -d '{
    "entry": [{
      "changes": [{
        "field": "messages",
        "value": {
          "metadata": {"phone_number_id": "YOUR_PHONE_ID"},
          "messages": [{
            "id": "test_msg_123",
            "from": "919876543210",
            "timestamp": "1234567890",
            "type": "text",
            "text": {"body": "test message"}
          }]
        }
      }]
    }]
  }'

# Watch logs for:
# - "Processing inbound messages from webhook {count: 1}"
# - "Inbound message created" or warnings about customer/phone
```

---

## Summary

| Issue | Symptom | Solution |
|-------|---------|----------|
| Race condition | "Message not found for status" warnings | See Solution 2 or 3 above |
| WhatsApp number not found | "WhatsApp number not found" warnings | Verify phone_number_id in DB |
| Setting inactive | "Meta WhatsApp setting not active" | Set is_active = 1 |
| Invalid JSON | "Invalid Meta webhook payload" | Check Content-Type header |
| No logs at all | No webhook logs in Laravel logs | Verify webhook URL in Meta |
| Queue not running | Messages stuck in "pending" | Run `php artisan queue:work` |
