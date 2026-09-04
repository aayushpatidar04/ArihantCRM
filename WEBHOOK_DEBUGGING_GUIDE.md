# WhatsApp Webhook Debugging Guide

## What I Fixed

I've added comprehensive logging to your webhook implementation to identify the root causes of missing delivery and inbound message logs.

### Changes Made:

1. **Enhanced Error Logging in Webhook Controller** - `app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php`
   - Added detailed error logging with stack traces
   - Logs Content-Type and payload size for debugging
   - Shows exactly why webhook processing failed

2. **Added Validation Logging in Webhook Service** - `app/Services/MetaWhatsappWebhookService.php`
   - Logs JSON parsing errors with details
   - Logs when WhatsApp numbers aren't found
   - Logs when Meta settings are missing or inactive
   - **CRITICAL**: Added logging when status updates fail to find messages

3. **Added Inbound Message Processing Logs**
   - Logs when messages are created successfully
   - Logs why messages are rejected (missing phone, no customer, duplicate, etc.)
   - Logs processing progress

4. **Added Status Update Logs**
   - **IMPORTANT**: Now logs when a message can't be found for a status update
   - Shows the message ID and timestamps for debugging the race condition

---

## Critical Issue: Race Condition ⚠️

### The Problem:
When you send a message through the UI:
1. Message is created in database with `status = 'pending'`
2. `SendWhatsappMessageJob` queue job processes the message
3. Job sends to Meta and gets back a `whatsapp_message_id`
4. Job updates the message with `whatsapp_message_id` and `status = 'sent'`
5. **Meta immediately sends a "delivered" webhook (usually within 100ms)**
6. Webhook tries to find message by `whatsapp_message_id`
7. **If the database update from step 4 hasn't finished yet → FAIL** ❌

### Result:
The status update from Meta is **silently ignored** with no logs.

### Solution:
With the new logging, you'll see warnings like:
```
Message not found for status update: whatsapp_message_id=xxx
```

---

## How to Debug Now

### Step 1: Check the Laravel Logs
```bash
# Terminal - monitor logs in real-time
tail -f storage/logs/laravel.log

# Or check the latest log file
cat storage/logs/laravel.log | grep -i "meta\|webhook"
```

### Step 2: Send a Test Message
1. Open the chat interface
2. Send a message to a customer
3. Watch the logs in real-time

### Step 3: Look for These Log Patterns:

**✅ GOOD FLOW:**
```
[2026-09-04 10:30:45] local.INFO: Meta Webhook Received {size: 1234, content_type: "application/json"}
[2026-09-04 10:30:45] local.DEBUG: Meta Webhook Processing {payload_length: 1234, timestamp: "2026-09-04..."}
[2026-09-04 10:30:45] local.INFO: Processing status updates from webhook {count: 1, whatsapp_number_id: 1}
[2026-09-04 10:30:45] local.INFO: Message status updated {message_id: 42, old_status: "sent", new_status: "delivered"}
[2026-09-04 10:30:45] local.INFO: Webhook processed successfully
```

**❌ BAD FLOW - Message Not Found:**
```
[2026-09-04 10:30:45] local.WARNING: Message not found for status update {
  "whatsapp_message_id": "wamid.xxx",
  "whatsapp_number_id": 1,
  "status": "delivered"
}
```

This indicates the **RACE CONDITION** - the status webhook arrived before the message was saved.

**❌ BAD FLOW - Invalid JSON:**
```
[2026-09-04 10:30:45] local.ERROR: Invalid Meta webhook payload {
  "payload": "{invalid json...",
  "json_error": "Syntax error"
}
```

**❌ BAD FLOW - WhatsApp Number Not Found:**
```
[2026-09-04 10:30:45] local.WARNING: WhatsApp number not found {
  "phone_number_id": "123456"
}
```

---

## Debugging Checklist

### Basic Connectivity:
- [ ] Webhook URL is correctly configured in Meta Business Manager
- [ ] Meta can reach the URL (not behind firewall/VPN)
- [ ] HTTP/HTTPS protocol is correct (check your config)

### Database Setup:
- [ ] WhatsappNumber record exists with correct `phone_number_id`
- [ ] MetaWhatsappSetting record exists and `is_active = true`
- [ ] Message.whatsapp_message_id is being populated after sending

