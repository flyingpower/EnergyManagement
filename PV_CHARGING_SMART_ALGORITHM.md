# Smart PV Charging Algorithm with Time-Based Stability

## Overview

The new smart PV charging algorithm provides stable, efficient solar surplus charging by:
- **Time-based stability:** 3-minute waiting periods before starting/stopping
- **Dynamic current adjustment:** Automatically adjusts charging to maintain ~0W net grid consumption
- **Prevents rapid cycling:** No more start/stop every minute

## Algorithm Logic

### Start Condition

**Requirements:**
1. PV surplus ≥ `min_pv_surplus` (default: 1400W)
2. Condition stable for **3 minutes** (180 seconds)
3. Calculated target current ≥ 6A

**Process:**
- Every 30 seconds, check if surplus ≥ min_pv_surplus
- If YES and not charging: increment `pv_start_condition_timer` by 30 seconds
- If NO: reset `pv_start_condition_timer` to 0
- When timer reaches 180 seconds: **START CHARGING**

**Example:**
```
Time    Surplus    Timer    Action
10:00   1500W      0s       Timer starts
10:00   1500W      30s      Increment
10:01   1450W      60s      Increment
10:01   1480W      90s      Increment
10:02   1520W      120s     Increment
10:02   1490W      150s     Increment
10:03   1510W      180s     START! (3 minutes stable)
```

### Dynamic Current Adjustment

**While charging, every 30 seconds:**

The system calculates the optimal charging current to achieve **~0W net grid consumption**:

```
Available Power = Production - Consumption + Current Charging Power
Target Power = Available Power - Buffer (200W safety margin)
Target Current = Target Power / 230V
Clamped: 6A ≤ Target Current ≤ 32A
```

**Example:**
```
Production: 3000W
Consumption: 800W (house load)
Currently charging at 8A = 1840W

Net = Consumption - Production = 800W - 3000W = -2200W (exporting)
Available = Production - Consumption + Current Charging = 3000W - 800W + 1840W = 4040W
Target = Available - Buffer = 4040W - 200W = 3840W
Current = 3840W / 230V = 16.7A → 17A

Action: Increase charging from 8A to 17A
Result: Net consumption ≈ 0W (minimal grid import/export)
```

### Stop Condition

**Requirements:**
1. Charging at minimum current (6A)
2. Net grid consumption > `pv_charging_buffer` (default: 200W)
3. Condition stable for **3 minutes** (180 seconds)

**Process:**
- Every 30 seconds, check if current ≤ 6A AND net_consumption > buffer
- If YES: increment `pv_stop_condition_timer` by 30 seconds
- If NO: reset `pv_stop_condition_timer` to 0
- When timer reaches 180 seconds: **STOP CHARGING**

**Example:**
```
Time    Current    Net      Timer    Action
14:00   8A         -50W     0s       OK (exporting)
14:00   7A         +50W     0s       OK (low import)
14:01   6A         +150W    0s       Below buffer (200W)
14:01   6A         +250W    30s      Above buffer, timer starts
14:02   6A         +280W    60s      Still above, increment
14:02   6A         +270W    90s      Increment
14:03   6A         +260W    120s     Increment
14:03   6A         +240W    150s     Increment
14:04   6A         +230W    180s     STOP! (3 minutes at minimum)
```

## Configuration Parameters

### `input_number.min_pv_surplus`
- **Default:** 1400W
- **Meaning:** Minimum PV surplus to **start** charging
- **Recommendation:** 1200-1600W (enough for 6A charging)

### `input_number.pv_charging_buffer`
- **Default:** 200W
- **Meaning (NEW):** Maximum allowed grid consumption before stopping
- **Previous meaning:** Hysteresis buffer (deprecated in smart algorithm)
- **Recommendation:** 200-400W
  - Lower = more sensitive to grid import
  - Higher = allows more grid consumption before stopping

### Timer Inputs (automatic, read-only)
- `input_number.pv_start_condition_timer`: Tracks seconds start condition met (0-600s)
- `input_number.pv_stop_condition_timer`: Tracks seconds stop condition met (0-600s)

## Helper Sensors

### `sensor.pv_target_charging_current`
Calculates the optimal charging current to achieve ~0W net consumption.

**Formula:**
```yaml
consumption = Tibber power consumption
production = Tibber power production
current_charging = Easee charger power × 1000

available = production - consumption + current_charging
target_power = available - buffer
target_current = target_power / 230V
clamped: max(6, min(32, target_current))
```

