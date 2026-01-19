# Energy Management System for Home Assistant

Intelligent EV charging automation system that optimizes charging costs by leveraging PV surplus, dynamic electricity prices, and user-defined schedules.

## Overview

This Home Assistant configuration provides automated charging control for Tesla EVs using an Easee charger, with three main charging scenarios:

1. **PV Surplus Charging** - Automatically charges using excess solar energy, dynamically adjusting power to match available surplus
2. **Price-Based Charging** - Schedules charging during cheapest electricity hours while ensuring morning readiness
3. **Manual Charging** - User-defined charging targets with intelligent PV/grid blending

## Features

- ✅ Dynamic PV surplus charging with automatic power adjustment
- ✅ Price optimization using Tibber hourly rates
- ✅ Morning readiness guarantee (car ready by 7 AM)
- ✅ Manual charging mode with deadline support
- ✅ Emergency charging for critical battery levels
- ✅ Intelligent scenario prioritization
- ✅ Mobile notifications for key events
- ✅ Comprehensive dashboard with controls
- ✅ Historical data tracking and cost analysis

## Prerequisites

### Required Integrations

1. **Tesla Integration**
   - Available in Home Assistant core
   - Requires Tesla account credentials
   - Provides vehicle battery level and charging control

2. **Tibber Integration**
   - Available in Home Assistant core
   - Requires API token from [Tibber Developer Portal](https://developer.tibber.com/)
   - Provides hourly electricity prices and PV surplus data

3. **Easee Charger Integration**
   - Available via HACS
   - Repository: https://github.com/fondberg/easee_hass
   - Requires Easee account credentials
   - Provides charger control (6-32A dynamic current)

### Hardware Requirements

- Tesla vehicle (Model 3, Model Y, Model S, or Model X)
- Easee EV charger installed and configured
- Tibber electricity contract with hourly pricing
- Home Assistant instance (version 2023.1 or later recommended)

## Installation

### 1. Clone or Copy Files

Copy all files from this repository to your Home Assistant configuration directory:

```bash
/config/
├── configuration.yaml
├── packages/
│   └── energy_management/
│       ├── inputs.yaml
│       ├── sensors.yaml
│       ├── automations.yaml
│       └── scripts.yaml
└── dashboards/
    └── energy_management.yaml
```

### 2. Configure Secrets

Create or update `/config/secrets.yaml` with your credentials:

```yaml
# Location
latitude: 48.8566
longitude: 2.3522
elevation: 50

# Database
db_url: sqlite:///config/home-assistant_v2.db

# Tibber
tibber_api_token: your_tibber_api_token_here

# Tesla (if using YAML configuration)
tesla_username: your_tesla_email
tesla_password: your_tesla_password

# Easee (if using YAML configuration)
easee_username: your_easee_email
easee_password: your_easee_password
```

### 3. Install Integrations

#### Via Home Assistant UI:

1. **Tesla Integration:**
   - Go to Configuration → Integrations
   - Click "Add Integration"
   - Search for "Tesla"
   - Enter your Tesla account credentials

2. **Tibber Integration:**
   - Go to Configuration → Integrations
   - Click "Add Integration"
   - Search for "Tibber"
   - Enter your Tibber API token

3. **Easee Integration (HACS):**
   - Ensure HACS is installed
   - Go to HACS → Integrations
   - Search for "Easee"
   - Install the integration
   - Restart Home Assistant
   - Go to Configuration → Integrations
   - Click "Add Integration"
   - Search for "Easee"
   - Enter your Easee credentials

### 4. Update Entity Names

The configuration uses specific entity names. You may need to update these based on your actual entity IDs:

**In `sensors.yaml`:**
- `sensor.tesla_battery` → Your Tesla battery sensor
- `sensor.tesla_charging_state` → Your Tesla charging state sensor
- `sensor.tibber_prices` → Your Tibber prices sensor
- `sensor.tibber_net_consumption` → Your Tibber consumption sensor
- `sensor.easee_power` → Your Easee power sensor
- `sensor.easee_status` → Your Easee status sensor

**In `scripts.yaml`:**
- `sensor.easee_charger_id` → Your Easee charger ID

To find your actual entity IDs:
1. Go to Developer Tools → States
2. Search for "tesla", "tibber", "easee"
3. Note the entity IDs
4. Update the configuration files accordingly

### 5. Configure Notifications

Update `/config/configuration.yaml` notification service:

```yaml
notify:
  - name: mobile_app
    platform: group
    services:
      - service: mobile_app_your_device_name  # Replace with your actual device
```

Find your device name:
1. Go to Configuration → Integrations → Mobile App
2. Note your device name
3. Replace `your_device_name` with the actual name

### 6. Restart Home Assistant

After all files are in place:
1. Check configuration: Configuration → Server Controls → Check Configuration
2. If valid, restart Home Assistant

### 7. Install Dashboard

#### Option A: Via UI (Storage Mode)
1. Go to Overview → Edit Dashboard
2. Click the three dots → Raw configuration editor
3. Copy content from `dashboards/energy_management.yaml`
4. Create a new view and paste the configuration

#### Option B: YAML Mode
1. Uncomment the Lovelace section in `configuration.yaml`
2. Restart Home Assistant
3. Dashboard will be available at `/lovelace/energie-management`

## Configuration

### Default Values

The system comes pre-configured with sensible defaults:

| Setting | Default Value | Description |
|---------|---------------|-------------|
| Morning Target SOC | 80% | Target battery level by 7 AM |
| Price Threshold (80%) | €0.28/kWh | Price below which to charge to 80% |
| Price Threshold (50%) | €0.30/kWh | Price below which to charge to 50% |
| Min PV Surplus | 1400W | Minimum solar surplus to start charging |
| PV Buffer | 200W | Safety buffer for household consumption |
| Hysteresis Time | 5 min | Time before stopping PV charging |

### Adjusting Settings

All settings can be adjusted via the dashboard or in the input helpers:

1. **Dashboard:** Go to Energie Management → Einstellungen
2. **Input Helpers:** Go to Configuration → Helpers → Filter by "energie"

### Entity IDs Reference

#### Input Helpers
- `input_number.target_soc_morning` - Morning target SOC
- `input_number.manual_target_soc` - Manual charging target
- `input_number.price_threshold_50` - Price threshold for 50%
- `input_number.price_threshold_80` - Price threshold for 80%
- `input_number.min_pv_surplus` - Minimum PV surplus
- `input_boolean.manual_charging_mode` - Manual mode toggle
- `input_boolean.enable_pv_charging` - Enable PV charging
- `input_boolean.enable_price_charging` - Enable price charging
- `input_boolean.enable_morning_readiness` - Enable morning check
- `input_datetime.manual_deadline` - Manual charging deadline
- `input_select.charging_state` - Current charging state

#### Template Sensors
- `sensor.aktueller_lademodus` - Current charging mode (display)
- `sensor.verfugbarer_pv_uberschuss` - Available PV surplus
- `sensor.berechneter_ladestrom_pv` - Calculated charging current
- `sensor.tesla_ladestand` - Tesla SOC (German)
- `sensor.aktueller_strompreis` - Current electricity price
- `binary_sensor.auto_angeschlossen` - Car connected status

## Usage

### Automatic Operation

The system runs automatically with minimal intervention required:

1. **PV Charging** - Activates automatically when:
   - Solar surplus > 1400W
   - Car is connected
   - No manual mode active

2. **Price-Based Charging** - Activates automatically:
   - During cheapest hours
   - To ensure morning readiness
   - Based on configured price thresholds

3. **Morning Readiness** - Checks nightly at 22:00:
   - Calculates if morning target is reachable
   - Starts additional charging if needed
   - Sends notification if target won't be met

### Manual Charging

To use manual charging mode:

1. Go to dashboard → Manueller Lademodus
2. Toggle "Manuellen Modus aktivieren" ON
3. Set your desired target SOC (e.g., 90%)
4. Set your deadline (date and time)
5. System will calculate and execute optimal charging

The system will:
- Prefer PV charging when surplus is available
- Use grid charging when needed to meet deadline
- Consider current electricity price
- Notify when target is reached

### Notifications

You'll receive mobile notifications for:

- ✅ Charging started (with mode and reason)
- ✅ Charging completed (with total energy)
- ⚠️ Morning target won't be met
- 🚨 Emergency charging activated (SOC < 20%)
- ⚠️ Integration unavailable

## Scenario Priority

The system evaluates scenarios in this order:

1. **Emergency** - SOC < 20% → Charge immediately at max power
2. **Manual** - User override → Follow user-defined schedule
3. **Morning Readiness** - Before 7 AM if target not met → Charge to target
4. **PV Surplus** - Solar energy available → Use free energy
5. **Price-Based** - During cheap hours → Optimize cost
6. **Idle** - None of the above → Stop charging

Only ONE scenario is active at any time.

## Troubleshooting

### Common Issues

#### 1. Charging Not Starting

**Check:**
- Is car connected? (`binary_sensor.auto_angeschlossen`)
- Is charging state "idle"? (`input_select.charging_state`)
- Are integrations working? (Developer Tools → States)

**Solution:**
- Verify car is plugged in and at home
- Check that at least one charging mode is enabled
- Restart the affected integration

#### 2. PV Charging Not Working

**Check:**
- Is PV charging enabled? (`input_boolean.enable_pv_charging`)
- Is surplus above minimum? (`sensor.verfugbarer_pv_uberschuss` > 1400W)
- Is calculated current valid? (`sensor.berechneter_ladestrom_pv` >= 6A)

**Solution:**
- Lower `min_pv_surplus` if needed
- Check Tibber integration provides `net_consumption`
- Verify Easee accepts dynamic current commands

#### 3. Price Data Not Available

**Check:**
- Is Tibber integration working?
- Does `sensor.tibber_prices` have attributes `today` and `tomorrow`?

**Solution:**
- Verify Tibber API token is valid
- Check that Tibber provides price data for your contract
- Prices update around 13:00 CET for next day

#### 4. Notifications Not Received

**Check:**
- Is Mobile App integration configured?
- Is notification service name correct in `configuration.yaml`?

**Solution:**
- Update `notify.mobile_app` service name
- Test notification manually: Developer Tools → Services → notify.mobile_app

### Debug Mode

Enable debug logging for the energy management system:

```yaml
logger:
  default: info
  logs:
    homeassistant.components.automation.energy_mgmt: debug
```

View logs: Configuration → Settings → Logs

### Reset System

To reset the energy management system:

1. Set all `input_boolean` entities to OFF
2. Set `input_select.charging_state` to "idle"
3. Stop any active charging manually
4. Wait 5 minutes for next controller cycle
5. Re-enable desired features

## Customization

### Adjust Charging Times

To change the morning deadline from 7 AM:

Edit `automations.yaml`:
```yaml
- id: energy_mgmt_morning_readiness_check
  trigger:
    - platform: time
      at: "06:00:00"  # Changed from 07:00:00
```

### Add Custom Price Thresholds

Add a new input_number for your threshold:

`inputs.yaml`:
```yaml
input_number:
  price_threshold_custom:
    name: "Custom Price Threshold"
    min: 0.10
    max: 0.50
    step: 0.01
    initial: 0.25
```

Update scripts.yaml to use the new threshold.

### Change Charger Power Limits

Edit maximum current in scripts:

`scripts.yaml`:
```yaml
current: 16  # Changed from 32 (reduces from 7.4kW to 3.7kW)
```

## Cost Savings

Expected cost reduction: **30-40%** compared to standard charging.

Savings breakdown:
- **PV Charging:** Free energy (€0.00/kWh)
- **Price-Based:** Average €0.15/kWh (vs €0.35 standard)
- **Smart Scheduling:** Avoid expensive peak hours

Example monthly savings (assuming 1000 kWh/month):
- Standard cost: 1000 kWh × €0.35 = **€350**
- Optimized cost:
  - 400 kWh PV × €0.00 = €0
  - 600 kWh Grid × €0.18 = €108
  - **Total: €108**
- **Monthly savings: €242** (69%)

## Advanced Features

### AppDaemon Integration (Optional)

For more complex price optimization, consider using AppDaemon:

1. Install AppDaemon add-on
2. Create Python script for optimal hour calculation
3. Replace template sensors with AppDaemon logic

### InfluxDB & Grafana (Optional)

For advanced analytics:

1. Install InfluxDB add-on
2. Configure recorder to use InfluxDB
3. Install Grafana add-on
4. Create custom dashboards

### Utility Meters

Track costs over time:

```yaml
utility_meter:
  monthly_charging_cost:
    source: sensor.charging_cost
    cycle: monthly
```

## Support

For issues or questions:

1. Check this README thoroughly
2. Review Home Assistant logs
3. Verify integration configurations
4. Check entity states in Developer Tools

## License

This configuration is provided as-is for personal use.

## Changelog

### Version 1.0.0 (2026-01-19)
- Initial release
- PV surplus charging
- Price-based charging
- Manual charging mode
- Morning readiness check
- Emergency charging
- Full dashboard
- Mobile notifications

## Acknowledgments

- Home Assistant community
- Tesla integration contributors
- Tibber integration maintainers
- Easee HACS integration developers
