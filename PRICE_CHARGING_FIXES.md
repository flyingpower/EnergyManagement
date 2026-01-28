# Price-Based Charging Fixes

## Issues Found

### 1. SoC Always Showing 80% in Charging Plan

**Problem:** The hourly SoC in the Ladeplan was stuck at 80% for all hours.

**Root Cause:** Line 81 in `optimal_charging_plan.yaml` was capping SoC at 80%:

```yaml
# BEFORE (broken):
{% set soc.value = [soc.value + soc_per_hour, 80] | min %}

# AFTER (fixed):
{% set soc.value = [soc.value + soc_per_hour, 100] | min %}
```

**Why this was wrong:** The SoC calculation should go up to 100%, not 80%. The 80% limit is for the **charging threshold** (only charge if SoC < 80% when price ≤ threshold_80), not for the SoC display.

**Fix Applied:** Changed cap from 80 to 100.

### 2. Charging Not Starting Despite Price Below Threshold

**Problem:** `price_threshold_80` set to 0.38, current price below that, but charging not starting.

**Possible Causes:**

There are several automation conditions that must ALL be met:

1. ✅ `input_boolean.enable_price_charging` = ON
2. ✅ `binary_sensor.auto_angeschlossen` = ON (car connected)
3. ✅ `input_boolean.manual_charging_mode` = OFF
4. ✅ `binary_sensor.notladung_erforderlich` = OFF (not in emergency mode)
5. ✅ Current price ≤ threshold_80
6. ✅ Current SoC < 80%

**Additionally:**
- Sensor `sensor.optimaler_ladeplan` must calculate correctly
- Attribute `should_charge_now` must be `true`
- Automation must actually trigger (every hour at :00 or when sensor updates)

## Debug Tools Added

### New Debug Sensor: `sensor.debug_preisladen`

I've created a comprehensive debug sensor that shows:

**State:** "Sollte laden" or "Sollte nicht laden"

**Attributes:**
- `current_price`: Current electricity price
- `current_soc`: Current battery level
- `threshold_80`: Price threshold for 80% charging
- `threshold_50`: Price threshold for 50% charging
- `should_charge_now`: What the sensor calculates
- `enable_price_charging`: Is price charging enabled?
- `auto_angeschlossen`: Is car connected?
- `manual_charging_mode`: Is manual mode active?
- `notladung_erforderlich`: Is emergency charging active?
- `garage_status`: Charger status
- `charging_state`: Current charging mode
- `all_conditions_met`: Are all automation conditions met?
- `expected_action`: What should happen right now?
- `current_hour_plan`: Details for the current hour

**File:** `/packages/energy_management/price_charging_debug.yaml`

## How to Debug Price Charging

### Step 1: Reload YAML Configuration

**Developer Tools → YAML → Manually Configured YAML Entities → Reload**

This will:
- Apply the SoC cap fix (80% → 100%)
- Load the new debug sensor

### Step 2: Check Debug Sensor

**Developer Tools → States → Search for "debug_preisladen"**

or add it to your dashboard:

```yaml
type: entities
entities:
  - entity: sensor.debug_preisladen
```

Click on it to see all attributes.

### Step 3: Verify Each Condition

Check the debug sensor attributes:

**Expected values for charging to start:**
```
current_price: 0.32 (or whatever it is)
threshold_80: 0.38
current_soc: 65 (must be < 80)
should_charge_now: true
all_conditions_met: "Ja"
expected_action: "Laden starten"
```

**If `all_conditions_met` shows "Nein":**

It will tell you why:
- "Preisladen aus" → Turn on `input_boolean.enable_price_charging`
- "Auto nicht angeschlossen" → Car not plugged in
- "Manueller Modus aktiv" → Turn off `input_boolean.manual_charging_mode`
- "Notladung aktiv" → Emergency charging is active (SoC < 20%)

**If `should_charge_now` is `false`:**

Check `current_hour_plan` to see why:
```
Preis: 0.32€, Sollte laden: false, Grund: none, SoC Ende: 65%
```

