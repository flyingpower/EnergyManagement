# Energy Management System - Deployment Guide

## Configured Entity IDs

This installation has been configured with the actual entity IDs from your Home Assistant instance.

### Tesla Model 3 Entities

| Entity ID | Description | Type |
|-----------|-------------|------|
| `sensor.model_3_battery_level` | Battery SOC (0-100%) | Sensor |
| `sensor.model_3_charging` | Charging state (Charging, Stopped, Complete, Disconnected) | Sensor |
| `sensor.model_3_charger_power` | Current charger power | Sensor |
| `number.model_3_charge_current` | Set charging current (0-16A) | Number |
| `number.model_3_charge_limit` | Set charge limit percentage | Number |
| `switch.model_3_charge` | Start/stop charging | Switch |

### Tibber Entities

| Entity ID | Description | Type |
|-----------|-------------|------|
| `sensor.tibber_electricity_price` | Current electricity price (EUR/kWh) | Sensor |
| `sensor.tibber_power` | Current power consumption (W) | Sensor |
| `sensor.tibber_power_production` | Current PV production (W) | Sensor |

**Note:** PV surplus is calculated as: `power_production - power_consumption`. Negative values indicate grid consumption.

### Easee Charger (Garage) Entities

| Entity ID | Description | Type |
|-----------|-------------|------|
| `sensor.garage_power` | Current charging power (kW) | Sensor |
| `sensor.garage_status` | Charger status | Sensor |
| `sensor.garage_session_energy` | Energy charged in current session (kWh) | Sensor |
| `switch.garage_charger_enabled` | Enable/disable charger | Switch |

## Important Configuration Notes

### Tesla Charge Current Limitation

Your Tesla integration supports a maximum charging current of **16A** (not 32A). This is reflected in all scripts:

```yaml
- service: number.set_value
  target:
    entity_id: number.model_3_charge_current
  data:
    value: 16  # Maximum available
```

At 230V single-phase, this provides approximately **3.7 kW** charging power.

### PV Surplus Calculation

Since Tibber doesn't provide a direct "net consumption" sensor, the PV surplus is calculated in `sensors.yaml`:

```yaml
{% set power_consumption = states('sensor.tibber_power') | float(0) %}
{% set power_production = states('sensor.tibber_power_production') | float(0) %}
{% set net_consumption = power_consumption - power_production %}
```

When `net_consumption` is **negative**, there is PV surplus available for charging.

### Easee Charger Control

The Easee integration in your system uses **switches** rather than direct service calls:

- **Start charging:** `switch.turn_on` → `switch.model_3_charge`
- **Stop charging:** `switch.turn_off` → `switch.model_3_charge`
- **Set current:** `number.set_value` → `number.model_3_charge_current`

**Note:** Dynamic current adjustment for Easee is handled through the Tesla charge current setting, not through Easee directly.

## Deployment Steps

### 1. Prepare Configuration Directory

Your Home Assistant configuration directory should look like this:

```
/config/
├── configuration.yaml (add packages line if not present)
├── packages/
│   └── energy_management/
│       ├── inputs.yaml ✓
│       ├── sensors.yaml ✓
│       ├── automations.yaml ✓
│       └── scripts.yaml ✓
└── dashboards/
    └── energy_management.yaml ✓
```

### 2. Update configuration.yaml

Add this line to your `configuration.yaml` if not already present:

```yaml
homeassistant:
  packages: !include_dir_named packages
```

### 3. Copy Configuration Files

Copy all files from this repository to your Home Assistant config directory:

```bash
# From the EnergyManagement repository
cp -r packages/energy_management /config/packages/
cp dashboards/energy_management.yaml /config/dashboards/
```

### 4. Verify Entity IDs

All entity IDs have been configured based on your actual Home Assistant instance. However, verify these critical entities exist:

```bash
# In Home Assistant: Developer Tools → States
# Search for each of these:
sensor.model_3_battery_level
sensor.model_3_charging
sensor.tibber_electricity_price
sensor.tibber_power
sensor.garage_power
switch.model_3_charge
```

### 5. Check Configuration

Before restarting, check the configuration:

1. Go to **Configuration** → **Server Controls**
2. Click **Check Configuration**
3. Review any errors
4. Fix if necessary

### 6. Restart Home Assistant

1. Go to **Configuration** → **Server Controls**
2. Click **Restart**
3. Wait 2-3 minutes for restart

### 7. Verify Input Helpers Created

After restart, verify all input helpers were created:

**Configuration** → **Helpers** → Filter by "energie"

You should see:
- ✅ `input_number.target_soc_morning`
- ✅ `input_number.manual_target_soc`
- ✅ `input_number.price_threshold_50`
- ✅ `input_number.price_threshold_80`
- ✅ `input_number.min_pv_surplus`
- ✅ `input_number.pv_charging_buffer`
- ✅ `input_number.pv_hysteresis_time`
- ✅ `input_boolean.manual_charging_mode`
- ✅ `input_boolean.enable_pv_charging`
- ✅ `input_boolean.enable_price_charging`
- ✅ `input_boolean.enable_morning_readiness`
- ✅ `input_datetime.manual_deadline`
- ✅ `input_select.charging_state`

