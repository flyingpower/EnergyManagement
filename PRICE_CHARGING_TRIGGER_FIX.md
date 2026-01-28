# Price Charging Trigger Fix - Check Every 5 Minutes

## The Issue

The price charging automation only triggered:
1. At the top of each hour (00:00, 01:00, etc.)
2. When sensor.optimaler_ladeplan updates

**Problem:**
- If you're at 16:30 and the plan says charging should happen at 16:00, the automation won't trigger until 17:00
- You miss 30 minutes of charging!

## The Fix

### Quick Fix (Do This Now!)

**Manually trigger the automation:**

1. Go to **Developer Tools → Automations**
2. Search for **"Preisplan ausführen"**
3. Click **"Run"** button

This immediately checks the plan and starts charging if needed.

### Permanent Fix (Prevents Future Issues)

I've updated the automation to check **every 5 minutes** instead of only every hour.

**New trigger schedule:**
- 00:00, 00:05, 00:10, 00:15, 00:20, 00:25, 00:30, 00:35, 00:40, 00:45, 00:50, 00:55
- 01:00, 01:05, 01:10, ... (and so on)

**Files to copy (when SMB available):**

1. **`price_charging_automation.yaml`** - Updated with 5-minute checks
2. **`price_charging_manual_trigger.yaml`** - NEW manual trigger script

```bash
# When SMB is mounted:
cp /Users/mif7fe/Documents/Projects/EnergyManagement/packages/energy_management/price_charging_automation.yaml /Volumes/config/packages/energy_management/

cp /Users/mif7fe/Documents/Projects/EnergyManagement/packages/energy_management/price_charging_manual_trigger.yaml /Volumes/config/packages/energy_management/
```

After copying, reload:
- **Developer Tools → YAML → Automations → Reload**
- **Developer Tools → YAML → Scripts → Reload**

### Bonus: Manual Trigger Script (Optional)

I've also created a script that you can add to your dashboard to manually trigger the price check:

**Script:** `script.trigger_price_charging`

**Add to dashboard:**
```yaml
type: button
name: Preisladen Jetzt Prüfen
icon: mdi:lightning-bolt
tap_action:
  action: call-service
  service: script.trigger_price_charging
```

This gives you a button to force-check the plan whenever you want.

## How It Works Now

### Before (Hourly Only):

| Time | What Happens |
|------|--------------|
| 15:30 | Plan updated: "Charge at 16:00" |
| 15:45 | Nothing (waiting for next hour) |
| 16:00 | ✓ Automation triggers → Starts charging |
| 16:30 | Plan changes: "Stop charging" |
| 16:45 | Still charging (waiting for next hour) |
| 17:00 | ✓ Automation triggers → Stops charging |

**Problem:** Delays of up to 60 minutes!

### After (Every 5 Minutes):

| Time | What Happens |
|------|--------------|
| 15:30 | Plan updated: "Charge at 16:00" |
| 15:35 | Automation checks → Not yet 16:00 → Wait |
| 15:40 | Automation checks → Not yet 16:00 → Wait |
| 15:45 | Automation checks → Not yet 16:00 → Wait |
| 15:50 | Automation checks → Not yet 16:00 → Wait |
| 15:55 | Automation checks → Not yet 16:00 → Wait |
| 16:00 | ✓ Automation triggers → Starts charging |
| 16:05 | Automation checks → Should charge → Already charging → OK |
| 16:30 | Plan changes: "Stop charging" |
| 16:35 | ✓ Automation triggers → Stops charging |

**Result:** Maximum delay of 5 minutes!

## Why Every 5 Minutes?

**Too frequent (every 1 minute):**
- Wastes resources
- Creates log spam
- Unnecessarily triggers automations

**Too infrequent (every 30 minutes):**
- Still misses start times
- Delays can be 30 minutes

**Every 5 minutes:**
- ✅ Good balance between responsiveness and efficiency
- ✅ Maximum 5-minute delay to start/stop
- ✅ Only 12 checks per hour (reasonable)
- ✅ Catches mid-hour plan changes

## Current Situation

You said charging doesn't start even though the plan shows it should. Here's why:

**Current time:** Likely 16:XX (not exactly 16:00)
**Plan says:** Charge at hour 16
**Automation last ran:** Probably at 16:00 (before you enabled price charging)
**Next automatic run:** 17:00

**Solution right now:**
1. **Developer Tools → Automations → "Preisplan ausführen" → Run**
2. Charging should start immediately

**After applying the fix:**
- Automation will check again at next 5-minute mark (16:35, 16:40, etc.)
- Will catch the charging requirement and start automatically

## Alternative: Check Every Minute (More Responsive)

If 5 minutes is still too slow, you can make it check every minute:

```yaml
trigger:
  - platform: time_pattern
    minutes: "*"  # Every minute
```

But this creates more load on the system (60 checks/hour vs 12 checks/hour).

## Testing the Fix

After copying files and reloading:

1. **Check automation triggers:**
   - Developer Tools → Automations → "Preisplan ausführen"
   - Look at "Last triggered" timestamp
   - Wait 5 minutes
   - Should update to new timestamp

2. **Check logs:**
   - Settings → System → Logs
   - Search: "Price charging automation triggered"
   - Should see entries every 5 minutes (when conditions are met)

3. **Test mid-hour start:**
   - Set `price_threshold_80` very high (e.g., 1.00)
   - Wait 5 minutes
   - Should NOT charge
   - Lower `price_threshold_80` to current price
   - Wait up to 5 minutes
   - Should start charging

## Summary

**Issue:** Automation only checked every hour → Missed charging opportunities

**Quick fix:** Manually trigger automation right now (Developer Tools → Run)

**Permanent fix:** Updated automation to check every 5 minutes

**Bonus:** Created manual trigger script for dashboard button

**Files ready to copy:**
- `price_charging_automation.yaml` (updated)
- `price_charging_manual_trigger.yaml` (new)

**Next step:** When SMB available, copy files and reload automations/scripts.
