# Price Charging Fixes - Threshold Stopping & Morning Target

## Issues Fixed

### 1. Hour 21 Charging Despite Being at 80%

**Problem:** After hour 20 reached 80%, hour 21 still charged to 95%.

**Root Cause:** Floating point precision. After hour 20, SoC was actually 79.7% (displayed as 80%). Hour 21 checked `79.7 < 80` → TRUE → charged.

**Fix:** Round SoC before comparing:
```yaml
# BEFORE:
{% if price_val <= threshold_80 and soc.value < 80 %}

# AFTER:
{% if price_val <= threshold_80 and soc.value | round(0) < 80 %}
```

Now: `79.7 | round(0) = 80`, and `80 < 80` = FALSE → Won't charge ✓

### 2. Morning Target Charging Even Though Already at 95%

**Problem:** Hours 0-4 tomorrow marked as "morning_target" even though opportunistic charging already brought SoC to 95% by midnight.

**Root Cause:** Morning target hours were selected at the start (based on 21% current SoC), before calculating opportunistic charging. The algorithm didn't re-check if those hours were still needed after opportunistic charging.

**How it worked before:**
1. Calculate: Need 5 hours to go from 21% to 80% → Select cheapest 5 hours (0-4)
2. Loop through hours: Hour 0 is in selected_hours → Charge (even though SoC is already 95%)

**Fix:** Add additional check when processing morning_target hours:
```yaml
# BEFORE:
{% elif price_hour in selected_hour_nums and price_time > now_time and price_time <= target_time %}

# AFTER:
{% elif price_hour in selected_hour_nums and price_time > now_time and price_time <= target_time and soc.value | round(0) < target_soc %}
```

Now even if an hour is "selected", it won't charge if SoC is already at or above target.

### 3. Dashboard Not Showing Current Hour

**Problem:** At 16:30, the dashboard starts with hour 17:00 instead of showing 16:00.

**Root Cause:** Dashboard filter `{% if hour_time >= now_time %}` compares:
- hour_time: 16:00:00
- now_time: 16:30:00
- Result: 16:00:00 < 16:30:00 → Hour 16 filtered out

**Fix:** Compare against hour floor:
```yaml
# BEFORE:
{%- if hour_time >= now_time -%}

# AFTER:
{%- if hour_time >= now_time.replace(minute=0, second=0, microsecond=0) -%}
```

Now at 16:30:
- now_time.replace(...) = 16:00:00
- hour_time >= 16:00:00 → Hour 16 included ✓

**Note:** This fix needs to be applied to the dashboard template (storage mode), which requires editing the `.storage/lovelace` file or editing through the UI.

## Expected Behavior After Fix

### Charging Plan (from 16:00, SoC 21%):

| Hour | Price | SoC Start | Decision | SoC End | Reason |
|------|-------|-----------|----------|---------|--------|
| 16:00 | 0.362 | 21% | ✓ Charge | 36% | price_80 |
| 17:00 | 0.382 | 36% | ✓ Charge | 50% | price_50 |
| 18:00 | 0.390 | 50% | ✗ Don't | 50% | Over 50% |
| 19:00 | 0.379 | 50% | ✓ Charge | 65% | price_80 |
| 20:00 | 0.347 | 65% | ✓ Charge | 80% | price_80 |
| 21:00 | 0.328 | 80% | **✗ Don't** | **80%** | **At 80% (fixed!)** |
| 22:00 | 0.326 | 80% | ✗ Don't | 80% | At 80% |
| 23:00 | 0.319 | 80% | ✗ Don't | 80% | At 80% |
| 00:00 | 0.311 | 80% | **✗ Don't** | **80%** | **Already at target (fixed!)** |
| 01:00 | 0.311 | 80% | **✗ Don't** | **80%** | **Already at target (fixed!)** |
| ... | ... | 80% | ✗ Don't | 80% | Already at target |

**Key Changes:**
- ✓ Stops at hour 21 (rounded SoC 80% no longer triggers charging)
- ✓ Doesn't charge hours 0-4 tomorrow (already at target 80%)
- ✓ Current hour shows in dashboard

## Real-World Impact

### Before Fix:

**Hour 20:** 65% → 80% ✓ Correct
**Hour 21:** 80% (really 79.7%) → 95% ✗ Overcharged by 15%
**Hours 0-4 tomorrow:** 95% → 100% ✗ Overcharged to 100%

**Result:**
- Charged to 100% instead of stopping at 80%
- Wasted money on unnecessary charging
- Increased battery wear (80→100% is bad for battery health)

### After Fix:

**Hour 20:** 65% → 80% ✓ Correct
**Hour 21:** 80% → 80% ✓ Stops correctly
**Hours 0-4 tomorrow:** 80% → 80% ✓ No unnecessary charging

