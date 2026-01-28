# Solar Forecast Setup Guide

## Problem

The PV forecast in the dashboard shows **0 kW** for all hours because the **Forecast.Solar integration** is either:
1. Not installed
2. Not configured correctly
3. Not providing data

## Files Updated

1. **`solar_forecast_sensors.yaml`** - Fixed namespace bug in `hourly_forecast`, `hourly_sunshine`, and `plan` attributes
2. **`solar_debug.yaml`** - NEW debug sensor to check Forecast.Solar integration status

## Step 1: Check if Forecast.Solar Integration is Installed

After reloading template entities, check the debug sensor:

**Developer Tools → States → `sensor.debug_solar_prognose`**

### Check These Attributes:

```yaml
integration_installed: true/false  # Is Forecast.Solar installed?
sensor_exists: true/false          # Does sensor.energy_production_today exist?
sensor_state: "12.5" or "unknown"  # Sensor value
has_wh_hours: true/false           # Does it have hourly data?
wh_hours_count: 24                 # How many hours of forecast?
first_entry: "2026-01-26T08:00:00: 450 Wh"  # Sample data
```

### Scenario A: `integration_installed: false`

**The Forecast.Solar integration is NOT installed.**

#### Install Forecast.Solar Integration:

1. Go to **Settings → Devices & Services**
2. Click **+ Add Integration** (bottom right)
3. Search for **"Forecast.Solar"**
4. Click on it and follow the setup wizard

#### Configuration Required:

You'll need to provide:
- **Latitude/Longitude** - Your home location (auto-filled from HA)
- **Declination** - Roof tilt angle (degrees from horizontal)
  - Flat roof: 0°
  - 45° pitched roof: 45°
  - For Eutingen im Gäu, typical: 30-40°
- **Azimuth** - Roof direction (degrees from north)
  - North: 0° or 360°
  - East: 90°
  - South: 180° (best for Germany)
  - West: 270°
- **Total PV Power** - System size in kWp (kilowatt peak)
  - Example: 10 kWp
- **Damping** - Panel efficiency factor
  - Default: 0 (no damping)
  - Use 0.1-0.2 if panels are older/shaded

After setup, the integration creates these sensors:
- `sensor.energy_production_today`
- `sensor.energy_production_tomorrow`
- `sensor.power_production_now`

### Scenario B: `sensor_exists: false`

**Integration installed but sensor doesn't exist.**

Check:
1. **Settings → Devices & Services → Forecast.Solar**
2. Verify device is showing
3. Click on the device
4. Check if `sensor.energy_production_today` is listed

If sensor missing:
- Remove and re-add the integration
- Check Home Assistant logs for errors

### Scenario C: `has_wh_hours: false`

**Sensor exists but has no hourly forecast data.**

Possible causes:
1. **API quota exceeded** - Forecast.Solar free tier has limits
2. **Network connectivity issue** - Can't reach Forecast.Solar API
3. **Invalid configuration** - Wrong coordinates or parameters

Check:
- **Settings → System → Logs** - Look for Forecast.Solar errors
- Wait a few hours and check again (API updates periodically)
- Check internet connectivity

### Scenario D: `wh_hours_count: 0` or very low

**Data exists but incomplete.**

This is normal:
- At night (before sunrise), count may be low
- Forecast.Solar updates every few hours
- Wait until daylight hours for full forecast

## Step 2: After Installing Integration

### A. Reload Template Entities

```
Developer Tools → YAML → Template Entities → Reload
```

### B. Check Sensors

1. **`sensor.solar_prognose_stundlich`** - Should now have `hourly_forecast` attribute
2. **`sensor.sonnenstunden_prognose`** - Should show number of sunshine hours
3. **`sensor.debug_solar_prognose`** - Should show parsed data

### C. Check Dashboard

Go to your Energy Management dashboard and look at the **"Optimaler Ladeplan"** table.

The **PV column** should now show forecast values like:
- 0.0 kW (night hours)
- 2.5 kW (morning)
- 8.0 kW (midday)
- 3.5 kW (afternoon)
- 0.0 kW (evening)

## Step 3: Verify Data Quality

### Expected Values

For a 10 kWp system in Eutingen im Gäu:

**Winter (January):**
- Peak production: 3-5 kW (midday)
- Sunshine hours: 2-4 hours
- Total daily: 5-15 kWh

