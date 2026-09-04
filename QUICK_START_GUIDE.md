# IMMEDIATE ACTION PLAN - WhatsApp Webhook Issues

## What's Wrong ❌

Your webhook receives messages from Meta but they're not being logged. The issue is **race conditions** between database saves and webhook status updates.

## What I Fixed ✅

Added comprehensive logging throughout the webhook flow in:
- `app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php`
- `app/Services/MetaWhatsappWebhookService.php`

## DO THIS NOW 🚀

### Step 1: Deploy Changes (2 minutes)
```bash
# The files are already edited. Just commit:
git add app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php
git add app/Services/MetaWhatsappWebhookService.php
git commit -m "Add comprehensive webhook logging for debugging"

# Or if you're not using git, just ensure files are saved
```

### Step 2: Check Prerequisites (5 minutes)
```bash
# 1. Queue worker MUST be running
ps aux | grep "queue:work"

# If not running, start it:
php artisan queue:work --tries=3 --timeout=60 &

# 2. Check database can be accessed
php artisan tinker
>>> Message::count()
>>> WhatsappNumber::count()
>>> MetaWhatsappSetting::count()
>>> exit()

# All should return numbers > 0

# 3. Check webhook URL is correct in Meta Business Manager
# Should be: https://yoursite.com/webhooks/meta/whatsapp
```

### Step 3: Verify Webhook Endpoint (5 minutes)
```bash
# Test webhook verification endpoint
curl "http://localhost:8000/webhooks/meta/whatsapp?hub_mode=subscribe&hub_verify_token=YOUR_VERIFY_TOKEN&hub_challenge=test_challenge"

# Should return: test_challenge
# If error → check your verify_token in database
```

### Step 4: Monitor Logs (Start Now)
```bash
# Terminal 1: Watch logs
tail -f storage/logs/laravel.log

# Terminal 2: Send a test message via UI
# Watch Terminal 1 for logs...
```

---

## What to Look For in Logs

### ✅ EXPECTED - Successful Message Send:
```
[INFO] Meta Webhook Received {size: 1234}
[INFO] Processing inbound messages from webhook {count: 0}
[INFO] Processing status updates from webhook {count: 1}
[INFO] Message status updated {message_id: 42, new_status: "delivered"}
[INFO] Webhook processed successfully
```

### ⚠️ WARNING - Race Condition (Normal if occasional):
```
[WARNING] Message not found for status update {
  "whatsapp_message_id": "wamid.xxx",
  "status": "delivered"
}
```
- This is normal occasionally (timing issue)
- If it happens for EVERY message → there's a deeper problem
- Solution: Check Step 2 (queue worker running)

### ❌ ERROR - Real Problems:
```
[ERROR] Invalid Meta webhook payload
[WARNING] WhatsApp number not found {phone_number_id: "xxx"}
[WARNING] Meta WhatsApp setting not active
```
- Fix based on the specific error

---

## Verification Checklist

After changes are deployed, verify:

- [ ] Queue worker is running: `php artisan queue:work`
- [ ] Laravel logs exist: `ls -la storage/logs/`
- [ ] Logs show webhook activity: `grep "Meta Webhook" storage/logs/laravel.log`
- [ ] Send test message, logs show progress
- [ ] Database has Message records with `whatsapp_message_id` populated
- [ ] Message statuses show "delivered" or "read" (not stuck in "pending")

---

## Debugging Commands

```bash
# Check if messages are being saved
sqlite3 database/database.sqlite \
  "SELECT COUNT(*) as total, 
          SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
          SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent,
          SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) as delivered
   FROM messages;"

# Check WhatsApp numbers are configured
sqlite3 database/database.sqlite \
  "SELECT id, phone_number_id, is_active FROM whatsapp_numbers;"

# Check Meta settings are active
sqlite3 database/database.sqlite \
  "SELECT id, is_active, last_webhook_at FROM meta_whatsapp_settings;"

# Monitor logs for "Message not found" race conditions
grep "Message not found for status update" storage/logs/laravel.log | wc -l

# View latest logs
tail -50 storage/logs/laravel.log
```

---

## If Still Not Working

### Check 1: Is the Queue Running?
```bash
# Check if queue worker is running
ps aux | grep queue

# If not, start it:
php artisan queue:work --tries=3 --timeout=60

# Keep it running in background:
# Option A: Screen/tmux
screen -S queue
php artisan queue:work

# Option B: Supervisor (production)
# Create /etc/supervisor/conf.d/laravel-queue.conf
```

### Check 2: Are Webhooks Actually Hitting Your Server?
```bash
# Check for any webhook requests in logs
grep "Meta Webhook Received" storage/logs/laravel.log

# If ZERO matches, webhook isn't reaching your server:
# - Check URL in Meta Business Manager
# - Verify HTTPS/firewall isn't blocking
# - Confirm domain is accessible from internet
```

### Check 3: Is Database Writable?
```bash
php artisan tinker
>>> $msg = new \App\Models\Message();
>>> $msg->body = 'test';
>>> $msg->save();
>>> exit()

# If error → check database permissions
```

### Check 4: Contact Meta Support
If logs show webhooks arriving but still not working:
- Share webhook payload from logs
- Share error messages
- Verify webhook configuration in Meta

---

## Document References

Two detailed guides created:
1. **WEBHOOK_DEBUGGING_GUIDE.md** - Complete debugging reference
2. **WEBHOOK_RACE_CONDITION_ANALYSIS.md** - Technical deep-dive into the race condition

Read these for:
- Detailed log patterns
- Common issues & solutions
- Manual testing procedures
- Race condition explanation

---

## Summary of Changes

### Files Modified:
1. `app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php`
   - Added error logging with full stack trace
   - Logs Content-Type and payload size

2. `app/Services/MetaWhatsappWebhookService.php`
   - JSON parsing error logging
   - Webhook entry/change validation logging
   - Message processing flow logging
   - **Status update failure logging (critical)**
   - **Inbound message creation logging**

### New Log Coverage:
- Webhook reception with payload details
- JSON parsing errors (if any)
- WhatsApp number lookup (success/failure)
- Meta setting validation (active/inactive)
- Inbound message count and processing
- Status update count and processing
- **Message not found warnings (race condition detection)**
- **Message created successfully confirmations**
- **Message status update success confirmations**

---

## Next: Testing & Monitoring

1. **Immediate**: Start queue worker + monitor logs
2. **Short-term**: Send test messages, verify logs
3. **Medium-term**: Monitor for race condition warnings
4. **Long-term**: Implement Solution 3 from WEBHOOK_RACE_CONDITION_ANALYSIS.md (move whatsapp_message_id assignment to controller)

---

## Questions? Check These First

1. **No logs at all** → Is queue running? Is webhook URL correct in Meta?
2. **"Message not found" warnings** → Normal occasionally. If always → queue issue.
3. **Messages stuck in "pending"** → Queue worker not running.
4. **"WhatsApp number not found"** → phone_number_id doesn't match database.
5. **"Setting not active"** → Check is_active flag in meta_whatsapp_settings table.

---

**Status**: ✅ Ready to test. Start by running the queue worker and monitoring logs!
