# Automated Charging Troubleshooting Guide

## Problem: Car didn't charge at planned time

If your car was supposed to charge at 3:00 AM but didn't, follow these diagnostic steps.

## Files Updated

1. **`optimal_charging_plan.yaml`** - Fixed `should_charge_now` to always return a proper boolean
2. **`price_charging_automation.yaml`** - Added detailed logging
3. **`charging_debug.yaml`** - NEW debug sensor to check all conditions

## Step 1: Check the Debug Sensor

After reloading template entities, check the new debug sensor:

**Developer Tools → States → `sensor.debug_preisladen_bedingungen`**

This sensor shows ALL the conditions and their current state:

### Attributes to Check:

```yaml
state: "Alle OK" or "Bedingung fehlt"

Attributes:
  enable_price_charging: "on" or "off"  # Must be "on"
  auto_angeschlossen: "on" or "off"     # Must be "on" (car connected)
  manual_mode: "off" or "on"            # Must be "off"
  emergency_mode: "off" or "on"         # Must be "off"

  should_charge_now: true or false      # Should be true at planned hour
  current_hour: 3                       # Current hour

  charging_hours: [3, 4, 5]            # Hours when charging is planned

  garage_status: "ready" or "charging"
  is_charging: true or false

  last_automation_run: "2026-01-26 03:00:00"  # When automation last ran
```

### Common Issues:

#### Issue 1: `enable_price_charging` is "off"
**Solution:** Turn on price-based charging
```
Settings → Devices → Input Boolean → Enable Price Charging → ON
```

#### Issue 2: `auto_angeschlossen` is "off"
**Problem:** Car not detected as connected
**Check:**
- Is car physically plugged in?
- Check `binary_sensor.garage_cable_lock` state
- Check `binary_sensor.auto_angeschlossen` state

#### Issue 3: `manual_mode` is "on"
**Solution:** Turn off manual mode
```
Settings → Devices → Input Boolean → Manual Charging Mode → OFF
```

#### Issue 4: `should_charge_now` is false at 3:00 AM
**Problem:** Charging plan didn't calculate correctly
**Check:**
- Look at `charging_hours` - does it include hour 3?
- Check `sensor.optimaler_ladeplan` attributes → `charging_plan`
- Look for hour 3 in the plan - is `should_charge: true`?

## Step 2: Check Home Assistant Logs

**Settings → System → Logs**

Look for these log entries around the planned charging time (e.g., 03:00):

### A. Automation Triggered?
```
Price charging automation triggered:
should_charge=True, is_charging=False, soc=45%, hour=3
```

If you DON'T see this:
- Automation didn't run → Check conditions in Step 1
- Check if automation is enabled: Settings → Automations → "Energie Management - Preisplan ausführen"

### B. Charging Started?
```
Price charging started: SoC=45%
```

If you see "automation triggered" but NOT "charging started":
- Check the full log message
- Look for errors from Easee service calls
- Check if `is_charging` was already true

### C. No Action Taken?
```
Price charging: No action needed.
should_charge=False, is_charging=False, charging_state=idle
```

This means the automation ran but `should_charge` was false.

## Step 3: Verify the Charging Plan

**Developer Tools → States → `sensor.optimaler_ladeplan`**

Check the `charging_plan` attribute. Find the entry for 03:00 (hour: 3):

```yaml
{
  "hour": 3,
  "time": "03:00",
  "datetime": "2026-01-26T03:00:00+01:00",
  "price": 0.245,
  "should_charge": true,      # ← Should be true!
  "charge_reason": "morning_target",
  "soc_end": 65
}
```

### If `should_charge: false`:

Possible reasons:
1. **Current SoC already meets target** - If you're at 65% and target is 65%, no charging needed
2. **Price too high** - Not in selected cheapest hours
3. **Calculation error** - Check `hours_needed` attribute

### If hour 3 is missing from plan:

