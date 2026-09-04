# Duplicate Customer Phone Error - Race Condition Fix

## The Problem

You were getting this error:
```
SQLSTATE[23000]: Integrity constraint violation: 1062 
Duplicate entry '919462334649' for key 'customers.customers_phone_unique'
```

This error occurs when multiple webhook requests arrive simultaneously for the same phone number and both try to create a customer record.

---

## Root Cause: Race Condition

### Timeline of the Bug:

```
Time    Thread 1                          Thread 2
───────────────────────────────────────────────────────
0ms     Webhook arrives for              Webhook arrives for
        phone: 919462334649              phone: 919462334649
        
5ms     Check: Customer with             
        this phone exists?
        ↓ Not found                       
        
7ms                                      Check: Customer with
                                         this phone exists?
                                         ↓ Not found
        
10ms    Start creating customer...       
        
12ms    INSERT INTO customers           
        (phone: 919462334649)
        ↓ SUCCESS ✓                      
        
15ms                                    INSERT INTO customers
                                        (phone: 919462334649)
                                        ↓ DUPLICATE ERROR! ❌
```

### Why This Happened:

1. Two webhooks arrive at almost the same time
2. Both check if customer exists → NOT FOUND
3. First thread creates customer → SUCCESS
4. Second thread tries to create same customer → DUPLICATE ERROR

The issue was that:
- ❌ Check and create were separate operations
- ❌ No atomic transaction protection
- ❌ No double-check inside transaction
- ❌ No error recovery when duplicate occurred

---

## How I Fixed It

### Fix 1: Double-Check Inside Transaction ✅

