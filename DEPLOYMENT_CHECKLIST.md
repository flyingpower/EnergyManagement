# Energy Management System - Deployment Checklist

Use this checklist to track your deployment progress.

## Pre-Deployment Verification

### Prerequisites
- [ ] Home Assistant installed and running at http://192.168.0.136:8123
- [ ] Tesla Integration installed and working
- [ ] Tibber Integration installed with API token
- [ ] Easee Integration installed (via HACS)
- [ ] Mobile App connected to Home Assistant
- [ ] SSH/File access to Home Assistant config directory

### Required Entity Verification

Access: **Developer Tools** → **States**

#### Tesla Entities
- [ ] `sensor.model_3_battery_level` exists
- [ ] `sensor.model_3_charging` exists
- [ ] `number.model_3_charge_current` exists (max value should be 16)
- [ ] `number.model_3_charge_limit` exists
- [ ] `switch.model_3_charge` exists

#### Tibber Entities
- [ ] `sensor.tibber_electricity_price` exists and shows current price
- [ ] `sensor.tibber_power` exists and shows consumption
- [ ] `sensor.tibber_power_production` exists (0 if no PV)

#### Easee/Garage Entities
- [ ] `sensor.garage_power` exists
- [ ] `sensor.garage_status` exists
- [ ] `sensor.garage_session_energy` exists
- [ ] `switch.garage_charger_enabled` exists

## Backup Current Configuration

- [ ] Create backup of `/config` directory
  ```bash
  # Via Home Assistant UI:
  # Configuration → Backups → Create backup
  ```
- [ ] Backup existing `configuration.yaml` (if present)
  ```bash
  cp /config/configuration.yaml /config/configuration.yaml.backup
  ```

## File Deployment

### 1. Copy Configuration Files

- [ ] Create `/config/packages/` directory (if not exists)
  ```bash
  mkdir -p /config/packages
  ```

- [ ] Copy energy management package
  ```bash
  cp -r packages/energy_management /config/packages/
  ```

- [ ] Verify files copied:
  - [ ] `/config/packages/energy_management/inputs.yaml`
  - [ ] `/config/packages/energy_management/sensors.yaml`
  - [ ] `/config/packages/energy_management/automations.yaml`
  - [ ] `/config/packages/energy_management/scripts.yaml`

### 2. Copy Dashboard

- [ ] Create `/config/dashboards/` directory (if not exists)
  ```bash
  mkdir -p /config/dashboards
  ```

- [ ] Copy dashboard file
  ```bash
  cp dashboards/energy_management.yaml /config/dashboards/
  ```

### 3. Update Main Configuration

- [ ] Edit `/config/configuration.yaml`
- [ ] Add packages line (if not present):
  ```yaml
  homeassistant:
    packages: !include_dir_named packages
  ```
- [ ] Verify recorder configuration present (for history):
  ```yaml
  recorder:
    purge_keep_days: 7
  ```

### 4. Create Secrets File

- [ ] Copy secrets template
  ```bash
  cp secrets.yaml.example /config/secrets.yaml
  ```

- [ ] Edit `/config/secrets.yaml` with actual values:
  - [ ] Latitude
  - [ ] Longitude
  - [ ] Elevation
  - [ ] Tibber API token
  - [ ] Tesla credentials
  - [ ] Easee credentials

- [ ] Set correct permissions:
  ```bash
  chmod 600 /config/secrets.yaml
  ```

### 5. Configure Notifications

- [ ] Find your mobile device name:
  - Configuration → Integrations → Mobile App → Note device ID

- [ ] Update notification service in `configuration.yaml`:
  ```yaml
  notify:
    - name: mobile_app
      platform: group
      services:
        - service: mobile_app_YOUR_DEVICE  # Replace with actual device
  ```

## Configuration Validation

### 1. Check Configuration

- [ ] Go to **Configuration** → **Server Controls**
- [ ] Click **Check Configuration**
- [ ] Wait for validation to complete
- [ ] **Result:** No errors shown ✅

If errors appear:
- [ ] Review error message
- [ ] Check entity IDs match your system
- [ ] Verify YAML syntax (indentation, no tabs)
- [ ] Fix errors and re-check

### 2. Restart Home Assistant

- [ ] Click **Restart** in Server Controls
- [ ] Wait 2-3 minutes for restart
- [ ] Reload browser page
- [ ] **Result:** Home Assistant accessible ✅

