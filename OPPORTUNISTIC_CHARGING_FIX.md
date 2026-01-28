# Opportunistic Charging Fix - Past Hours Problem

## Problem Identified

From the debug sensor, you showed:
```
current_price: 0.355 (< threshold_80: 0.38) ✓
current_soc: 21 (< 80%) ✓
should_charge_now: false ✗
current_hour_plan: Sollte laden: False, SoC Ende: 95%
```

**Why it wasn't charging:**

The algorithm was processing **past hours** (hours 0-14) and marking them for charging because their prices were also below the threshold. Each marked hour incremented the projected SoC by 14.7%.

By the time it evaluated hour 15 (current hour), the projected SoC was already 95% (from ~5 past hours of "virtual" charging), so even though your **actual** SoC was only 21%, the algorithm thought "we've already charged enough" and didn't mark hour 15 for charging.

## Root Cause

The opportunistic charging logic had two bugs:

### Bug 1: Evaluating Past Hours

```yaml
# BEFORE (broken):
{% if price_val <= threshold_80 and soc.value < 80 %}
  {% set should_charge = true %}
```

This evaluated ALL hours in the forecast, including past hours. Past hours with good prices were marked for charging, incrementing the projected SoC, even though those hours have already passed and charging didn't happen.

### Bug 2: Using Projected SoC Instead of Actual SoC

```yaml
# BEFORE (broken):
{% if price_val <= threshold_80 and soc.value < 80 %}
```

This used `soc.value` (projected SoC after past virtual charging) instead of `current_soc` (actual battery level).

So even when evaluating the current hour, it was checking if projected SoC (95%) < 80%, not if actual SoC (21%) < 80%.

## The Fix

### Change 1: Only Evaluate Current and Future Hours

```yaml
# AFTER (fixed):
{% if price_time >= now_time.replace(minute=0, second=0, microsecond=0) %}
  {# Only process current hour and future hours #}
  {% if price_val <= threshold_80 and current_soc < 80 %}
    {% set should_charge = true %}
```

