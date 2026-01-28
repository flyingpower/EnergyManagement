# Price Charging Automation Fix - System Lag Issue

## Problem

The system was experiencing severe lag and unresponsiveness. Log analysis revealed:

**Error (repeating every minute):**
```
ERROR: Error rendering data template: SecurityError: access to attribute '__class__' of 'bool' object is unsafe.
```

**Source:** `automation.energie_management_preisplan_ausfuhren` (Price charging automation)

**Impact:**
- Thousands of error messages per day
- Log file spam (31KB of errors in 500 lines)
- System lag and slow response
- Database bloat

## Root Cause

**File:** `/packages/energy_management/price_charging_automation.yaml`
**Line:** 45

**Problematic code:**
```yaml
message: >
  Price charging automation triggered:
  should_charge={{ should_charge }} (type: {{ should_charge.__class__.__name__ }}),
  ...
```

**Issue:** Home Assistant **forbids** accessing `__class__` attribute in templates for security reasons.

**Why this caused lag:**
1. Automation triggered every minute (sensor updates + hourly trigger)
2. Each trigger threw a security exception
3. Error handling and logging consumed resources
4. Log file grew rapidly
5. Database recorded thousands of errors

## The Fix

**Removed the unsafe template access:**

```yaml
# BEFORE (broken):
message: >
  should_charge={{ should_charge }} (type: {{ should_charge.__class__.__name__ }}),

# AFTER (fixed):
message: >
  should_charge={{ should_charge }},
```

**Why it was there:**
- Debug code to check variable type
- Not needed in production
- Template security rules prohibit introspection

## Files Modified

- ✅ `/Volumes/config/packages/energy_management/price_charging_automation.yaml`
- ✅ `/Users/mif7fe/Documents/Projects/EnergyManagement/packages/energy_management/price_charging_automation.yaml`

## Next Steps

### 1. Reload Automations

**Developer Tools → YAML → Automations → Reload**

This will apply the fix without restarting Home Assistant.

### 2. Monitor Logs

**Settings → System → Logs**

The errors should **stop immediately** after reload. Look for:
- ❌ No more "SecurityError: access to attribute '__class__'" errors
- ✅ Clean "Price charging automation triggered" log entries
- ✅ System responsive again

### 3. Clear Old Errors (Optional)

**Settings → System → Logs → Clear**

This will remove the thousands of old error messages.

## Additional Observations

### Trigger Frequency

The automation is currently triggering very frequently:

**Current triggers:**
- `time_pattern: minutes: "0"` → Every hour (intended)
- `state: sensor.optimaler_ladeplan` → Every time sensor updates

**Actual behavior:** Triggering every minute

**Reason:** The `sensor.optimaler_ladeplan` sensor is updating every minute (due to its triggers).

**Impact:** Not necessarily bad - ensures plan executes promptly when conditions change.

**If still laggy after fix:**
1. Check if sensor is updating too frequently
2. Consider adding `for: "00:00:30"` to state trigger (wait 30s before firing)
3. Or remove state trigger and rely only on hourly check

## Verification

### Expected Log Messages (After Fix)

**Good:**
```
Price charging automation triggered:
should_charge=true,
is_charging=False,
soc=65%,
hour=18
```

**Bad (should not appear anymore):**
```
ERROR: SecurityError: access to attribute '__class__' of 'bool' object is unsafe.
```

### System Performance

**Before:**
- Slow UI response
- Lag when navigating
- High CPU/memory usage
- Large log file

**After:**
- Fast UI response
- Smooth navigation
- Normal resource usage
- Clean logs

## Prevention

**Template Security Rules:**

Never access these in Home Assistant templates:
- `__class__`
- `__name__`
- `__module__`
- `__dict__`
- `__globals__`

**For debugging variable types:**
```yaml
# DON'T:
{{ my_var.__class__.__name__ }}

# DO:
{{ my_var | string }}  # Convert to string
{{ my_var }}            # Just output the value
```

## Summary

✅ **Fixed:** Removed `__class__.__name__` template security violation
✅ **Impact:** System lag should resolve immediately after reload
✅ **Action Required:** Reload automations in Developer Tools

The error was a debug line that violated Home Assistant's template security sandbox. Removing it will eliminate thousands of errors per day and restore system performance.