## Post-Deployment Verification

### 1. Verify Input Helpers Created

**Configuration** → **Helpers** → Filter by "energie"

- [ ] `input_number.target_soc_morning` (Ziel-Ladestand Morgen)
- [ ] `input_number.manual_target_soc` (Manueller Ziel-Ladestand)
- [ ] `input_number.price_threshold_50` (Preisschwelle 50%)
- [ ] `input_number.price_threshold_80` (Preisschwelle 80%)
- [ ] `input_number.min_pv_surplus` (Min. PV-Überschuss)
- [ ] `input_number.pv_charging_buffer` (PV-Ladepuffer)
- [ ] `input_number.pv_hysteresis_time` (PV-Hysterese Zeit)
- [ ] `input_boolean.manual_charging_mode` (Manueller Lademodus)
- [ ] `input_boolean.enable_pv_charging` (PV-Laden aktiviert)
- [ ] `input_boolean.enable_price_charging` (Preisbasiertes Laden)
- [ ] `input_boolean.enable_morning_readiness` (Morgen-Bereitschaft)
- [ ] `input_datetime.manual_deadline` (Manueller Zeitpunkt)
- [ ] `input_select.charging_state` (Ladezustand)

### 2. Verify Template Sensors

**Developer Tools** → **States** → Search for:

- [ ] `sensor.aktueller_lademodus`
- [ ] `sensor.verfugbarer_pv_uberschuss`
- [ ] `sensor.tesla_ladestand`
- [ ] `sensor.tesla_ladestatus`
- [ ] `sensor.easee_ladegerat_status`
- [ ] `sensor.aktuelle_ladeleistung`
- [ ] `sensor.aktueller_strompreis`
- [ ] `sensor.berechneter_ladestrom_pv`
- [ ] `sensor.benotigte_energie_bis_ziel`
- [ ] `sensor.geschatzte_ladedauer`
- [ ] `sensor.preisniveau`
- [ ] `binary_sensor.auto_angeschlossen`
- [ ] `binary_sensor.notladung_erforderlich`
- [ ] `binary_sensor.morgenziel_erreichbar`

### 3. Verify Automations Loaded

**Configuration** → **Automations** → Search "Energie Management"

- [ ] Energie Management - Zentralsteuerung
- [ ] Energie Management - PV Ladestrom anpassen
- [ ] Energie Management - Optimale Ladezeiten berechnen
- [ ] Energie Management - Morgen-Bereitschaft prüfen
- [ ] Energie Management - Manuelle Ladung aktualisieren
- [ ] Energie Management - Manuelle Ladung abgeschlossen
- [ ] Energie Management - Benachrichtigung Ladung gestartet
- [ ] Energie Management - Benachrichtigung Ladung abgeschlossen
- [ ] Energie Management - Notladung Warnung
- [ ] Energie Management - Auto getrennt
- [ ] Energie Management - Integrationsfehler

**All automations should be enabled (blue switch icon)**

### 4. Verify Scripts Loaded

**Developer Tools** → **Services** → Search for "script."

- [ ] `script.adjust_pv_charging`
- [ ] `script.calculate_optimal_charging_hours`
- [ ] `script.start_price_charging`
- [ ] `script.start_emergency_morning_charge`
- [ ] `script.execute_manual_charging`
- [ ] `script.start_emergency_charging`
- [ ] `script.stop_charging`

### 5. Check Dashboard

- [ ] Navigate to: `http://192.168.0.136:8123/lovelace/energie-management`
- [ ] **Result:** Dashboard loads without errors
- [ ] All sections visible:
  - [ ] Übersicht (Overview)
  - [ ] Manuelle Steuerung (Manual Control)
  - [ ] Einstellungen (Settings)
  - [ ] Detaillierter Status (Detailed Status)
  - [ ] Verlauf (History)

### 6. Verify No Errors in Logs

- [ ] **Configuration** → **Logs**
- [ ] Filter for: "energy_mgmt" or "error"
- [ ] **Result:** No critical errors ✅

Expected warnings are OK:
- Template rendering warnings (normal on first load)
- Missing history data (normal for new sensors)

## Functional Testing

### 1. Test Car Detection

- [ ] Ensure Tesla is connected to charger
- [ ] Check: `binary_sensor.auto_angeschlossen` = **on**
- [ ] Dashboard shows "Auto angeschlossen"
- [ ] Battery level displays correctly

