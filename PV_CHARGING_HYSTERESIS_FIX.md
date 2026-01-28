# PV Charging Rapid Cycling Fix

## Problem

PV charging was starting and stopping every minute in a rapid cycle:
1. **Surplus ≥ 1400W** → Start charging
2. Charging starts → Charger consumes power → Surplus drops below 1400W
3. **Surplus < 1400W** → Stop charging
4. Charging stops → Power available again → Surplus rises above 1400W
5. **Repeat infinitely!**

This caused:
- Excessive wear on the charger
- No actual charging (too short cycles)
- Constant notifications
- Log spam

## Root Cause

**No hysteresis** - The system used the same threshold (1400W) for both starting AND stopping charging.

## The Fix

### 1. Added Hysteresis

**Start threshold:** 1400W (min_pv_surplus)
**Stop threshold:** 1200W (min_pv_surplus - pv_charging_buffer)

Now:
- Charging starts when surplus **≥ 1400W**
- Charging continues until surplus **< 1200W**
- **200W buffer** prevents rapid cycling

### 2. Removed State Change Trigger

**Before:** Automation triggered on:
- Every 5 minutes
- **Every time `sensor.verfugbarer_pv_uberschuss` changed** ← Problem!

**After:** Automation triggers on:
- Every 2 minutes only

This prevents the automation from running every second when surplus fluctuates.

### 3. Changed Mode to `single`

**Before:** `mode: restart` - Could interrupt itself
**After:** `mode: single` - Waits for completion before running again

### 4. Added Detailed Logging

Every check now logs:
- Current surplus
- Start threshold
- Stop threshold
- Calculated current
- Charging state
- Decision made

Check logs at: **Settings → System → Logs**

### 5. Added Notifications

You'll now receive notifications when:
- **PV charging starts:** Shows surplus and charging current
- **PV charging stops:** Shows surplus and stop threshold

## Files Modified

### `automations.yaml`
```yaml
- id: energy_mgmt_pv_charging_adjust
  trigger:
    - platform: time_pattern
      minutes: "/2"  # Every 2 minutes (removed state trigger)
  mode: single  # Don't interrupt
```

### `scripts.yaml`
```yaml
adjust_pv_charging:
  variables:
    surplus: "{{ states('sensor.verfugbarer_pv_uberschuss') | float(0) }}"
    min_surplus: "{{ states('input_number.min_pv_surplus') | float(1400) }}"
    buffer: "{{ states('input_number.pv_charging_buffer') | float(200) }}"
    stop_threshold: "{{ (min_surplus - buffer) | int }}"  # Hysteresis!

  choose:
    # Start: surplus >= 1400W
    - conditions: "{{ surplus >= min_surplus }}"

    # Adjust: surplus >= 1200W (keeps charging)
    - conditions: "{{ surplus >= stop_threshold }}"

    # Stop: surplus < 1200W
    - conditions: "{{ surplus < stop_threshold }}"
```

## How It Works Now

### Example Scenario:

**Initial state:** Not charging, surplus = 1500W

| Time | Surplus | Action | Reason |
|------|---------|--------|--------|
| 10:00 | 1500W | **Start** | surplus (1500W) ≥ start threshold (1400W) |
| 10:02 | 1300W | **Continue** | surplus (1300W) ≥ stop threshold (1200W) ✓ |
| 10:04 | 1250W | **Continue** | surplus (1250W) ≥ stop threshold (1200W) ✓ |
| 10:06 | 1150W | **STOP** | surplus (1150W) < stop threshold (1200W) |
| 10:08 | 1350W | **Idle** | surplus (1350W) < start threshold (1400W) |
| 10:10 | 1450W | **Start** | surplus (1450W) ≥ start threshold (1400W) |

### Key Points:

1. **Once charging:** Continues as long as surplus ≥ 1200W (not 1400W!)
2. **200W buffer zone:** Between 1200-1400W, state persists
3. **2-minute checks:** Prevents rapid decisions
4. **Single mode:** Can't interrupt itself

## Configuration

You can adjust the hysteresis buffer:

```
Settings → Devices → Input Numbers → PV Charging Buffer
```

**Current value:** 200W
**Recommended:** 200-400W

- **Smaller buffer (100-200W):** More responsive, but may still cycle
- **Larger buffer (300-500W):** More stable, but requires more surplus to start

## Activation

**Reload automations and scripts:**
```
Developer Tools → YAML → Automations → Reload
Developer Tools → YAML → Scripts → Reload
```

Or restart Home Assistant.

## Monitoring

### Check Logs

**Settings → System → Logs**

Look for entries like:
```
PV Charging check: surplus=1450W, start_threshold=1400W,
stop_threshold=1200W, current=8A, is_charging=False

PV Charging: Starting with 8A (surplus: 1450W)

PV Charging: Adjusting to 10A (surplus: 1600W)

PV Charging: Stopping (surplus: 1150W < stop_threshold: 1200W)
```

### Watch Notifications

Your phone will receive:
- ☀️ **PV-Laden gestartet** when charging begins
- ⏸️ **PV-Laden gestoppt** when charging stops

### Dashboard Monitoring

Check:
- **`sensor.verfugbarer_pv_uberschuss`** - Current surplus
- **`sensor.berechneter_ladestrom_pv`** - Calculated current
- **`sensor.garage_status`** - Charging state

## Expected Behavior

### Normal Operation:

**Morning (clouds passing):**
- Surplus fluctuates: 1300W → 1500W → 1200W → 1600W
- Charging: Stays on as long as ≥ 1200W
- No rapid cycling!

**Midday (stable sun):**
- Surplus steady: 2500W
- Charging: Continuous at max current
- Occasional adjustments every 2 minutes

**Afternoon (sun fading):**
- Surplus dropping: 1800W → 1500W → 1300W → 1100W
- Charging: Stops only when < 1200W
- No premature stopping

## Troubleshooting

### Still Cycling?

If charging still cycles rapidly:

1. **Increase buffer:**
   ```
   Settings → Input Numbers → PV Charging Buffer → 400
   ```

2. **Check surplus sensor:**
   - Is it stable or jumping wildly?
   - May need smoothing/averaging

3. **Check logs for threshold values:**
   - Verify stop_threshold is calculated correctly
   - Should be 200W below start_threshold

### Not Starting at All?

Check:
- `input_number.min_pv_surplus` - Start threshold (default: 1400W)
- `sensor.verfugbarer_pv_uberschuss` - Must be ≥ 1400W
- `sensor.berechneter_ladestrom_pv` - Must be ≥ 6A

### Stopping Too Soon?

Increase the buffer:
```
PV Charging Buffer: 200 → 300 or 400
```

This makes the system "hold on" longer when surplus drops.

## Summary

**The fix implements proper hysteresis:**
- ✅ Different start/stop thresholds (200W gap)
- ✅ Time-based trigger only (every 2 min)
- ✅ Single mode (no interrupts)
- ✅ Detailed logging for diagnosis
- ✅ Notifications for visibility

**Result:**
- No more rapid cycling
- Stable PV charging
- Efficient use of solar surplus
- Less wear on equipment