### `sensor.net_grid_consumption`
Shows net grid import (+) or export (-).

**Formula:**
```yaml
net = consumption - production
```
- Positive = importing from grid
- Negative = exporting to grid
- Zero = balanced (ideal)

## File Structure

### `/packages/energy_management/pv_charging_helpers.yaml`
Contains:
- Timer inputs (`pv_start_condition_timer`, `pv_stop_condition_timer`)
- Template sensors (`pv_target_charging_current`, `net_grid_consumption`)

### `/packages/energy_management/scripts.yaml`
Contains:
- `adjust_pv_charging_smart`: New smart algorithm (replaces old script)
- Timer increment/reset logic
- Dynamic current adjustment
- Start/stop logic with 3-minute stability

### `/packages/energy_management/automations.yaml`
Modified automation:
- Trigger: Every 30 seconds (was every 2 minutes)
- Calls: `script.adjust_pv_charging_smart`

## How It Works (Step by Step)

### Starting Charging

1. **10:00:00** - Surplus jumps to 1500W
   - Start timer: 0s → 30s
   - Action: Wait

2. **10:00:30** - Surplus still 1480W
   - Start timer: 30s → 60s
   - Action: Wait

3. **10:01:00** - Surplus 1520W
   - Start timer: 60s → 90s
   - Action: Wait

4. **10:01:30** - Surplus 1490W
   - Start timer: 90s → 120s
   - Action: Wait

5. **10:02:00** - Surplus 1510W
   - Start timer: 120s → 150s
   - Action: Wait

6. **10:02:30** - Surplus 1500W
   - Start timer: 150s → 180s
   - Action: Wait

7. **10:03:00** - Surplus 1510W
   - Start timer: **180s reached!**
   - Target current: (1510W - 200W) / 230V = 5.7A → 6A
   - Action: **START CHARGING at 6A (1-phase)**
   - Reset start timer to 0
   - Notification sent

### Adjusting Current

8. **10:03:30** - Charging at 6A, surplus increases
   - Production: 3500W, Consumption: 1000W
   - Currently charging: 6A × 230V = 1380W
   - Available: 3500W - 1000W + 1380W = 3880W
   - Target: (3880W - 200W) / 230V = 16A
   - Action: **ADJUST to 16A**

9. **10:04:00** - Charging at 16A, production stable
   - Production: 3600W, Consumption: 1000W
   - Currently charging: 16A × 230V = 3680W
   - Available: 3600W - 1000W + 3680W = 6280W
   - Target: (6280W - 200W) / 230V = 26.4A → 26A
   - Action: **ADJUST to 26A**

10. **10:04:30** - Charging at 26A, production dropping
    - Production: 3000W, Consumption: 1000W
    - Currently charging: 26A × 230V = 5980W
    - Available: 3000W - 1000W + 5980W = 7980W
    - Target: (7980W - 200W) / 230V = 33.8A → 32A (max)
    - Net: 1000W - 3000W = -2000W (still exporting)
    - Action: **ADJUST to 32A (maximum)**

### Stopping Charging

11. **14:00:00** - Production dropping, clouds
    - Currently at 8A
    - Target: 7A
    - Action: **ADJUST to 7A**

12. **14:00:30** - Production very low
    - Currently at 7A
    - Target: 5.5A → 6A (minimum)
    - Action: **ADJUST to 6A (minimum)**

13. **14:01:00** - At minimum, importing from grid
    - Production: 1200W, Consumption: 1600W
    - Charging: 6A × 230V = 1380W
    - Net: 1600W - 1200W = **+400W** (importing)
    - Buffer: 200W
    - Net > Buffer: YES (400W > 200W)
    - Stop timer: 0s → 30s
    - Action: Wait

14. **14:01:30** - Still at minimum, still importing
    - Net: **+380W** (importing)
    - Net > Buffer: YES
    - Stop timer: 30s → 60s
    - Action: Wait

15. **14:02:00 - 14:03:30** - Continues for 3 more minutes
    - Stop timer increments: 60s → 90s → 120s → 150s → 180s

16. **14:04:00** - Stop timer reaches 180s
    - Action: **STOP CHARGING**
    - Reset stop timer to 0
    - Notification sent

## Benefits