### 2. Test PV Surplus Calculation

**Developer Tools** → **Template**

Test template:
```jinja2
{% set power_consumption = states('sensor.tibber_power') | float(0) %}
{% set power_production = states('sensor.tibber_power_production') | float(0) %}
{% set net_consumption = power_consumption - power_production %}
{% if net_consumption < 0 %}
  PV Surplus: {{ (net_consumption | abs - 200) | round(0) }} W
{% else %}
  No surplus (consuming {{ net_consumption }} W from grid)
{% endif %}
```

- [ ] Result shows correct calculation
- [ ] Compare with dashboard value
- [ ] Values match ✅

### 3. Test Manual Charging

- [ ] Open Dashboard → Manuelle Steuerung
- [ ] Set "Ziel-Ladestand" to 60%
- [ ] Set deadline to tomorrow
- [ ] Enable "Manueller Lademodus"
- [ ] **Result:**
  - [ ] `input_select.charging_state` changes to "manual"
  - [ ] Dashboard shows "Manuell" mode
  - [ ] Automation triggers (check logs)

- [ ] Disable manual mode
- [ ] **Result:** State returns to appropriate mode (pv/price/idle)

### 4. Test Price Monitoring

- [ ] Check: `sensor.aktueller_strompreis` has valid value
- [ ] Check: `sensor.preisniveau` shows appropriate level
- [ ] Dashboard displays current price
- [ ] Price history graph shows data (may take time)

### 5. Test Notifications

**Developer Tools** → **Services**

Service: `notify.mobile_app`
Data:
```yaml
title: "Test Notification"
message: "Energy Management System is working!"
```

- [ ] Click "Call Service"
- [ ] **Result:** Notification received on mobile device ✅

### 6. Test Charging Control (CAREFUL!)

⚠️ **Only if car is connected and you want to test actual charging**

- [ ] Ensure car is connected
- [ ] Set manual target SOC to current SOC + 5%
- [ ] Enable manual mode
- [ ] **Wait 5 minutes** (automation cycle)
- [ ] **Result:**
  - [ ] Charging starts
  - [ ] `sensor.model_3_charging` = "Charging"
  - [ ] Notification received
  - [ ] Dashboard shows charging status

- [ ] Disable manual mode to stop
- [ ] **Result:** Charging stops

## Configuration Tuning

### 1. Adjust PV Settings

Current values (dashboard or Configuration → Helpers):

- [ ] Review `min_pv_surplus` (default: 1400W)
  - Increase if charging starts too early
  - Decrease if you have more PV available

- [ ] Review `pv_charging_buffer` (default: 200W)
  - Safety margin to prevent grid consumption

- [ ] Review `pv_hysteresis_time` (default: 5 min)
  - Time below threshold before stopping

### 2. Adjust Price Thresholds

- [ ] Review `price_threshold_50` (default: 0.30 EUR/kWh)
  - Compare with your average Tibber price
  - Set to ~80% of average for good deals

- [ ] Review `price_threshold_80` (default: 0.28 EUR/kWh)
  - Set to ~70% of average for excellent deals

### 3. Adjust Morning Readiness

- [ ] Review `target_soc_morning` (default: 80%)
  - Set based on typical daily usage

- [ ] Check automation time (default: 7:00 AM)
  - Edit in `automations.yaml` if different

## Monitoring Period

### Day 1-3: Initial Monitoring

- [ ] **Check logs daily**
  - Configuration → Logs → Filter "energy_mgmt"
  - Look for recurring errors

- [ ] **Monitor charging state**
  - Dashboard → Übersicht → Ladezustand
  - Should change based on conditions

- [ ] **Verify PV charging** (if sunny)
  - PV surplus should trigger charging
  - Current should adjust with surplus

- [ ] **Check notifications**
  - Charging start/stop notifications working
  - Emergency alerts (if SOC < 20%)

### Week 1: Fine-Tuning

- [ ] **Review charging patterns**
  - Dashboard → Verlauf
  - Check energy charged per session

- [ ] **Optimize PV settings**
  - Adjust thresholds based on actual surplus
  - Minimize grid consumption

- [ ] **Optimize price settings**
  - Review Tibber price patterns
  - Adjust thresholds for your usage

- [ ] **Check cost savings**
  - Compare with previous charging costs
  - Expected: 30-40% reduction

## Troubleshooting Reference

