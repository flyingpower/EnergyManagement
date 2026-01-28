# Smart PV Charging Implementation Summary

## What Was Implemented

I've successfully implemented the new smart PV charging algorithm with time-based stability as requested.

### Key Features

1. **3-Minute Stability for Starting**
   - Charging only starts when surplus ≥ 1400W for 3+ minutes
   - Prevents false starts from brief solar spikes

2. **Dynamic Current Adjustment**
   - Every 30 seconds, recalculates optimal charging current
   - Targets ~0W net grid consumption (no import, minimal export)
   - Automatically adjusts between 6A and 32A

3. **3-Minute Stability for Stopping**
   - Only stops when at minimum (6A) AND consuming > 200W from grid for 3+ minutes
   - Prevents premature stopping from brief clouds

4. **New Buffer Meaning**
   - `pv_charging_buffer` (200W) now means: maximum allowed grid consumption
   - Previously: hysteresis gap (deprecated in smart algorithm)

## Files Modified/Created

### 1. `/packages/energy_management/pv_charging_helpers.yaml` (NEW)
**Created helper entities:**
- `input_number.pv_start_condition_timer`: Tracks seconds start condition met (0-600s)
- `input_number.pv_stop_condition_timer`: Tracks seconds stop condition met (0-600s)
- `sensor.pv_target_charging_current`: Calculates optimal current for ~0W net consumption
- `sensor.net_grid_consumption`: Shows net grid import (+) or export (-)

### 2. `/packages/energy_management/scripts.yaml` (MODIFIED)
**Added new script:**
- `adjust_pv_charging_smart`: Complete rewrite with:
  - Timer increment/reset logic (every 30 seconds)
  - Start charging after 180s timer threshold
  - Dynamic current adjustment while charging
  - Stop charging after 180s at minimum current with grid consumption
  - Comprehensive logging
  - Smartphone notifications

**Old script preserved:**
- `adjust_pv_charging`: Old hysteresis version (not used by automation anymore)

### 3. `/packages/energy_management/automations.yaml` (MODIFIED)
**Updated automation `energy_mgmt_pv_charging_adjust`:**
- Trigger changed: Every 30 seconds (was every 2 minutes)
- Action changed: Calls `script.adjust_pv_charging_smart` (was `script.adjust_pv_charging`)
- Removed condition for `charging_state == "pv"` to allow starting from idle

### 4. Documentation (NEW)
- `PV_CHARGING_SMART_ALGORITHM.md`: Complete explanation with examples
- `SMART_PV_IMPLEMENTATION_SUMMARY.md`: This file

## Files Copied to Home Assistant

All files have been copied to `/Volumes/config/packages/energy_management/`:
- ✅ `pv_charging_helpers.yaml`
- ✅ `scripts.yaml`
- ✅ `automations.yaml`
- ✅ Documentation files

## Next Steps (What You Need to Do)

### 1. Reload YAML Configuration

Go to **Developer Tools → YAML** and reload:
- **Scripts** → Reload
- **Automations** → Reload
- **Manually Configured YAML Entities** → Reload (for helper inputs and sensors)

**OR** restart Home Assistant completely (safer option).

### 2. Verify Entities Exist

Check that new entities were created:
- `input_number.pv_start_condition_timer`
- `input_number.pv_stop_condition_timer`
- `sensor.pv_target_charging_current`
- `sensor.net_grid_consumption`

Go to **Settings → Devices & Services → Entities** and search for "pv".

### 3. Monitor First Charging Session

Watch the logs during the first sunny day:
- **Settings → System → Logs**
- Look for "PV Smart:" entries
- Verify timers are incrementing correctly
- Check that current adjusts dynamically

### 4. Add to Dashboard (Optional)

You may want to add these entities to your dashboard for monitoring:

```yaml
- type: entities
  title: PV Charging Smart
  entities:
    - entity: sensor.verfugbarer_pv_uberschuss
      name: PV Surplus
    - entity: sensor.pv_target_charging_current
      name: Target Current
    - entity: sensor.net_grid_consumption
      name: Net Grid Consumption
    - entity: input_number.pv_start_condition_timer
      name: Start Timer
    - entity: input_number.pv_stop_condition_timer
      name: Stop Timer
    - entity: sensor.garage_status
      name: Charger Status
    - entity: sensor.garage_dynamic_charger_limit
      name: Current Charging Amps
```

