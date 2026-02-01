# Tesla SoC Reliability System

This system prevents automations from breaking when the Tesla network connection is unstable.

## The Problem

When the Tesla Fleet API connection is unstable:
- `sensor.model_3_battery_level` becomes `unavailable`, `unknown`, or reports `0%`
- All charging automations read `0%` SoC
- Emergency charging incorrectly triggers (thinking battery is critically low)
- Optimal charging plans calculate wrong hours (thinking car needs full charge)
- Manual charging targets become invalid

## The Solution

The system automatically stores the last valid SoC reading and uses it as a fallback.

### Components

#### 1. Storage (`input_number.tesla_soc_last_valid`)
- Stores the most recent valid SoC reading (> 0%)
- Updates automatically whenever a valid reading is received
- Persists across Home Assistant restarts
- Default: 50% (if no valid reading ever received)

#### 2. Smart Sensor (`sensor.tesla_ladestand`)
The main sensor now has intelligent fallback logic:

**When Live Data Available:**
```yaml
state: 45%
icon: mdi:battery-charging
attributes:
  source: live
  last_valid_soc: 45
  raw_soc: 45
```

**When Network Lost:**
```yaml
state: 45%  # Uses last valid value
icon: mdi:battery-alert  # Warning icon
attributes:
  source: last_valid  # Indicates fallback mode
  last_valid_soc: 45
  raw_soc: unavailable
```

#### 3. Safe Emergency Charging
Emergency charging only triggers when:
- SoC < 20% **AND**
- Data source is `live` (not fallback)

This prevents false alarms when using stale data.

#### 4. Connection Lost Alert
If Tesla is unavailable for 15+ minutes, you get a notification:

```
⚠️ Tesla Verbindung verloren
Tesla SoC nicht verfügbar seit 15 Minuten.
Verwende letzten gültigen Wert: 45%
```

## How It Works

### Normal Operation (Live Data)

```
1. Tesla API updates → sensor.model_3_battery_level = 67%
2. Automation detects valid reading (> 0%)
3. Stores 67% in input_number.tesla_soc_last_valid
4. sensor.tesla_ladestand shows 67% (source: live)
```

### Network Outage (Fallback Mode)

```
1. Tesla API fails → sensor.model_3_battery_level = unavailable
2. sensor.tesla_ladestand checks: Is current value valid?
3. No → Uses input_number.tesla_soc_last_valid (67%)
4. sensor.tesla_ladestand shows 67% (source: last_valid)
5. Icon changes to battery-alert to indicate fallback mode
```

### Recovery (Back to Live)

```
1. Tesla API reconnects → sensor.model_3_battery_level = 65%
2. Automation detects valid reading
3. Updates stored value to 65%
4. sensor.tesla_ladestand shows 65% (source: live)
5. Icon returns to battery-charging
```

## Dashboard Integration

### Show Data Source

You can display whether you're using live or fallback data:

```yaml
type: entities
entities:
  - entity: sensor.tesla_ladestand
    secondary_info: attribute
    attribute: source
    name: Tesla Battery
  - type: attribute
    entity: sensor.tesla_ladestand
    attribute: last_valid_soc
    name: Last Valid Reading
  - type: attribute
    entity: sensor.tesla_ladestand
    attribute: raw_soc
    name: Raw Tesla Value
```

### Visual Indicator

```yaml
type: glance
entities:
  - entity: sensor.tesla_ladestand
    name: Battery
    icon: >
      {% if state_attr('sensor.tesla_ladestand', 'source') == 'live' %}
        mdi:battery-charging
      {% else %}
        mdi:battery-alert
      {% endif %}
```

## What Automations See

All existing automations continue to work without modification:

```yaml
# This automation just works, using fallback if needed
trigger:
  - platform: numeric_state
    entity_id: sensor.tesla_ladestand
    below: 30
```

The sensor always returns a valid percentage, either live or from storage.

## Testing the System

### Simulate Network Outage

You can test the fallback by manually setting the Tesla sensor to unavailable:

**Developer Tools → States:**
1. Find `sensor.model_3_battery_level`
2. Click on it
3. Change state to `unavailable`
4. Check `sensor.tesla_ladestand` - should show last valid value with source='last_valid'

### Check Stored Value

**Developer Tools → States:**
- Look for `input_number.tesla_soc_last_valid`
- This is the value used during outages

### View Automation Activity

**Settings → Automations → "Tesla - Store Valid SoC":**
- Click "Traces" to see when valid values were stored
- Each trace shows the SoC value that was saved

## Troubleshooting

### Fallback value is wrong

If the stored value seems incorrect:

1. **Check when it was last updated:**
   ```
   Developer Tools → States → input_number.tesla_soc_last_valid
   ```
   Look at "Last changed" timestamp

2. **Manually set correct value:**
   ```yaml
   service: input_number.set_value
   target:
     entity_id: input_number.tesla_soc_last_valid
   data:
     value: 70
   ```

3. **Check automation traces:**
   - Settings → Automations → "Tesla - Store Valid SoC" → Traces
   - See what values were being stored

### Emergency charging still triggers on fallback

Check the `notladung_erforderlich` sensor:

```yaml
Developer Tools → States → binary_sensor.notladung_erforderlich
```

Attributes should show:
```yaml
soc_source: live  # Only 'live' triggers emergency charging
```

If showing `last_valid`, emergency charging is correctly prevented.

### Connection lost alert not firing

The alert requires 15 minutes of unavailability. To test immediately:

```yaml
service: automation.trigger
target:
  entity_id: automation.tesla_connection_lost_alert
```

## Migration from Previous Setup

No changes needed! The system is backwards compatible:

- `sensor.tesla_ladestand` still exists
- All automations continue to use it
- Fallback happens automatically
- No dashboard changes required

## Advanced: Custom Fallback Duration

By default, the system uses the last valid value indefinitely. To expire old values:

```yaml
# In sensor.tesla_ladestand state template, add age check:
{% set last_update = state_attr('input_number.tesla_soc_last_valid', 'last_changed') %}
{% set age = (now() - last_update).total_seconds() %}
{% if age > 3600 %}  # Older than 1 hour
  50  # Use default instead of old value
{% else %}
  {{ states('input_number.tesla_soc_last_valid') }}
{% endif %}
```

## Files

- `packages/energy_management/tesla_soc_reliability.yaml` - Storage and automation
- `packages/energy_management/sensors.yaml` - Updated sensor.tesla_ladestand

## Summary

**Before:**
- Network issue → SoC = 0% → Automations break

**After:**
- Network issue → SoC = last valid → Automations continue safely
- Clear indication when using fallback (icon + attributes)
- Alert after 15 minutes
- Emergency charging prevented on stale data
