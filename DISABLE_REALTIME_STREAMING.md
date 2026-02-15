# Disable Realtime Streaming - Fix Watchdog Restarts

## Problem
Home Assistant constantly restarts due to watchdog timeouts. Root cause:
- **Tibber realtime websocket** fails every 2-3 minutes
- **Easee realtime stream** fails frequently
- Both retry aggressively, **blocking the main thread**
- Watchdog detects frozen main thread → restarts HA

## Solution
Disable realtime streaming for both integrations. Switch to polling (30 second intervals) instead.

## Manual Steps (via Home Assistant UI)

### Step 1: Disable Tibber Realtime

1. Go to **Settings → Devices & Services**
2. Search for **"Tibber"**
3. Click on the **Tibber** integration
4. Click the **⋮ (three dots)** menu button
5. Look for **"Disable realtime streaming"** or **"Realtime"** option
6. **Toggle OFF** (disable it)

**Alternative (if toggle not visible):**
- Click **"Configure"** instead
- Look for "Enable realtime data" checkbox
- **Uncheck it**
- Click **"Submit"**

### Step 2: Disable Easee Realtime

1. Go to **Settings → Devices & Services**
2. Search for **"Easee"**
3. Click on the **Easee** integration
4. Click the **⋮ (three dots)** menu button
5. Look for **"Disable realtime"** or **"Live data"** option
6. **Toggle OFF** (disable it)

**Alternative:**
- Click **"Reconfigure"**
- Disable **"Enable realtime data"** or similar checkbox
- Click **"Submit"**

### Step 3: Reload Automations

After disabling both integrations:

1. Go to **Settings → System → Quick Reload**
2. Click **"Automations"** (to load the warning messages)
3. Check **System Log** (Settings → System → Logs) - should see:
   ```
   ⚠️ IMPORTANT: Tibber realtime streaming is DISABLED...
   ⚠️ IMPORTANT: Easee realtime streaming is DISABLED...
   ```

## What This Changes

### Before (Realtime Enabled)
```
Tibber: WebSocket → Connects → Fails after 2 min → Retry → Connects → Fails...
Easee: Stream → Connects → Fails after 5 min → Retry → Connects → Fails...
MainThread: BLOCKED by connection failures
Watchdog: "Main thread frozen" → RESTART HA
```

### After (Realtime Disabled)
```
Tibber: Poll every 30 seconds → Get data → Continue
Easee: Poll every 30 seconds → Get data → Continue
MainThread: Responsive, no blocking
Watchdog: Happy - no restarts
```

## Impact on Functionality

### Data You Keep
- ✅ Current electricity prices (still updated)
- ✅ Charger status (still updated)
- ✅ Battery SoC (still updated)
- ✅ Energy consumption (still tracked)
- ✅ All automations work normally

### What Changes
- ⏱️ Updates every **30 seconds** instead of real-time
- ✅ This is FINE for home automation (immediate response not critical)
- ✅ No impact on charging logic or energy calculations

### Latency
- **Before:** Updates every 1-2 seconds (when working)
- **After:** Updates every 30 seconds (stable)
- **Impact:** Negligible for energy management

## Verification

### Check Realtime is Disabled

1. **Settings → Devices & Services → Tibber**
   - Should NOT show a green "Connected" indicator for realtime
   - Or should show realtime as "Disabled"

2. **Settings → Devices & Services → Easee**
   - Should NOT show real-time status updates
   - Or should show realtime as "Disabled"

### Check System is Stable

1. **Settings → System → Logs**
   - Should NOT see these errors:
     ```
     ERROR (MainThread) [tibber.realtime] Watchdog: Connection is down
     ERROR (MainThread) [pyeasee.easee] SR stream disconnected
     ```

2. **Watch Home Assistant**
   - Should NOT restart on its own
   - Run for 30+ minutes without restart = SUCCESS ✅

## If Still Restarting

### Check 1: Did you disable BOTH integrations?
- Make sure BOTH Tibber AND Easee have realtime disabled
- Disabling just one won't fully solve it

### Check 2: Other problematic integrations
Check logs for other repeated errors:
- **Shelly devices** - slow to respond
- **Tesla Fleet** - connection issues
- **Solcast** - API rate limits

Fix: Increase poll interval or disable if not needed

### Check 3: System resources
Check under-voltage warnings in logs:
```
WARNING [homeassistant.components.rpi_power] Under-voltage detected
```
If present: **Get a better power supply for Raspberry Pi**

## After Disabling Realtime

You can safely:
- ✅ Let Home Assistant run 24/7
- ✅ Use all automations (they still work)
- ✅ Monitor energy consumption
- ✅ Control charging
- ✅ Reboot when needed (not forced restarts)

## Troubleshooting

**Q: Energy Manager seems slow to update**
A: Normal. Updates every 30s instead of 1-2s. Still plenty fast for automation.

**Q: Charging didn't start immediately**
A: Check after 30 seconds. Automation checks every 2-5 minutes anyway.

**Q: How do I re-enable realtime?**
A: Same steps as above, but **Toggle ON** instead. Not recommended - causes restarts.

**Q: Will prices be wrong?**
A: No. Prices update every 30 seconds, which is sufficient.

## Files Updated

- `packages/energy_management/disable_realtime.yaml` - Warning automations
- This document - Setup instructions

## Next Steps

1. **Disable both integrations** (follow steps above)
2. **Wait 5 minutes** - confirm no restarts
3. **Check logs** - should be clean
4. **Enjoy stable HA!** 🎉

---

**Important:** Once you disable realtime streaming, do NOT re-enable it unless you fix the underlying network/API issues causing the failures.