## Configuration Settings

### Current Values (recommended)
- `min_pv_surplus`: 1400W (start threshold)
- `pv_charging_buffer`: 200W (maximum grid consumption before stopping)

### Adjustments if Needed

**If charging cycles too much (unlikely with new algorithm):**
- Increase `pv_charging_buffer` to 300-400W

**If charging starts too late:**
- Decrease `min_pv_surplus` to 1200W

**If charging doesn't start at all:**
- Check that surplus actually reaches ≥ 1400W for 3 consecutive minutes
- Verify `sensor.pv_target_charging_current` shows ≥ 6A
- Check logs for timer increments

## Expected Behavior

### Typical Charging Day

**Morning (10:00 AM):**
```
10:00 - Surplus reaches 1500W → Start timer begins: 30s
10:01 - Surplus 1480W → Timer: 60s
10:02 - Surplus 1520W → Timer: 90s, 120s, 150s
10:03 - Surplus 1510W → Timer: 180s → START CHARGING at 6A
```

**Midday (12:00 PM):**
```
12:00 - Production high (3500W), consumption 1000W
      - Available: 3500W - 1000W + current charging = high
      - Target current: 16A
      - Net consumption: ~0W (balanced)
12:01 - Production increases to 4000W
      - Target adjusts to 20A
      - Still ~0W net (optimal)
```

**Afternoon (14:00 PM):**
```
14:00 - Production dropping, clouds
      - Current drops from 12A → 8A → 6A (minimum)
      - Net consumption: +150W (importing, below 200W buffer)
      - Stop timer: 0s (condition not met)
14:01 - Still at 6A minimum
      - Net consumption: +250W (above buffer!)
      - Stop timer: 30s, 60s, 90s...
14:04 - Stop timer reaches 180s → STOP CHARGING
```

## Troubleshooting

### Check Logs

**Settings → System → Logs**, look for:
```
PV Smart: surplus=1500W, target_current=8A, net=-50W,
          is_charging=False, start_timer=90s, stop_timer=0s
```

### Check Sensors

- `sensor.verfugbarer_pv_uberschuss`: Must be calculated correctly
- `sensor.tibber_power`: House consumption
- `sensor.tibber_power_production`: Solar production
- `sensor.garage_power`: Current charger power (kW)

### Common Issues

**Timer not incrementing:**
- Surplus fluctuating around 1400W threshold
- Check if surplus sensor is stable

**Not starting even with surplus:**
- Verify `input_boolean.enable_pv_charging` is ON
- Check `binary_sensor.auto_angeschlossen` is ON
- Look for errors in logs

**Stops too quickly:**
- Increase `pv_charging_buffer` from 200W to 300-400W

## Benefits Over Previous Algorithm

| Metric | Old Algorithm | New Algorithm |
|--------|---------------|---------------|
| Rapid cycling | ❌ Yes (every minute) | ✅ No (3-min timers) |
| Start stability | ⚠️ Moderate (immediate) | ✅ High (3-min wait) |
| Current adjustment | ⚠️ Fixed calculation | ✅ Dynamic (~0W net) |
| Stop stability | ⚠️ Immediate at 1200W | ✅ 3-min at minimum |
| Grid consumption | ⚠️ Variable | ✅ Minimized (~0W) |
| Equipment wear | ⚠️ High (rapid cycling) | ✅ Low (stable) |
| Efficiency | ⚠️ Good | ✅ Excellent |

## Summary

✅ **Implemented:**
- Time-based 3-minute stability for start/stop
- Dynamic current adjustment every 30 seconds
- Target ~0W net grid consumption
- New helper entities and sensors
- Comprehensive logging and notifications

✅ **Files Updated:**
- `pv_charging_helpers.yaml` (NEW)
- `scripts.yaml` (added smart script)
- `automations.yaml` (updated to use smart script)

✅ **Copied to Config:**
- All files in `/Volumes/config/packages/energy_management/`

🔄 **Next: Reload YAML configuration or restart Home Assistant**

📖 **Full Documentation:** `PV_CHARGING_SMART_ALGORITHM.md`
