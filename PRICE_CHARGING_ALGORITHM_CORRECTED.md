# Price Charging Algorithm - Corrected Logic

## The Issue

The previous fix was **wrong**. I changed it to use `current_soc` for all hours, which meant:
- Every hour checked: "Is 21% < 80%?" → YES → Charge forever
- SoC projection didn't work

## The Correct Fix

**Only skip past hours, but use projected SoC for threshold checks.**

```yaml
# For each hour in price forecast:
{% if price_time >= now_time.replace(minute=0, second=0, microsecond=0) %}
  # Only process current and future hours

  # Check thresholds using PROJECTED soc.value (not current_soc!)
  {% if price_val <= threshold_80 and soc.value < 80 %}
    {% set should_charge = true %}
    {% set charge_reason = 'price_80' %}
  {% elif price_val <= threshold_50 and soc.value < 50 %}
    {% set should_charge = true %}
    {% set charge_reason = 'price_50' %}
  {% endif %}
{% endif %}

# If charging this hour, increment projected SoC
{% if should_charge %}
  {% set soc.value = soc.value + soc_per_hour %}  # 14.7% per hour
{% endif %}
```

## Expected Behavior with Your Current Settings

**Configuration:**
- threshold_80: 0.38 (charge to 80% when price ≤ 0.38€)
- threshold_50: 0.4 (charge to 50% when price ≤ 0.40€)
- Current SoC: 21%
- Charging: 3-phase 16A = 11.04 kW = 14.7% per hour

**Hour-by-hour calculation:**

| Hour | Price | Start SoC | Check | Decision | End SoC |
|------|-------|-----------|-------|----------|---------|
| 16:00 | 0.362 | 21% | 0.362 ≤ 0.38 AND 21% < 80% | ✓ Charge (price_80) | 36% |
| 17:00 | 0.382 | 36% | 0.382 ≤ 0.4 AND 36% < 50% | ✓ Charge (price_50) | 51% |
| 18:00 | 0.39 | 51% | 0.39 ≤ 0.4 BUT 51% ≥ 50% | ✗ Don't charge | 51% |
| 19:00 | 0.379 | 51% | 0.379 ≤ 0.38 AND 51% < 80% | ✓ Charge (price_80) | 66% |
| 20:00 | 0.346 | 66% | 0.346 ≤ 0.38 AND 66% < 80% | ✓ Charge (price_80) | 80% |
| 21:00 | 0.328 | 80% | 0.328 ≤ 0.38 BUT 80% ≥ 80% | ✗ Don't charge | 80% |
| 22:00 | 0.326 | 80% | 0.326 ≤ 0.38 BUT 80% ≥ 80% | ✗ Don't charge | 80% |
| 23:00 | 0.319 | 80% | 0.319 ≤ 0.38 BUT 80% ≥ 80% | ✗ Don't charge | 80% |
| ... | ... | ... | ... | ... | ... |

**Expected charging hours:** 16, 17, 19, 20 (then stops at 80%)

**Expected non-charging hours:** 18 (over 50%), 21+ (at 80%)

## Why It Stops

### Threshold_50 (0.4€)
- Charges **up to 50%** when price ≤ 0.40€
- Stops when SoC reaches 50%
- Example: Hour 18 has price 0.39€ (≤ 0.4), but SoC is already 51%, so **doesn't charge**

### Threshold_80 (0.38€)
- Charges **up to 80%** when price ≤ 0.38€
- Stops when SoC reaches 80%
- Example: Hour 21 has price 0.328€ (≤ 0.38), but SoC is already 80%, so **doesn't charge**

## How the Automation Works

### Every Hour (or when plan updates):

1. **Calculate plan** - `sensor.optimaler_ladeplan` determines which hours should charge
2. **Check current hour** - `should_charge_now` attribute checks if current hour is marked
3. **Automation triggers** - If `should_charge_now` is true, start charging
4. **Next hour** - If `should_charge_now` becomes false, stop charging

### Real-time behavior:

**16:00** - Plan says charge → Automation starts charging → Actual charging begins
**17:00** - Plan says charge → Charging continues
**18:00** - Plan says DON'T charge → Automation stops charging
**19:00** - Plan says charge → Automation starts charging again
**20:00** - Plan says charge → Charging continues
**21:00** - Plan says DON'T charge → Automation stops charging (reached 80%)
**21:00+** - Stays off until next day (when SoC drops or prices get cheaper)