**Summer (July):**
- Peak production: 8-10 kW (midday)
- Sunshine hours: 8-12 hours
- Total daily: 50-70 kWh

**Cloudy day:**
- Peak production: 1-2 kW
- Sunshine hours: 0-2 hours
- Total daily: 2-8 kWh

### If Values Seem Wrong

Check configuration:
1. **Settings → Devices & Services → Forecast.Solar**
2. Click device → Settings icon (gear)
3. Verify:
   - Declination (roof tilt)
   - Azimuth (roof direction)
   - Total power (kWp)
   - Damping factor

## Step 4: Template Bug Fixes

I also fixed the **same namespace bug** in the solar sensors that we had in the charging plan:

### What Was Fixed:

**Before (broken):**
```jinja2
{% set hourly = [] %}
{% for item in data %}
  {% set hourly = hourly + [item] %}  # ← Doesn't work in loops!
{% endfor %}
```

**After (working):**
```jinja2
{% set hourly = namespace(list=[]) %}
{% for item in data %}
  {% set hourly.list = hourly.list + [item] %}  # ← Works correctly!
{% endfor %}
{{ hourly.list }}
```

This was preventing:
- `hourly_forecast` from being populated
- `hourly_sunshine` from being populated
- Dashboard PV column from showing data

## Testing After Setup

### Quick Test:

1. **Check sensor state:**
   ```
   Developer Tools → States → sensor.energy_production_today
   ```
   Should show today's total production forecast (kWh)

2. **Check hourly data:**
   ```
   Developer Tools → States → sensor.solar_prognose_stundlich
   ```
   Expand `hourly_forecast` attribute - should show array of hourly data

3. **Check dashboard:**
   Energy Management → Optimaler Ladeplan table
   PV column should show kW values

### Full Test:

Create a test automation to log the data:

```yaml
automation:
  - alias: Test Solar Forecast
    trigger:
      - platform: time_pattern
        hours: "/1"  # Every hour
    action:
      - service: system_log.write
        data:
          message: >
            Solar forecast test:
            Now: {{ states('sensor.power_production_now') }} W
            Today total: {{ states('sensor.energy_production_today') }} kWh
            Next hour: {{ state_attr('sensor.solar_prognose_stundlich', 'hourly_forecast')[0].kw if state_attr('sensor.solar_prognose_stundlich', 'hourly_forecast') else 'No data' }} kW
          level: info
```

## Troubleshooting

### Error: "Could not connect to Forecast.Solar"

**Cause:** Network connectivity or API down

**Solution:**
- Check internet connection
- Wait and try again later
- Check https://forecast.solar/ is accessible

### Error: "Invalid coordinates"

**Cause:** Latitude/longitude outside valid range

**Solution:**
- Use Home Assistant's coordinates
- Verify: Settings → System → General → Location

### Warning: "API quota exceeded"

**Cause:** Too many requests to free API

**Solution:**
- Forecast.Solar free tier updates every 1 hour
- Wait for quota to reset
- Consider paid account for more frequent updates

### Sensor shows "unknown" or "unavailable"

**Cause:** Integration not initialized yet

**Solution:**
- Wait 5-10 minutes after installation
- Restart Home Assistant
- Check integration is enabled

## Alternative: Solcast Integration

If Forecast.Solar doesn't work or you want more accurate forecasts, use **Solcast** instead:

1. Create free account at https://solcast.com/
2. Install **Solcast Solar** integration in Home Assistant
3. Configure with your API key
4. Update sensor names in `solar_forecast_sensors.yaml`:
   - Change `sensor.energy_production_today` to appropriate Solcast sensor

## Summary

**For PV forecast to work, you need:**

1. ✅ Forecast.Solar integration installed and configured
2. ✅ `sensor.energy_production_today` exists and has data
3. ✅ Template entities reloaded (to apply namespace fixes)
4. ✅ Configuration matches your actual PV system (declination, azimuth, power)

**After setup:**
- Dashboard PV column shows hourly forecast
- `sensor.sonnenstunden_prognose` shows expected sunshine hours
- `sensor.pv_prognose_nachste_stunden` shows next 6 hours production
- Old `sensor.ladeplan_mit_solarprognose` now works (was broken before)

The PV forecast is **informational only** in the optimal charging plan - it doesn't affect charging decisions, but helps you see when solar production is expected.