### Issue: Entities Not Found

**Symptoms:** Errors in logs about missing entities

**Solution:**
1. [ ] Verify entity IDs: Developer Tools → States
2. [ ] Compare with DEPLOYMENT.md entity table
3. [ ] Update entity IDs in configuration files
4. [ ] Check configuration
5. [ ] Restart Home Assistant

### Issue: PV Charging Not Starting

**Symptoms:** Surplus available but charging doesn't start

**Check:**
- [ ] Car connected? `binary_sensor.auto_angeschlossen` = on
- [ ] PV enabled? `input_boolean.enable_pv_charging` = on
- [ ] Sufficient surplus? `sensor.verfugbarer_pv_uberschuss` ≥ 1400W
- [ ] Manual mode off? `input_boolean.manual_charging_mode` = off
- [ ] Current charging state? `input_select.charging_state`

**Solution:**
1. [ ] Check all conditions above
2. [ ] Review logs for automation triggers
3. [ ] Manually trigger: Services → `script.adjust_pv_charging`
4. [ ] Lower `min_pv_surplus` if needed

### Issue: Charging Not Stopping

**Symptoms:** Charging continues when it shouldn't

**Check:**
- [ ] Current state: `input_select.charging_state`
- [ ] Is emergency mode active? (SOC < 20%)
- [ ] Is morning readiness active?

**Solution:**
1. [ ] Manual stop: Services → `script.stop_charging`
2. [ ] Check automation logs
3. [ ] Verify `switch.model_3_charge` responds
4. [ ] Review automation conditions

### Issue: No Notifications

**Symptoms:** No mobile notifications received

**Check:**
- [ ] Mobile app service name correct in configuration.yaml
- [ ] Mobile app integration active

**Solution:**
1. [ ] Configuration → Integrations → Mobile App → Verify device
2. [ ] Test: Services → `notify.mobile_app` → Send test
3. [ ] Update device name in configuration.yaml
4. [ ] Restart Home Assistant

### Issue: Template Errors

**Symptoms:** Template rendering errors in logs

**Solution:**
1. [ ] Developer Tools → Template
2. [ ] Test problematic template individually
3. [ ] Check entity states exist and have values
4. [ ] Verify syntax (quotes, braces, filters)
5. [ ] Common fix: Add `| float(0)` or `| default('')` fallbacks

## Support Resources

- [ ] **README.md** - Complete feature documentation
- [ ] **DEPLOYMENT.md** - Entity ID mappings and configuration notes
- [ ] **QUICKSTART.md** - Alternative quick installation guide
- [ ] **IMPLEMENTATION_SUMMARY.md** - Overview of what was built

### Home Assistant Resources

- [ ] **Developer Tools** → **Template** - Test template syntax
- [ ] **Developer Tools** → **States** - Check entity values
- [ ] **Developer Tools** → **Services** - Test service calls
- [ ] **Configuration** → **Logs** - View errors and warnings
- [ ] **Logbook** - View automation history

## Final Verification

### System Health Check

All items should be ✅ before considering deployment complete:

- [ ] ✅ All configuration files deployed
- [ ] ✅ Configuration check passes without errors
- [ ] ✅ Home Assistant restarted successfully
- [ ] ✅ All 13 input helpers created
- [ ] ✅ All 14 template sensors working
- [ ] ✅ All 11 automations loaded and enabled
- [ ] ✅ All 7 scripts available
- [ ] ✅ Dashboard loads without errors
- [ ] ✅ Mobile notifications working
- [ ] ✅ Car detected when connected
- [ ] ✅ PV surplus calculates correctly
- [ ] ✅ Charging state shows appropriate mode
- [ ] ✅ No critical errors in logs
- [ ] ✅ Test charging cycle completed successfully

## Deployment Status

**Started:** _________________ (Date/Time)

**Completed:** _________________ (Date/Time)

**Deployed by:** _________________

**Notes:**
```
_____________________________________________________________________________

_____________________________________________________________________________

_____________________________________________________________________________
```

**Status:**
- [ ] ✅ **DEPLOYED SUCCESSFULLY**
- [ ] ⚠️ **DEPLOYED WITH ISSUES** (describe above)
- [ ] ❌ **DEPLOYMENT FAILED** (describe above)

---

**Version:** 1.0.0
**Last Updated:** 2026-01-19
**System:** Energy Management for Home Assistant