**Result:**
- Stops at 80% as intended
- Saves money
- Better for battery longevity

## How to Apply

### 1. Copy Fixed File

The fix has been applied to:
`/Users/mif7fe/Documents/Projects/EnergyManagement/packages/energy_management/optimal_charging_plan.yaml`

Copy it to your Home Assistant config:
```bash
cp /Users/mif7fe/Documents/Projects/EnergyManagement/packages/energy_management/optimal_charging_plan.yaml /Volumes/config/packages/energy_management/
```

(Or use whatever method you normally use to copy files to Home Assistant)

### 2. Reload YAML Configuration

**Developer Tools → YAML → Manually Configured YAML Entities → Reload**

### 3. Verify the Plan

**Developer Tools → States → sensor.optimaler_ladeplan**

Check the `charging_plan` attribute:
- **Hour 21:** should_charge should be **false**, soc_end should be **80** (not 95)
- **Hours 0-4 tomorrow:** should_charge should be **false**, soc_end should be **80** (not increasing)

### 4. Fix Dashboard (Manual - UI Edit Required)

The dashboard fix needs to be applied through the UI:

1. Go to your dashboard
2. Click **Edit** (top right)
3. Find the "Optimaler Ladeplan" card
4. Click the **pencil icon** to edit it
5. In the content, find the line:
   ```
   {%- if hour_time >= now_time -%}
   ```
6. Change it to:
   ```
   {%- if hour_time >= now_time.replace(minute=0, second=0, microsecond=0) -%}
   ```
7. **Save** the card
8. **Done** with dashboard edit
9. Refresh the page

Alternatively, if your dashboard is in storage mode and you can access the `.storage/lovelace` file, the same change can be made there.

## Testing Checklist

After reloading:

### Plan Verification
- [ ] Hour 20: soc_end = 80, should_charge = true ✓
- [ ] Hour 21: soc_end = 80 (not 95!), should_charge = false ✓
- [ ] Hour 22-23: soc_end = 80, should_charge = false ✓
- [ ] Hours 0-4 tomorrow: soc_end = 80 (not increasing!), should_charge = false ✓

### Dashboard Verification
- [ ] Current hour shows in the table (not starting with next hour)
- [ ] Current hour highlighted or shows correct status

### Real Charging Test
- [ ] Charging starts at appropriate hour
- [ ] Charging continues through threshold_80 hours
- [ ] **Charging STOPS at hour 21** (when reaching 80%)
- [ ] Charging does NOT resume for morning_target hours (already at target)

## Edge Cases Handled

### Case 1: SoC at 79.9%

**Before:** 79.9 < 80 → TRUE → Charged one more hour → 94.9% ✗

**After:** 79.9 | round(0) = 80, 80 < 80 → FALSE → Don't charge ✓

### Case 2: Opportunistic Charging Reaches 100% Before Midnight

**Before:** Selected morning hours still charged (already at 100%) ✗

**After:** 100 < 80 → FALSE → Don't charge ✓

### Case 3: Opportunistic Charging Reaches Exactly Target

**Before:** Might charge one more hour due to floating point ✗

**After:** Rounds before comparing → Stops correctly ✓

## Algorithm Summary

The corrected algorithm:

1. **Calculate initial morning target hours** based on current SoC (21%)
2. **Loop through all hours from now until forecast end:**
   - Skip past hours
   - **Opportunistic charging:** Check if price ≤ threshold AND **rounded** projected SoC < limit
   - **Morning target:** Check if hour selected AND time in range AND **rounded** projected SoC < target
   - If charging, increment projected SoC by 14.7%
3. **Result:** Accurate plan that:
   - Stops at 80% for threshold_80 (with proper rounding)
   - Doesn't overcharge for morning target (checks current projected SoC)
   - Shows all hours from current onwards (dashboard fix)

## Files Modified

**Main file:**
- `/packages/energy_management/optimal_charging_plan.yaml`
  - Line 70: Added `| round(0)` to threshold_80 check
  - Line 73: Added `| round(0)` to threshold_50 check
  - Line 78: Added `and soc.value | round(0) < target_soc` to morning_target check

**Dashboard (manual edit required):**
- Dashboard "Optimaler Ladeplan" card content
  - Changed hour filter to include current hour

## Summary

**Three bugs fixed:**
1. ✅ Threshold charging stops correctly at 80% (rounding fix)
2. ✅ Morning target doesn't overcharge (SoC check added)
3. ✅ Dashboard shows current hour (time comparison fix)

**Result:**
- Charging stops at intended SoC limits
- No unnecessary charging to 100%
- Better battery health
- More accurate dashboard display
