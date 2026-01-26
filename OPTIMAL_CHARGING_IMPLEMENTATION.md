# Optimal Price-Based Charging Implementation

## Overview

Implemented a new algorithm for price-based charging that ensures the morning target SoC is reached while taking advantage of low-price opportunities.

## Implementation Date
2026-01-25

## Files Created

### 1. `/packages/energy_management/optimal_charging_plan.yaml`
**Purpose:** Calculates the optimal charging plan based on electricity prices and charging requirements.

**Key Features:**
- Calculates hours needed to reach morning target (7:00 AM)
- Selects cheapest hours until 7:00 AM for morning target
- Implements opportunistic charging:
  - Charge to 80% if price ≤ `input_number.price_threshold_80`
  - Charge to 50% if price ≤ `input_number.price_threshold_50`
- Tracks expected SoC progression throughout the day
- Uses 3-phase, 16A charging (11.04 kW = 14.7% SoC per hour for 75 kWh battery)

**Sensor:** `sensor.optimaler_ladeplan`

**Attributes:**
- `charging_plan`: Array of hourly plan with:
  - `hour`: Hour of day (0-23)
  - `time`: Time in HH:MM format
  - `datetime`: ISO datetime string
  - `price`: Electricity price (EUR/kWh)
  - `should_charge`: Boolean - whether to charge this hour
  - `charge_reason`: 'price_80', 'price_50', 'morning_target', or 'none'
  - `soc_end`: Expected SoC at end of hour (%)
- `hours_needed`: Hours of charging needed to reach morning target
- `next_charging_hour`: Time of next planned charging hour (HH:MM)
- `should_charge_now`: Boolean - should charge in current hour

### 2. `/packages/energy_management/price_charging_automation.yaml`
**Purpose:** Automatically executes the charging plan.

**Automations:**

#### A. Execute Price Plan (`energy_mgmt_execute_price_plan`)
- **Triggers:**
  - Every hour at :00 (time_pattern)
  - When `sensor.optimaler_ladeplan` updates

- **Conditions:**
  - `input_boolean.enable_price_charging` = ON
  - `binary_sensor.auto_angeschlossen` = ON (car connected)
  - `input_boolean.manual_charging_mode` = OFF
  - `binary_sensor.notladung_erforderlich` = OFF (no emergency charging)

- **Actions:**
  - If should charge and not charging:
    - Set charging state to "price"
    - Force 3-phase mode
    - Set charging current to 16A
    - Start charging
  - If should not charge and is charging (in price mode):
    - Stop charging

#### B. Update Price Plan (`energy_mgmt_update_price_plan`)
- **Triggers:**
  - Every hour at :05 (time_pattern)
  - When `sensor.tibber_preisprognose` updates

- **Actions:**
  - Force update of `sensor.optimaler_ladeplan`

### 3. `/dashboards/energy_management_ui.yaml`
**Modified:** Updated dashboard visualization

**Changes:**
- Replaced old "Preisprognose & Ladeplan mit Solarvorhersage" card
- Added new "Optimaler Ladeplan" timetable showing:
  - Time (HH:MM)
  - Price (EUR/kWh)
  - Charging indicator (🟢 = will charge, ⚪ = no charging)
  - Expected SoC at end of hour (%)
  - PV forecast for the hour (kW)
  - Charge reason (Günstig/Sehr günstig/Morgenziel)
- Updated "Nächstes Ladefenster" summary card with:
  - Current charging status
  - Next planned charging hour
  - Hours needed to reach morning target
  - Solar and PV forecast info

## Algorithm Logic

### Priority Order:
1. **Opportunistic Charging (Very Cheap):** If price ≤ `price_threshold_50` and SoC < 50%
2. **Opportunistic Charging (Cheap):** If price ≤ `price_threshold_80` and SoC < 80%
3. **Morning Target:** Charge during cheapest N hours (until 7:00 AM) to reach target SoC

### Calculation Steps (Every Hour):
1. Get current SoC from `sensor.tesla_ladestand`
2. Get target SoC from `input_number.target_soc_morning`
3. Calculate SoC deficit: `target_soc - current_soc`
4. Calculate hours needed: `ceil(deficit / 14.7%)` (14.7% per hour @ 11.04 kW)
5. Build list of available hours from now until 7:00 AM tomorrow
6. Sort available hours by price, select cheapest N hours
7. For each hour in price forecast:
   - Check opportunistic thresholds
   - Check if hour is in selected cheapest hours
   - Update expected SoC

### Charging Parameters:
- **Power:** 11.04 kW (3-phase, 16A)
- **Battery:** 75 kWh (Tesla Model 3)
- **SoC Gain:** 14.7% per hour
- **Max SoC:** 80% (safety limit in algorithm)
- **Morning Target Time:** 7:00 AM

## Testing Instructions

### Step 1: Reload Configuration
```bash
# In Home Assistant Developer Tools → YAML
# Click "Template Entities" → "Reload"
# Click "Automations" → "Reload"
```

Or restart Home Assistant to load new files.

### Step 2: Verify Sensors Exist
Check that these sensors are available:
- `sensor.optimaler_ladeplan`
- `sensor.tibber_preisprognose`
- `sensor.solar_prognose_stundlich`

### Step 3: Load Price Data
Run the script to fetch Tibber prices:
```yaml
# Developer Tools → Services
service: script.calculate_optimal_charging_hours
```