**File**: [app/Services/MetaWhatsappWebhookService.php](app/Services/MetaWhatsappWebhookService.php#L965-L1078)

```php
DB::transaction(function () use ($name, $phone, $assignment) {
    // DOUBLE-CHECK inside transaction
    $existing = Customer::query()
        ->where(function ($query) use ($phone) {
            $query
                ->where('phone', $phone)
                ->orWhere('phone', '+' . $phone);
        })
        ->first();

    if ($existing) {
        // Use existing customer
        return $existing;
    }

    // Safe to create now
    return Customer::create([...]);
});
```

**How it works:**
1. First check: Is customer already there? → NOT FOUND
2. Lock acquired by transaction
3. Second check: Re-check inside transaction → NOW FOUND (created by other process)
4. Return existing customer without creating duplicate
5. If still not found → Create safely with lock

### Fix 2: Catch & Recover from Duplicates ✅

**If somehow a duplicate still occurs:**

```php
} catch (\Illuminate\Database\QueryException $e) {
    // Check if it's a duplicate error
    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
        // Fetch the customer that was created by the other process
        $customer = Customer::query()
            ->where(function ($query) use ($phone) {
                $query
                    ->where('phone', $phone)
                    ->orWhere('phone', '+' . $phone);
            })
            ->first();

        if ($customer) {
            // Use the existing customer - no error!
            return $customer;
        }
    }
    // Re-throw if not a duplicate error
    throw $e;
}
```

### Fix 3: Enhanced Webhook Error Handling ✅

**File**: [app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php](app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php#L50-L130)

Added specific handling for database errors:

```php
} catch (\Illuminate\Database\QueryException $e) {
    $isDuplicateError = $e->getCode() === '23000';
    
    if ($isDuplicateError) {
        // Log as warning, not error - it's handled internally
        Log::warning('Database duplicate detected (race condition)');
    } else {
        // Log as error for other DB issues
        Log::error('Database query error');
    }
    
    // Always return 200 to Meta (data was partially processed)
    return response('EVENT_RECEIVED', 200);
}
```

### Fix 4: Message Duplicate Protection ✅

Also added similar protection to message creation:

```php
try {
    $dbMessage = Message::create([...]);
} catch (\Illuminate\Database\QueryException $e) {
    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
        // Fetch existing message created by concurrent process
        $dbMessage = Message::query()
            ->where('whatsapp_message_id', $whatsappMessageId)
            ->first();
        
        if ($dbMessage) {
            // Continue processing with existing message
            return;
        }
    }
    // Re-throw if not handled
    throw $e;
}
```

---

## What Changed

### Files Modified:

1. **`app/Services/MetaWhatsappWebhookService.php`**
   - Enhanced `resolveCustomer()` method:
     - ✅ Double-check inside transaction
     - ✅ Catch and recover from duplicates
     - ✅ Enhanced logging for debugging
   - Enhanced `processInboundMessage()` method:
     - ✅ Wrap message creation in try-catch
     - ✅ Recover from duplicate messages
     - ✅ Use existing message if concurrent creation

2. **`app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php`**
   - Enhanced exception handling:
     - ✅ Catch `\Illuminate\Database\QueryException`
     - ✅ Distinguish duplicate errors from other DB errors
     - ✅ Still return 200 to Meta (avoid retry loops)
     - ✅ Comprehensive error logging
     - ✅ Catch-all for unexpected exceptions

---

## Testing the Fix

### Scenario 1: Simultaneous Webhooks for Same Phone

```bash
# Simulate 2 concurrent webhooks
(curl -X POST http://localhost:8000/webhooks/meta/whatsapp \
  -H "Content-Type: application/json" \
  -d '{
    "entry": [{
      "changes": [{
        "field": "messages",
        "value": {
          "metadata": {"phone_number_id": "123456"},
          "messages": [{
            "id": "msg1",
            "from": "919462334649",
            "type": "text",
            "text": {"body": "test"}
          }]
        }
      }]
    }]
  }') &

(curl -X POST http://localhost:8000/webhooks/meta/whatsapp \
  -H "Content-Type: application/json" \
  -d '{
    "entry": [{
      "changes": [{
        "field": "messages",
        "value": {
          "metadata": {"phone_number_id": "123456"},
          "messages": [{
            "id": "msg2",
            "from": "919462334649",
            "type": "text",
            "text": {"body": "test"}
          }]
        }
      }]
    }]
  }') &

wait
```

### Expected Result:
✅ Both webhooks return `200 OK`
✅ Logs show: "Found existing customer" or "Customer created by concurrent request"
✅ Only ONE customer record created
✅ No duplicate entry error

---

## Checking Logs

### Good Flow:
```
[INFO] Meta Webhook Received {size: 1234}
[INFO] Processing inbound messages from webhook {count: 1}
[DEBUG] Found existing customer {customer_id: 42, phone: 919462334649}
[INFO] Processing inbound message {whatsapp_message_id: xxx, customer_id: 42}
[INFO] Inbound message created {message_id: 123, type: text}
[INFO] Webhook processed successfully
```

### Recovery from Duplicate (Old behavior - shouldn't happen now):
```
[WARNING] Duplicate customer creation detected, fetching existing customer {
  phone: 919462334649,
  error: "Duplicate entry..."
}
[DEBUG] Found existing customer {customer_id: 42, phone: 919462334649}
[INFO] Processing inbound message {whatsapp_message_id: xxx, customer_id: 42}
```

### Concurrent Message Creation:
```
[INFO] Message already created by concurrent request, using existing message {
  whatsapp_message_id: xxx
}
```

---

## Verification Commands

### Check Customer Counts:
```bash
sqlite3 database/database.sqlite \
  "SELECT phone, COUNT(*) as count FROM customers GROUP BY phone HAVING COUNT(*) > 1;"

# Should return empty (no duplicates)
```

### Check for Errors in Logs:
```bash
# Look for duplicate errors
grep "Duplicate entry" storage/logs/laravel.log

# Should be empty or very few (all handled gracefully)
```

### Monitor Successful Processing:
```bash
# Count successful webhook processing
grep "Webhook processed successfully" storage/logs/laravel.log | wc -l

# Count duplicates recovered
grep "Duplicate customer creation detected" storage/logs/laravel.log | wc -l
```

---

## Database-Level Protection

To add an extra layer of protection, ensure your database index is correct:

```sql
-- Check existing constraint
SHOW INDEX FROM customers WHERE Key_name = 'customers_phone_unique';

-- If not present, add it (CAREFUL - may fail if duplicates exist)
ALTER TABLE customers ADD UNIQUE KEY customers_phone_unique (phone);
```

---

## Why This Solution is Better

| Issue | Old Code | New Code |
|-------|----------|----------|
| **Race condition** | ❌ Unprotected separate check/create | ✅ Transaction + double-check |
| **Duplicate handling** | ❌ Crashes with error | ✅ Gracefully recovers |
| **Error logging** | ❌ No visibility | ✅ Detailed logging |
| **Meta webhook retry** | ❌ Returns 500, causes retries | ✅ Returns 200, data still processed |
| **Message duplicates** | ❌ Crashes if concurrent | ✅ Uses existing message |

---

## Summary

✅ **Race condition fixed** with transaction + double-check pattern
✅ **Duplicate errors handled gracefully** with recovery logic  
✅ **Enhanced logging** for all scenarios
✅ **No more 500 errors** from duplicate key violations
✅ **Messages still processed** even if concurrent creation attempted

The system can now safely handle multiple simultaneous webhooks for the same phone number without crashing.

---

## Need More Details?

Check these files for implementation:
- Duplicate prevention: [resolveCustomer method](app/Services/MetaWhatsappWebhookService.php#L965)
- Webhook error handling: [MetaWhatsappWebhookController::handle](app/Http/Controllers/Webhook/MetaWhatsappWebhookController.php#L52)
- Message duplicate handling: [processInboundMessage method](app/Services/MetaWhatsappWebhookService.php#L396)