### Stability
- No rapid cycling (start/stop every minute)
- 3-minute waiting periods ensure conditions are truly stable
- Much less wear on charger and vehicle

### Efficiency
- Dynamically adjusts to use maximum available solar power
- Aims for ~0W net grid consumption (no import, minimal export)
- Responds to production changes every 30 seconds

### Smart Stopping
- Doesn't stop prematurely when production dips briefly
- Only stops when truly insufficient (at minimum 6A for 3+ minutes)
- Allows brief grid import (up to buffer threshold)

## Monitoring

### Dashboard Sensors to Watch

**Charging Status:**
- `sensor.garage_status`: Current charger state
- `sensor.garage_dynamic_charger_limit`: Current charging amps

**PV Data:**
- `sensor.verfugbarer_pv_uberschuss`: Available PV surplus
- `sensor.pv_target_charging_current`: Calculated target current
- `sensor.net_grid_consumption`: Net grid import/export

**Timers:**
- `input_number.pv_start_condition_timer`: Seconds until start (0-180)
- `input_number.pv_stop_condition_timer`: Seconds until stop (0-180)

### Logs

Check **Settings → System → Logs** for:

```
PV Smart: surplus=1500W, target_current=8A, net=-50W, is_charging=False, start_timer=90s, stop_timer=0s
PV Smart: Starting with 8A (surplus stable for 180s)
PV Smart: Adjusting from 8A to 12A (net: -100W)
PV Smart: Stopping (at minimum 6A, consuming 250W from grid for 180s)
```

### Notifications

You'll receive:
- **"☀️ PV-Laden gestartet"** when charging starts (with surplus, current, net consumption)
- **"⏸️ PV-Laden gestoppt"** when charging stops (with reason)

## Troubleshooting

### Charging Not Starting

**Check:**
1. Is `sensor.verfugbarer_pv_uberschuss` ≥ 1400W?
2. Is `input_number.pv_start_condition_timer` incrementing?
3. Is `sensor.pv_target_charging_current` ≥ 6A?
4. Is `input_boolean.enable_pv_charging` ON?
5. Check logs for "PV Smart:" entries

**If timer keeps resetting:**
- Surplus is fluctuating around threshold
- Increase `min_pv_surplus` or lower it depending on your needs
- Check if production sensor is stable

### Charging Stops Too Soon

**Solution:** Increase `pv_charging_buffer`
- Default: 200W
- Try: 300-400W
- This allows more grid consumption before stopping

### Charging Starts Too Late

**Solution:** Decrease `min_pv_surplus`
- Default: 1400W
- Try: 1200W
- Ensure target current calculation still gives ≥ 6A

### Current Not Adjusting Smoothly

**Check:**
1. `sensor.pv_target_charging_current` - is it calculating correctly?
2. Tibber sensors (`sensor.tibber_power`, `sensor.tibber_power_production`) - are they updating?
3. `sensor.garage_power` - is it reporting correctly?

## Comparison: Old vs New Algorithm

| Feature | Old (Hysteresis) | New (Smart) |
|---------|------------------|-------------|
| **Start Threshold** | 1400W (immediate) | 1400W for 3 minutes |
| **Stop Threshold** | 1200W (immediate) | 6A + consuming > 200W for 3 minutes |
| **Current Adjustment** | Fixed calculation | Dynamic to achieve ~0W net |
| **Trigger Frequency** | Every 2 minutes | Every 30 seconds |
| **Stability** | Moderate (200W buffer) | High (3-minute timers) |
| **Responsiveness** | Moderate | High (30s adjustments) |
| **Prevents Cycling** | Partially | Fully |
| **Grid Consumption** | Varies | Minimized (~0W target) |

## Activation

**Reload scripts and automations:**
```
Developer Tools → YAML → Scripts → Reload
Developer Tools → YAML → Automations → Reload
```

Or restart Home Assistant.

The new algorithm will activate immediately when PV charging is enabled.

## Summary

The new smart PV charging algorithm provides:
- ✅ 3-minute stability timers (no rapid cycling)
- ✅ Dynamic current adjustment every 30 seconds
- ✅ Target ~0W net grid consumption
- ✅ Smart stopping only when truly insufficient
- ✅ Comprehensive logging and notifications
- ✅ More efficient use of solar surplus
- ✅ Less wear on equipment

**Result:** Stable, efficient, and intelligent solar surplus charging.