## Troubleshooting the 50% Jump Issue

You reported SoC showing 50% after first hour instead of 36%. Possible causes:

### 1. **Dashboard Not Refreshed**

The dashboard might be showing cached data from before the fix.

**Solution:**
- Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)
- Or check Developer Tools → States → sensor.optimaler_ladeplan directly

### 2. **Sensor Not Reloaded**

The sensor still has the old (broken) logic.

**Solution:**
- Developer Tools → YAML → Manually Configured YAML Entities → Reload
- Wait 30 seconds for sensor to recalculate
- Check again

### 3. **Using Different Charging Current**

If somehow the system is using 32A instead of 16A:
- 32A = 22.08 kW = 29.4% per hour
- 21% + 29.4% = 50.4% ✓ (matches what you see!)

**Check:**
- Look at logs during price charging: "Price charging started"
- Check `sensor.garage_dynamic_charger_limit` - should be 16
- Check Easee app - what current is it using?

### 4. **Central Controller Overriding**

The central controller might be setting a different current.

**Check:**
- What is `input_select.charging_state`? Should be "price"
- If it's something else, another mode is active

## Expected Fix Outcome

After reloading:

**Plan should show:**
```
16:00  0.362€  🟢  36%   Sehr günstig (price_80)
17:00  0.382€  🟢  51%   Günstig (price_50)
18:00  0.39€   ⚪  51%   - (no charging)
19:00  0.379€  🟢  66%   Sehr günstig (price_80)
20:00  0.346€  🟢  80%   Sehr günstig (price_80)
21:00  0.328€  ⚪  80%   - (no charging)
22:00  0.326€  ⚪  80%   - (no charging)
...
```

**Key differences:**
- ✓ SoC increments by ~15% per hour (not 29%)
- ✓ Stops at hour 18 (reached 50% limit for threshold_50)
- ✓ Resumes at hour 19 (cheaper price allows threshold_80)
- ✓ Stops at hour 21 (reached 80% limit for threshold_80)
- ✓ Stays off after hour 21

## What to Do Now

### 1. Reload YAML

**Developer Tools → YAML → Manually Configured YAML Entities → Reload**

### 2. Wait for Sensor Update

The sensor should recalculate within 30 seconds.

### 3. Check the Plan

**Developer Tools → States → sensor.optimaler_ladeplan**

Look at the `charging_plan` attribute. Find hours 16-23 and verify:
- Hour 16: should_charge = true, soc_end = 36 (not 50!)
- Hour 18: should_charge = false, soc_end = 51
- Hour 21: should_charge = false, soc_end = 80

### 4. Check Debug Sensor

**Developer Tools → States → sensor.debug_preisladen**

- `current_hour_plan`: Should show the correct decision for current hour
- `should_charge_now`: Should be true/false based on current hour in plan

### 5. Force Recalculation (if needed)

**Developer Tools → Developer Tools → Services**

Service: `homeassistant.update_entity`
Entity: `sensor.optimaler_ladeplan`

Click "Call Service"

### 6. Monitor Actual Charging

Once charging starts:
- Check `sensor.garage_dynamic_charger_limit` - should be 16
- Monitor `sensor.tesla_ladestand` - should increase by ~15% per hour
- Check at hour 21 - should stop automatically when reaching 80%

## If Still Not Working

### Check Actual Charging Current

**Developer Tools → States → sensor.garage_dynamic_charger_limit**

If it shows 32 instead of 16:
- Something is overriding the current setting
- Check central controller logic
- Check if manual mode or another mode is interfering

### Check Automation Execution

**Settings → System → Logs**

Search for: "Price charging automation triggered"

Should see entries like:
```
Price charging automation triggered: should_charge=true, hour=16
Price charging: No action needed (already charging), hour=17
Price charging automation triggered: should_charge=false, hour=18
```

If you see "should_charge=true" for ALL hours, the sensor fix hasn't been applied yet.

## Summary

**Previous bug:** Used `current_soc` (21%) for all hours → everything charged forever

**Correct logic:** Use `soc.value` (projected SoC) which starts at 21% and increments:
- Hour 16: 21% → charge → 36%
- Hour 17: 36% → charge → 51%
- Hour 18: 51% → don't charge → stays 51%
- Hour 19: 51% → charge → 66%
- Hour 20: 66% → charge → 80%
- Hour 21: 80% → don't charge → stays 80%

**Action:** Reload YAML, verify in Developer Tools that plan shows correct SoC progression.