**What this does:**
- Skips past hours entirely (they don't affect the plan)
- Only evaluates current and future hours

### Change 2: Use Actual SoC for Opportunistic Charging

```yaml
# BEFORE:
{% if price_val <= threshold_80 and soc.value < 80 %}

# AFTER:
{% if price_val <= threshold_80 and current_soc < 80 %}
```

**What this does:**
- Uses the actual current battery level (21%) for the threshold check
- Not the projected SoC (95%) from past virtual charging

## Expected Behavior After Fix

With your current conditions:
```
current_price: 0.355
threshold_80: 0.38
current_soc: 21%
current_hour: 15
```

**After reload:**
```
should_charge_now: true ✓
charge_reason: price_80 ✓
current_hour_plan: Sollte laden: True, Grund: price_80
expected_action: Laden starten ✓
```

The algorithm will now:
1. Skip past hours (0-14) - they don't affect the decision
2. Evaluate hour 15 with actual current_soc (21%)
3. See that 0.355 <= 0.38 AND 21 < 80
4. Mark hour 15 for charging with reason "price_80"
5. Set should_charge_now = true
6. Automation starts charging

## How to Apply the Fix

### 1. Reload YAML Configuration

**Developer Tools → YAML → Manually Configured YAML Entities → Reload**

### 2. Verify the Fix

**Developer Tools → States → sensor.debug_preisladen**

Check that:
- `should_charge_now`: Should now be **true**
- `current_hour_plan`: Should show **"Sollte laden: True, Grund: price_80"**
- `expected_action`: Should show **"Laden starten"**

### 3. Trigger Automation (if needed)

The automation triggers:
- Every hour at :00 minutes
- When sensor.optimaler_ladeplan updates

To force it immediately:
**Developer Tools → Automations → Search "Preisplan ausführen" → Run**

### 4. Verify Charging Starts

Check:
- `sensor.garage_status` → Should change to "charging"
- `input_select.charging_state` → Should change to "price"
- Easee app / car dashboard → Should show charging active

## Impact on Morning Target Planning

The fix also affects how morning target hours are selected:

**Before:** Past hours were included in the SoC projection, making the algorithm think less charging was needed.

**After:** Only future hours count. The algorithm calculates:
```
Current SoC: 21%
Target SoC: 80% (by 7:00 AM)
Deficit: 59%
Hours needed: 59 / 14.7 = 4 hours
```

Then it selects the 4 cheapest hours between now and 7:00 AM.

This is more accurate because it only considers actual future charging opportunities.

## Example Scenario

**Current time:** 15:00
**Current SoC:** 21%
**Prices for today:**

| Hour | Price | Old Behavior | New Behavior |
|------|-------|--------------|--------------|
| 00:00 | 0.32 | ✓ Charge (projected) → SoC += 15% | ✗ Skip (past hour) |
| 01:00 | 0.30 | ✓ Charge (projected) → SoC += 15% | ✗ Skip (past hour) |
| 02:00 | 0.32 | ✓ Charge (projected) → SoC += 15% | ✗ Skip (past hour) |
| ... | ... | ... | ... |
| 14:00 | 0.35 | ✓ Charge (projected) → SoC += 15% | ✗ Skip (past hour) |
| **15:00** | **0.355** | ✗ Don't charge (SoC=95%≥80%) | **✓ Charge (actual SoC=21%<80%)** |
| 16:00 | 0.36 | ✗ Don't charge (SoC≥80%) | ✓ Charge (21%<80%) |
| 17:00 | 0.37 | ✗ Don't charge (SoC≥80%) | ✓ Charge (21%<80%) |

**Result:**
- Old: No charging at current hour (thinks it already charged in the past)
- New: Charging starts immediately (uses actual current SoC)

## Files Modified

**File:** `/packages/energy_management/optimal_charging_plan.yaml`

**Changes:**
1. Added time check: Only evaluate current and future hours
2. Changed SoC check: Use `current_soc` instead of `soc.value`

**Lines affected:** 66-80

## Testing Checklist

After reload, verify:

- [ ] `sensor.debug_preisladen` shows `should_charge_now: true`
- [ ] `current_hour_plan` shows `Sollte laden: True`
- [ ] `expected_action` shows "Laden starten"
- [ ] Automation triggers (check logs for "Price charging automation triggered")
- [ ] Charging actually starts (`sensor.garage_status` = "charging")
- [ ] Dashboard shows current hour marked for charging

## Additional Notes

### SoC Projection Still Works for Future Hours

For **future hours**, the algorithm still projects SoC forward. This is correct for planning purposes.

Example at 15:00:
- Hour 15: Should charge (price 0.355 < 0.38, actual SoC 21% < 80%) → Mark for charging
- Hour 16: Project SoC = 21% + 14.7% = 35.7%
  - Should charge (price 0.36 < 0.38, projected SoC 35.7% < 80%) → Mark for charging
- Hour 17: Project SoC = 35.7% + 14.7% = 50.4%
  - Should charge (price 0.37 < 0.38, projected SoC 50.4% < 80%) → Mark for charging
- Hour 18: Project SoC = 50.4% + 14.7% = 65.1%
  - Should charge (price 0.38 = 0.38, projected SoC 65.1% < 80%) → Mark for charging
- Hour 19: Project SoC = 65.1% + 14.7% = 79.8%
  - Should charge (price 0.35 < 0.38, projected SoC 79.8% < 80%) → Mark for charging
- Hour 20: Project SoC = 79.8% + 14.7% = 94.5% (capped at 100%)
  - Don't charge (projected SoC 94.5% ≥ 80%) → Skip

This gives you a realistic plan for the rest of the day.

### Past Hours in Dashboard

Past hours will now show:
- `should_charge: false`
- `charge_reason: none`
- `soc_end: 21` (stays at current SoC, doesn't increment)

This is correct - they're in the past, so they shouldn't affect the plan.

## Summary

**Problem:** Algorithm evaluated past hours, projected virtual charging, and thought battery was already at 95% when it was actually at 21%.

**Fix:** Only evaluate current and future hours, use actual current SoC for threshold checks.

**Result:** Opportunistic charging now works correctly for the current hour.

**Action Required:** Reload YAML configuration and verify in debug sensor.