- Check `sensor.tibber_preisprognose` has price data
- Run `script.calculate_optimal_charging_hours` manually
- Check logs for template errors

## Step 4: Test the Automation Manually

**Developer Tools → Services**

Call the automation service manually:

```yaml
service: automation.trigger
target:
  entity_id: automation.energie_management_preisplan_ausfuhren
```

Then check logs immediately to see what happened.

## Step 5: Force Update the Plan

If the plan seems stale:

**Developer Tools → Services**

```yaml
service: homeassistant.update_entity
target:
  entity_id: sensor.optimaler_ladeplan
```

This forces recalculation based on current SoC and time.

## Step 6: Check Easee Charger

The automation calls these Easee services:

1. `easee.set_charger_phase_mode` (3-phase)
2. `easee.set_charger_dynamic_limit` (16A)
3. `easee.action_command` (start)

Check if Easee integration is working:

**Settings → Devices & Services → Easee**

- Device should be "Online"
- Check device ID matches: `4997305f9b10ff58595e095f3bdf74cd`

Test manually:

**Developer Tools → Services**

```yaml
service: easee.action_command
data:
  device_id: 4997305f9b10ff58595e095f3bdf74cd
  action_command: start
```

## Step 7: Enable Debug Logging

For more detailed logs, enable debug logging:

**configuration.yaml**:
```yaml
logger:
  default: info
  logs:
    homeassistant.components.template: debug
    homeassistant.components.automation: debug
```

Restart Home Assistant, then check logs again.

## Quick Fix Checklist

Run through this checklist:

- [ ] Reload template entities: Developer Tools → YAML → Template Entities → Reload
- [ ] Reload automations: Developer Tools → YAML → Automations → Reload
- [ ] Check `sensor.debug_preisladen_bedingungen` state is "Alle OK"
- [ ] Check `sensor.optimaler_ladeplan` → `should_charge_now` at current hour
- [ ] Verify car is connected: `binary_sensor.auto_angeschlossen` = "on"
- [ ] Verify price charging enabled: `input_boolean.enable_price_charging` = "on"
- [ ] Verify manual mode off: `input_boolean.manual_charging_mode` = "off"
- [ ] Check automation is enabled and not disabled
- [ ] Check Home Assistant logs for errors
- [ ] Run `script.calculate_optimal_charging_hours` to refresh price data

## Common Root Causes

### 1. Tesla Integration Issues
If `sensor.tesla_ladestand` shows "unavailable":
- Tesla vehicle is asleep
- Tesla Fleet API quota exceeded
- Authentication expired

**Solution:** Wake the car or wait for it to wake naturally

### 2. Tibber Price Data Stale
If prices aren't updating:
- Tibber integration issue
- API rate limit
- Network connectivity

**Solution:** Check Tibber integration status

### 3. Time Zone Issues
If charging happens at wrong hour:
- Check Home Assistant time zone setting
- Check if system clock is correct

**Solution:** Settings → System → General → Time Zone

### 4. Automation Disabled
**Solution:** Settings → Automations → Enable "Energie Management - Preisplan ausführen"

## After Fixing

Once you've identified and fixed the issue:

1. **Reload everything:**
   - Template entities
   - Automations

2. **Verify next planned hour:**
   - Check `sensor.optimaler_ladeplan` → `next_charging_hour`
   - Wait for that hour and monitor logs

3. **Set a test:**
   - Temporarily set a very high price threshold (e.g., 0.50 EUR)
   - This should make current hour "very cheap"
   - Automation should start charging within minutes

4. **Monitor for one full day** to confirm it works reliably

## Getting Help

If still not working, collect this info:

1. Screenshot of `sensor.debug_preisladen_bedingungen` attributes
2. `sensor.optimaler_ladeplan` → `charging_plan` attribute (full JSON)
3. Home Assistant logs from the hour when it should have charged
4. Automation trace: Settings → Automations → "Energie Management - Preisplan ausführen" → Traces

With this info, you can diagnose the exact point of failure.