### Step 4: Verify Sensor Data
Check `sensor.optimaler_ladeplan` in Developer Tools → States:
- State should be a recent timestamp
- Attributes should include:
  - `charging_plan`: Array with 24+ hours
  - `hours_needed`: Number > 0 (if SoC < target)
  - `next_charging_hour`: Time string (HH:MM)
  - `should_charge_now`: true or false

### Step 5: Check Dashboard
Navigate to Energy Management dashboard:
- "Nächstes Ladefenster" should show status and next charging time
- "Optimaler Ladeplan" table should show all hours with:
  - Prices
  - Green indicators (🟢) for planned charging hours
  - Expected SoC progression
  - PV forecast values

### Step 6: Enable Price-Based Charging
Set these input helpers:
- `input_boolean.enable_price_charging` → ON
- `input_boolean.manual_charging_mode` → OFF
- `input_number.target_soc_morning` → 80 (or desired %)
- `input_number.price_threshold_80` → 0.28 (or desired EUR/kWh)
- `input_number.price_threshold_50` → 0.30 (or desired EUR/kWh)

### Step 7: Monitor Automation
Watch the automation execute:
1. Connect car to charger
2. Wait for next hour boundary (:00)
3. Check logs: `Settings → System → Logs`
4. Look for: "Price charging started" or "Price charging stopped"
5. Verify charging starts/stops according to plan

## Expected Behavior

### Scenario 1: Low SoC, Morning Target Needed
- Current SoC: 40%
- Target SoC: 80%
- Hours needed: ~3 hours
- System will select 3 cheapest hours between now and 7:00 AM
- Automation will start/stop charging during those hours

### Scenario 2: Opportunistic Charging
- Current SoC: 60%
- Current price: 0.25 EUR/kWh (below threshold_80)
- System will charge even if not needed for morning target
- Will charge up to 80% max

### Scenario 3: Already at Target
- Current SoC: 80%
- No charging needed for morning target
- Will only charge during very cheap hours (price_threshold_50) up to 50%

## Troubleshooting

### Dashboard Shows "Keine Daten verfügbar"
**Solution:** Run `script.calculate_optimal_charging_hours` to fetch Tibber prices

### Charging Doesn't Start
**Check:**
1. `input_boolean.enable_price_charging` is ON
2. `binary_sensor.auto_angeschlossen` is ON (car connected)
3. `input_boolean.manual_charging_mode` is OFF
4. `binary_sensor.notladung_erforderlich` is OFF
5. `sensor.optimaler_ladeplan` attribute `should_charge_now` is true
6. Check automation trace: `Settings → Automations → Energie Management - Preisplan ausführen → Traces`

### Tesla SoC Shows 'unavailable'
**Check:**
1. Tesla Fleet integration is working: `Settings → Devices & Services → Tesla Fleet`
2. Vehicle is awake (not sleeping)
3. Check logs for Tesla API errors

### Charging Plan Empty
**Check:**
1. `sensor.tibber_preisprognose` has `prices_today` attribute with data
2. Run template test in Developer Tools → Template:
```jinja2
{{ state_attr('sensor.tibber_preisprognose', 'prices_today') | length }} prices
{{ state_attr('sensor.optimaler_ladeplan', 'charging_plan') | length }} hours in plan
```

### Automation Triggered But Nothing Happens
**Check:**
1. Easee integration is working
2. Device ID is correct: `4997305f9b10ff58595e095f3bdf74cd`
3. Check `sensor.garage_status` shows correct charging state
4. Review automation traces for condition failures

## Configuration Parameters

### Input Numbers
- `input_number.target_soc_morning`: Morning target SoC (default: 80%)
- `input_number.price_threshold_80`: Price for charging to 80% (default: 0.28 EUR/kWh)
- `input_number.price_threshold_50`: Price for charging to 50% (default: 0.30 EUR/kWh)

### Input Booleans
- `input_boolean.enable_price_charging`: Enable/disable price-based charging
- `input_boolean.manual_charging_mode`: Override with manual mode

## Dependencies

### Required Integrations:
- **Tibber:** For electricity price data
- **Easee:** For charger control
- **Tesla Fleet:** For vehicle SoC data
- **Forecast.Solar** (optional): For PV production forecast

### Required Sensors:
- `sensor.tesla_ladestand`: Tesla battery level
- `sensor.garage_status`: Easee charging status
- `binary_sensor.auto_angeschlossen`: Car connected status

### Required Scripts:
- `script.stop_charging`: Stop charging command
- `script.calculate_optimal_charging_hours`: Fetch Tibber prices

## Future Enhancements

Possible improvements:
1. Support for tomorrow's prices (extend planning beyond midnight)
2. Dynamic SoC per hour calculation based on actual charger capabilities
3. Integration with departure time (not fixed 7:00 AM)
4. Consideration of grid load or CO2 intensity
5. Machine learning for actual charging efficiency
6. Push notifications for charging start/stop

## Notes

- Algorithm does NOT use PV forecast for charging decisions (as requested)
- PV forecast is only shown in dashboard for information
- Maximum SoC is capped at 80% in the algorithm for battery health
- Charging power is fixed at 3-phase 16A (11.04 kW) for grid charging
- Plan updates every hour to recalculate based on current SoC and remaining time