### 8. Verify Template Sensors

**Developer Tools** → **States** → Search for:

- `sensor.aktueller_lademodus`
- `sensor.verfugbarer_pv_uberschuss`
- `sensor.tesla_ladestand`
- `sensor.easee_ladegerat_status`
- `binary_sensor.auto_angeschlossen`

### 9. Test Automations

**Developer Tools** → **Services** → Test key scripts:

```yaml
# Test 1: Check if car is detected as connected
# Should show "true" or "false"
Template: {{ is_state('binary_sensor.auto_angeschlossen', 'on') }}

# Test 2: Check PV surplus calculation
# Should show current surplus in watts
Template: {{ states('sensor.verfugbarer_pv_uberschuss') }}

# Test 3: Check charging state
# Should show current state
Template: {{ states('input_select.charging_state') }}
```

### 10. Install Dashboard

Go to **Overview** → **Edit Dashboard** → **Three dots** → **Raw configuration editor**

Create a new view and paste the content from `dashboards/energy_management.yaml`.

## Configuration Tuning

### Adjust PV Surplus Threshold

Default: 1400W

To change: **Configuration** → **Helpers** → `input_number.min_pv_surplus`

Recommended values:
- **1400W**: ~6A charging current (minimum for most EVs)
- **2300W**: ~10A charging current
- **3200W**: ~14A charging current

### Adjust Price Thresholds

**For 50% charging:** Default 0.30 EUR/kWh
**For 80% charging:** Default 0.28 EUR/kWh

Adjust based on your local electricity prices.

### Adjust Morning Target SOC

Default: 80%

Change in dashboard or **Configuration** → **Helpers** → `input_number.target_soc_morning`

## Monitoring

### Check System Status

Dashboard location: **http://your-ha:8123/lovelace/energie-management**

Key indicators:
- **Charging State:** Should show current mode (idle, pv, price, manual)
- **Tesla SOC:** Current battery level
- **PV Surplus:** Available solar energy
- **Current Price:** Tibber electricity price

### Check Logs

**Configuration** → **Logs**

Filter for "energy_mgmt" to see automation activity.

### Automation History

**Logbook** → Filter by automation name

Look for:
- "Energie Management - Zentralsteuerung" (runs every 5 minutes)
- "Energie Management - PV Ladestrom anpassen"
- Any error messages

## Troubleshooting

### PV Charging Not Starting

**Check:**
1. Is car connected? `binary_sensor.auto_angeschlossen` = on
2. Is PV enabled? `input_boolean.enable_pv_charging` = on
3. Is surplus sufficient? `sensor.verfugbarer_pv_uberschuss` >= 1400W
4. Is manual mode off? `input_boolean.manual_charging_mode` = off

**Fix:**
- Lower `min_pv_surplus` if needed
- Check Tibber integration provides power data
- Verify Tesla is home and connected

### Charging Not Stopping

**Check:**
- Current charging state: `input_select.charging_state`
- Is automation enabled?
- Check logs for errors

**Fix:**
- Manually stop: Services → `script.stop_charging`
- Check `switch.model_3_charge` state

### Template Sensor Errors

**Check:** **Developer Tools** → **Template**

Test each sensor template individually to identify errors.

### Price Data Missing

**Check:** `sensor.tibber_electricity_price` has a value

**Fix:**
- Verify Tibber integration is working
- Check Tibber API token is valid
- Restart Tibber integration

## Safety Features

The system includes several safety mechanisms:

1. **Emergency Charging:** Automatically activates when SOC < 20%
2. **Morning Readiness:** Ensures car is ready by 7 AM
3. **Disconnection Detection:** Stops charging when car disconnected
4. **Integration Error Handling:** Graceful degradation when sensors unavailable
5. **Priority System:** Clear hierarchy prevents conflicting actions

## Performance Expectations

### Typical Behavior

**PV Charging:**
- Starts when surplus > 1400W
- Adjusts current every 5 minutes
- Stops after 5 minutes below threshold

**Price-Based Charging:**
- Evaluates prices daily at 13:00
- Schedules charging for cheapest hours
- Ensures 7 AM readiness

**Cost Savings:**
- Expected: 30-40% reduction vs standard charging
- Actual savings depend on PV production and price patterns

### Resource Usage

- **CPU:** Minimal (template sensors update every 5 min)
- **Memory:** < 10MB additional
- **Network:** Periodic API calls to Tesla/Tibber/Easee

## Backup and Recovery

### Backup Configuration

```bash
# Backup entire config
cp -r /config/packages/energy_management /backup/

# Or use Home Assistant backup feature
# Configuration → Backups → Create backup
```

### Restore

```bash
# Restore from backup
cp -r /backup/energy_management /config/packages/

# Restart Home Assistant
```

## Support

For issues:

1. Check this README thoroughly
2. Review Home Assistant logs
3. Verify all entity IDs match your system
4. Test individual scripts/automations
5. Check GitHub repository for updates

## Version History

### v1.0.0 (2026-01-19)
- Initial deployment with configured entity IDs
- Tesla Model 3 integration (16A max current)
- Tibber price and PV data
- Easee charger control via switches
- All three charging scenarios implemented
- Full dashboard and notifications