Possible reasons:
- Current SoC already ≥ 80% (won't charge with threshold_80)
- Current SoC already ≥ 50% (won't charge with threshold_50)
- Price > threshold_80 AND price > threshold_50
- Not in a selected optimal hour for morning target

### Step 4: Check Automation Execution

**Settings → System → Logs**

Search for "Price charging automation triggered"

Should see:
```
Price charging automation triggered:
should_charge=true,
is_charging=False,
soc=65%,
hour=13
```

**If you DON'T see this:**
- Automation conditions are blocking (check debug sensor's `all_conditions_met`)
- Automation not triggering (check if sensor is updating)

### Step 5: Force Trigger (Testing)

**Developer Tools → Automations → Search "Preisplan ausführen" → Run**

This manually triggers the automation. Check logs to see what happens.

### Step 6: Check Charging Plan

**Developer Tools → States → sensor.optimaler_ladeplan**

Click on it and look at the `charging_plan` attribute. Find the current hour:

```json
{
  "hour": 13,
  "time": "13:00",
  "price": 0.32,
  "should_charge": true,
  "charge_reason": "price_80",
  "soc_end": 80
}
```

**What to check:**
- `should_charge`: Must be `true`
- `charge_reason`: Should be "price_80", "price_50", or "morning_target"
- `soc_end`: Should increment through hours (no longer stuck at 80%)

## Common Issues and Solutions

### Issue: "Charging plan shows all hours with SoC = 80%"

**Solution:** Reload YAML configuration (Step 1 above). The fix has been applied.

### Issue: "should_charge_now is false even though price < threshold"

**Possible causes:**

1. **Current SoC already at target:**
   - If SoC = 80% and price ≤ threshold_80: Won't charge (already at 80%)
   - If SoC = 50% and price ≤ threshold_50: Won't charge (already at 50%)
   - **Solution:** This is correct behavior

2. **Sensor not updating:**
   - Check if `sensor.optimaler_ladeplan` state changes
   - Should update when `sensor.tibber_preisprognose` updates
   - **Solution:** Reload sensor or restart HA

3. **Price sensor not working:**
   - Check `sensor.aktueller_strompreis`
   - Check `sensor.tibber_preisprognose`
   - **Solution:** Fix Tibber integration

### Issue: "All conditions met, should_charge is true, but not charging"

**Check:**

1. **Automation actually running?**
   - Look for "Price charging automation triggered" in logs
   - If not there: Conditions are blocking (check debug sensor)

2. **Easee commands failing?**
   - Look for Easee errors in logs
   - Check if charger is online

3. **Wrong charging_state?**
   - Check `input_select.charging_state`
   - Should switch to "price" when starting
   - Might be stuck in another mode

4. **Central controller overriding?**
   - The central controller runs every 1 minute
   - Might switch to different mode based on priority
   - Check if PV charging, manual mode, or emergency mode is active

### Issue: "Charges but stops immediately"

**Possible causes:**

1. **Central controller switching modes:**
   - PV charging has priority 3, price charging has priority 4
   - If PV surplus exists, it might switch to PV mode
   - **Solution:** Check `sensor.verfugbarer_pv_uberschuss`

2. **Plan updates and changes decision:**
   - Sensor updates → automation triggers → should_charge becomes false
   - **Solution:** Check if `charging_plan` is stable

## Testing Checklist

Use this checklist to verify price charging is working:

### Prerequisites
- [ ] Car connected (`binary_sensor.auto_angeschlossen` = ON)
- [ ] Price charging enabled (`input_boolean.enable_price_charging` = ON)
- [ ] Manual mode off (`input_boolean.manual_charging_mode` = OFF)
- [ ] No emergency charging (`binary_sensor.notladung_erforderlich` = OFF)
- [ ] SoC < 80% (for threshold_80 testing)

### Sensor Checks
- [ ] `sensor.tibber_preisprognose` has data
- [ ] `sensor.aktueller_strompreis` shows current price
- [ ] `sensor.optimaler_ladeplan` has `charging_plan` attribute
- [ ] `sensor.debug_preisladen` shows "all_conditions_met: Ja"

### Plan Verification
- [ ] `charging_plan` shows different SoC values (not all 80%)
- [ ] Current hour in plan has `should_charge: true`
- [ ] `charge_reason` is "price_80", "price_50", or "morning_target"
- [ ] `should_charge_now` attribute is `true`

### Automation Verification
- [ ] Automation "Preisplan ausführen" exists and is enabled
- [ ] Logs show "Price charging automation triggered"
- [ ] Logs show decision: "should_charge=true"
- [ ] Easee commands executed (phase_mode, dynamic_limit, start)

### Charging Verification
- [ ] `sensor.garage_status` changes to "charging"
- [ ] `input_select.charging_state` changes to "price"
- [ ] Charger actually starts (check Easee app or car)

### Dashboard Verification
- [ ] Ladeplan table shows varying SoC through hours
- [ ] Current hour highlighted/marked for charging
- [ ] Charge reason displayed correctly

## Files Modified

1. **`optimal_charging_plan.yaml`** - Fixed SoC cap (80 → 100)
2. **`price_charging_debug.yaml`** - NEW debug sensor

Both files copied to:
- `/Volumes/config/packages/energy_management/`
- `/Users/mif7fe/Documents/Projects/EnergyManagement/packages/energy_management/`

## Next Steps

1. **Reload YAML configuration** (Developer Tools → YAML → Reload Manually Configured)
2. **Check debug sensor** (Developer Tools → States → debug_preisladen)
3. **Verify plan** (Developer Tools → States → optimaler_ladeplan)
4. **Monitor logs** (Settings → System → Logs, search "Price charging")
5. **Report findings** - Tell me what the debug sensor shows!
