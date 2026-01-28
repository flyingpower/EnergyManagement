# PV Forecast Fix - Getting Hourly Data

## Problem Identified

The **Forecast.Solar Home Assistant integration** has changed in recent versions:

**Old version** (what the code expected):
- Provided `wh_hours` attribute with hourly breakdown
- Full 24+ hour forecast in one attribute

**New version** (what you have):
- Only provides `sensor.energy_current_hour` and `sensor.energy_next_hour`
- No hourly breakdown for the full day
- Just total daily values

**Result:** Dashboard PV column shows 0 kW or very limited data.

## Temporary Fix Applied

I've updated `solar_forecast_sensors.yaml` to use the limited data available:
- Shows current hour production
- Shows next hour production
- Everything else shows 0 kW

**This is a workaround only.** For proper hourly forecasts, use one of the solutions below.

## Solution 1: Use Solcast (RECOMMENDED)

**Solcast** provides excellent hourly solar forecasts and works reliably with Home Assistant.

### Setup Solcast:

#### 1. Create Solcast Account
- Go to https://solcast.com/
- Sign up for **Hobbyist** account (FREE)
- Verify email

#### 2. Add Your Rooftop
- Click "Rooftop Sites" → "Add Rooftop"
- Enter:
  - **Location:** Eutingen im Gäu (or use coordinates)
  - **Capacity:** Your system size in kW (e.g., 10 kW)
  - **Tilt:** Roof angle (e.g., 35°)
  - **Azimuth:** Direction (180° = South)
  - **Module Type:** Select your panel type
- Save rooftop configuration

#### 3. Get API Key
- Go to "API Toolkit"
- Copy your **API Key**

#### 4. Install Solcast Integration in Home Assistant
- Settings → Devices & Services
- Click **+ Add Integration**
- Search: **"Solcast PV Solar"**
- Enter your **API key**
- Configure:
  - Check "Forecast Today"
  - Check "Forecast Tomorrow"
  - Set update interval (default: 4 hours is fine for free tier)

#### 5. Update Solar Sensors

After Solcast is installed, update the sensor configuration:

**`solar_forecast_sensors.yaml`** - Change lines 15-40 to:

```yaml
hourly_forecast: >
  {% set solcast_forecast = state_attr('sensor.solcast_pv_forecast_forecast_today', 'detailedForecast') %}
  {% if solcast_forecast is not none and solcast_forecast is iterable %}
    {% set hourly = namespace(list=[]) %}
    {% for forecast in solcast_forecast %}
      {% set time = forecast.period_end | as_datetime | as_local %}
      {% set hourly.list = hourly.list + [{
        'time': time.isoformat(),
        'hour': time.hour,
        'wh': (forecast.pv_estimate * 1000) | int,
        'kw': forecast.pv_estimate | round(2)
      }] %}
    {% endfor %}
    {{ hourly.list }}
  {% else %}
    []
  {% endif %}
```

Reload template entities and done!

### Solcast Advantages:
- ✅ Free tier: 10 API calls per day
- ✅ Hourly forecast for 7 days
- ✅ More accurate than Forecast.Solar
- ✅ Accounts for weather, cloud cover
- ✅ Well-maintained HA integration

## Solution 2: REST API Direct to Forecast.Solar

If you don't want to use Solcast, call the Forecast.Solar API directly.

### Setup:

#### 1. Get Your PV System Parameters

You need:
- **Latitude:** e.g., 48.4833 (Eutingen im Gäu)
- **Longitude:** e.g., 8.7333
- **Declination:** Roof tilt (e.g., 35)
- **Azimuth:** Roof direction (e.g., 180 for South)
- **Power:** System size in kWp (e.g., 10)

Get these from:
```
Settings → Devices & Services → Forecast.Solar → Configure
```

#### 2. Add REST Sensor

Edit **`solar_forecast_rest.yaml`** (already created) and replace placeholders:

```yaml
rest:
  - resource: https://api.forecast.solar/estimate/48.4833/8.7333/35/180/10
    #                                            LAT    LON   TILT AZ  kWp
    scan_interval: 3600  # Update every hour
    sensor:
      - name: "Solar Forecast API"
        unique_id: solar_forecast_api_raw
        value_template: "{{ value_json.result.watts | first | last }}"
        json_attributes_path: "$.result"
        json_attributes:
          - watt_hours
          - watt_hours_day
          - watts
```

#### 3. Update Solar Prognose Sensor

Change `solar_forecast_sensors.yaml` to read from REST sensor:

```yaml
hourly_forecast: >
  {% set wh_hours = state_attr('sensor.solar_forecast_api', 'watt_hours') %}
  {% if wh_hours is not none and wh_hours is mapping %}
    {% set hourly = namespace(list=[]) %}
    {% for timestamp, watt_hours in wh_hours.items() %}
      {% set time = timestamp | as_datetime | as_local %}
      {% set hourly.list = hourly.list + [{
        'time': time.isoformat(),
        'hour': time.hour,
        'wh': watt_hours | int,
        'kw': (watt_hours / 1000) | round(2)
      }] %}
    {% endfor %}
    {{ hourly.list }}
  {% else %}
    []
  {% endif %}
```

#### 4. Restart Home Assistant

The REST sensor needs a restart to load.

### Forecast.Solar API Limitations:
- ⚠️ Free tier: 12 API calls per day (hourly updates only)
- ⚠️ Rate limits can block you
- ⚠️ Less accurate than Solcast
- ⚠️ No cloud cover forecasting

## Solution 3: Keep Current Workaround (Limited Data)

If you don't need hourly PV forecasts, keep the current setup:
- Shows current hour + next hour only
- All other hours show 0 kW
- Still functional, just limited visibility

**This works fine** since PV forecast doesn't affect charging decisions anyway (it's display-only).

## Recommended Action

**I recommend using Solcast** because:
1. Setup takes only 5 minutes
2. Free tier is generous (10 API calls/day)
3. Much better accuracy
4. Proper hourly breakdown for full week
5. Well-supported Home Assistant integration

## After Fixing

Once you have proper hourly data, the dashboard will show:

**Night (0:00-6:00):** 0.0 kW
**Morning (7:00-9:00):** 0.5-3.0 kW
**Midday (10:00-14:00):** 6.0-10.0 kW
**Afternoon (15:00-18:00):** 2.0-5.0 kW
**Evening (19:00-23:00):** 0.0 kW

Values depend on:
- Season (winter = lower, summer = higher)
- Weather (cloudy = lower, sunny = higher)
- System size (10 kWp example above)

## Current Status

**Files updated:**
- `solar_forecast_sensors.yaml` - Temporary workaround using current/next hour
- `forecast_solar_check.yaml` - Debug sensor to diagnose integration
- `solar_forecast_rest.yaml` - Template for REST API (needs configuration)

**To activate any solution:**
1. Make configuration changes
2. Reload: Developer Tools → YAML → Template Entities → Reload
3. Check dashboard PV column

Let me know which solution you'd like to use and I can help configure it!