### Queue Processing:
- [ ] Laravel queue worker is running: `php artisan queue:work`
- [ ] Messages are being processed (check logs for job execution)
- [ ] Database updates are happening (check Message table)

### Webhook Verification:
- [ ] GET `/webhooks/meta/whatsapp?hub_mode=subscribe&hub_verify_token=YOUR_TOKEN&hub_challenge=CHALLENGE`
- [ ] Should return `200 OK` with the challenge in body
- [ ] Check logs for: `Meta Webhook Verification:`

---

## Common Issues & Solutions

### Issue 1: "WhatsApp number not found"
**Cause**: The `phone_number_id` from Meta doesn't match your database

**Solution**:
```bash
# Check if phone_number_id is stored correctly
sqlite3 database/database.sqlite
SELECT id, phone_number_id, display_phone_number FROM whatsapp_numbers;

# Should match the phone_number_id that Meta sends in webhooks
```

### Issue 2: "Meta WhatsApp setting not active"
**Cause**: The MetaWhatsappSetting.is_active is false

**Solution**:
```bash
# Verify the setting is active
SELECT id, is_active, verify_token FROM meta_whatsapp_settings;

# If not active, update it:
UPDATE meta_whatsapp_settings SET is_active = 1;
```

### Issue 3: "Message not found for status update"
**Cause**: Race condition - webhook arrived before message was saved

**Solution**: This is expected initially. Watch for patterns:
- If it happens for EVERY status → something is wrong
- If it happens occasionally → normal, just a timing issue
- The message will eventually be found if Meta retries

### Issue 4: Logs show nothing for messages/statuses
**Cause**: Webhook isn't being called at all

**Solution**:
1. Verify webhook URL in Meta Business Manager
2. Test: `curl -X POST https://yoursite.com/webhooks/meta/whatsapp -H "Content-Type: application/json" -d '{"entry":[]}'`
3. Check firewall/proxy isn't blocking Meta's requests

---

## Testing the Webhook

### Manual Test 1: Verify Endpoint
```bash
# Test webhook verification (GET request)
curl "http://localhost:8000/webhooks/meta/whatsapp?hub_mode=subscribe&hub_verify_token=your-token&hub_challenge=test-challenge"

# Should return: test-challenge
```

### Manual Test 2: Send Sample Payload
```bash
curl -X POST http://localhost:8000/webhooks/meta/whatsapp \
  -H "Content-Type: application/json" \
  -d '{
    "entry": [{
      "changes": [{
        "field": "messages",
        "value": {
          "messaging_product": "whatsapp",
          "metadata": {
            "phone_number_id": "1234567890"
          },
          "statuses": [{
            "id": "wamid.test",
            "status": "delivered",
            "timestamp": "1234567890"
          }]
        }
      }]
    }]
  }'
```

Then check logs for:
```
Processing status updates from webhook {count: 1}
Message not found for status update... (or Message status updated...)
```

---

## Next Steps

1. **Run Laravel queue worker** (if not already running):
   ```bash
   php artisan queue:work --tries=3 --timeout=60
   ```

2. **Monitor logs in real-time**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "webhook\|message\|status"
   ```

3. **Send a test message** through your UI

4. **Analyze logs** using the patterns above

5. **Share relevant logs** if you need further help

---

## Log Levels

The new logging uses different levels:
- `INFO` - Normal operations (webhook received, message created, status updated)
- `DEBUG` - Detailed flow (duplicate messages skipped)
- `WARNING` - Unexpected but non-fatal (message not found, phone number missing)
- `ERROR` - Fatal issues (invalid JSON, exceptions)

Filter logs by level:
```bash
# Only errors
grep "ERROR" storage/logs/laravel.log

# Warnings and errors
grep -E "WARNING|ERROR" storage/logs/laravel.log

# All webhook activity
grep "Meta" storage/logs/laravel.log
```

---

## Files Modified

1. `app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php`
   - Enhanced error logging
   - Payload size logging

2. `app/Services/MetaWhatsappWebhookService.php`
   - Validation logging
   - Processing flow logging
   - Status update debugging
   - Inbound message debugging

---

## Getting Help

When reporting issues, please share:
1. Recent logs from `storage/logs/laravel.log` (grep for "Meta" or "webhook")
2. The phone_number_id and verify_token from your Meta settings
3. Output of: `php artisan queue:work` (check if it's running)
4. Whether webhook verification (GET request) works
