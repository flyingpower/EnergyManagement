# Energy Management System

A Home Assistant-based smart charging system for a Tesla Model 3, running on Raspberry Pi. It automatically controls an Easee EV charger based on solar PV surplus, electricity price, and user-defined targets.

## Overview

The system uses a **priority-based state machine** to decide when and how to charge the car:

```
Priority 1: Emergency   — SoC < 20%, charge immediately at full power
Priority 2: Manual      — User-controlled charging to a specified target
Priority 3: PV          — Use solar surplus for zero-export charging
Priority 4: Price       — Charge during cheapest hours to meet morning target
Default:    Idle        — No charging
```

The central controller runs every minute, evaluates all conditions top-down, and sets the active charging mode.

---

## System Architecture

### Key Integrations
| Integration | Purpose |
|---|---|
| **Tesla Fleet** | SoC (`sensor.model_3_battery_level`), charging state |
| **Tibber** | Real-time electricity price, grid power & production |
| **Easee** | EV charger control (start/stop/current/phase) |
| **Solcast** | PV production forecast (today & tomorrow) |
| **LaMetric** | Watch display with home energy data |

### State Machine
`input_select.charging_state` values: `idle`, `pv`, `price`, `manual`, `emergency`, `morning_readiness`

---

## Package Files

| File | Contents |
|---|---|
| `automations.yaml` | Central controller, PV adjust loop, notifications, safety automations |
| `inputs.yaml` | All `input_number`, `input_boolean`, `input_select`, `input_datetime` helpers |
| `sensors.yaml` | Core template sensors and binary sensors |
| `scripts.yaml` | Charger control scripts (start/stop/adjust for all modes) |
| `price_charging_automation.yaml` | Price plan execution: start/stop on scheduled hours |
| `optimal_charging_plan.yaml` | Template sensor computing the full charging plan |
| `charging_plan_sensors.yaml` | Tibber price forecast sensor (event-triggered) + plan visualization sensors |
| `pv_charging_helpers.yaml` | PV timer inputs + net grid consumption + target current sensor |
| `solar_forecast_sensors.yaml` | Solcast-based hourly solar forecast + combined plan sensor |
| `tesla_soc_reliability.yaml` | Stores last valid Tesla SoC; alerts on connection loss |
| `lametric.yaml` | LaMetric watch display automation |
| `watermeter.yaml` | Water meter webhook integration |
| `disable_realtime.yaml` | Startup warnings: realtime streaming disabled for stability |
| `price_charging_manual_trigger.yaml` | Script to manually trigger price charging check |
| `charging_plan_trigger_script.yaml` | Script to manually refresh Tibber forecast |
| `solar_forecast_rest.yaml` | Alternative REST-based Forecast.Solar integration (unused/backup) |

### Debug Files (can be removed to simplify)
| File | Contents |
|---|---|
| `charging_debug.yaml` | Debug sensor for price charging conditions |
| `price_charging_debug.yaml` | Debug sensor: should-charge decision breakdown |
| `solar_debug.yaml` | Debug sensor: Forecast.Solar integration check |
| `forecast_solar_check.yaml` | Debug sensor: Forecast.Solar entity explorer |
| `solcast_check.yaml` | Debug sensor: Solcast entity explorer |
| `test_easee.yaml` | One-off test script for Easee current limit |
| `tibber_price_simple.yaml` | Old debugging approach for Tibber prices (superseded) |

---

## Key Entities

### Input Helpers
| Entity | Default | Description |
|---|---|---|
| `input_number.target_soc_morning` | 80% | Target SoC to reach by morning |
| `input_number.morning_target_hour` | 7 | Hour by which morning target must be met |
| `input_number.price_threshold_80` | 0.28 €/kWh | Charge to 80% when price is at or below this |
| `input_number.price_threshold_50` | 0.30 €/kWh | Charge to 50% when price is at or below this |
| `input_number.min_pv_surplus` | 1400 W | Minimum PV surplus to start charging |
| `input_number.pv_charging_buffer` | 200 W | Safety buffer to avoid grid import during PV charging |
| `input_number.pv_hysteresis_time` | 1 min | PV check interval (UI setting, actual logic uses timers) |
| `input_number.pv_start_condition_timer` | — | Seconds surplus has been above threshold (auto-managed) |
| `input_number.pv_stop_condition_timer` | — | Seconds at minimum amps + grid import (auto-managed) |
| `input_number.tesla_soc_last_valid` | — | Last known valid Tesla SoC (fallback when unavailable) |
| `input_number.manual_target_soc` | 80% | Target SoC for manual charging mode |
| `input_boolean.enable_pv_charging` | on | Enable/disable PV charging |
| `input_boolean.enable_price_charging` | on | Enable/disable price-based charging |
| `input_boolean.enable_morning_readiness` | on | Enable/disable morning readiness check & alert |
| `input_boolean.manual_charging_mode` | off | Activate manual charging mode |
| `input_boolean.trigger_price_update` | off | Toggle to manually trigger Tibber price fetch |
| `input_select.charging_state` | idle | Current charging mode (state machine) |
| `input_datetime.manual_deadline` | — | Deadline for manual charging target |

### Computed Sensors
| Sensor | Description |
|---|---|
| `sensor.tesla_ladestand` | Reliable SoC (falls back to `tesla_soc_last_valid` when 0/unavailable) |
| `sensor.verfugbarer_pv_uberschuss` | Available PV surplus in W (net production minus buffer) |
| `sensor.berechneter_ladestrom_pv` | Calculated amps from surplus (1-phase, 6–32A) |
| `sensor.pv_target_charging_current` | Target amps for zero-export (accounting for current charging) |
| `sensor.net_grid_consumption` | Net grid draw: consumption − production |
| `sensor.aktueller_strompreis` | Current Tibber electricity price (EUR/kWh) |
| `sensor.preisniveau` | Price level text: Sehr günstig / Günstig / Normal / Teuer / Sehr teuer |
| `sensor.aktueller_lademodus` | Human-readable current charging mode |
| `sensor.easee_ladegerat_status` | Easee status in German |
| `sensor.aktuelle_ladeleistung` | Current charging power (W) |
| `sensor.benotigte_energie_bis_ziel` | kWh needed to reach morning target |
| `sensor.geschatzte_ladedauer` | Estimated hours to reach target |
| `sensor.optimaler_ladeplan` | Full price-based charging plan; key attribute: `should_charge_now` |
| `sensor.tibber_preisprognose` | Tibber hourly price forecast (populated via `tibber_prices_updated` event) |
| `sensor.solar_prognose_stundlich` | Hourly Solcast PV forecast |
| `sensor.sonnenstunden_prognose` | Predicted sunshine hours today |
| `sensor.pv_prognose_nachste_stunden` | Forecast PV production for next 6 hours (kWh) |
| `binary_sensor.auto_angeschlossen` | True when car is connected to charger |
| `binary_sensor.notladung_erforderlich` | True when SoC < 20% and data is live |
| `binary_sensor.morgenziel_erreichbar` | True when morning target is expected to be met |

---

## Charging Modes in Detail

### PV Charging (Smart with Stability Timers)
Script: `script.adjust_pv_charging_smart`

- Runs every 2 minutes via `energy_mgmt_pv_charging_adjust`
- **Start**: PV surplus ≥ `min_pv_surplus` for **120 seconds** → start at target current (1-phase)
- **Adjust**: While charging, dynamically update current to maintain ~0W net grid consumption
- **Stop**: Charging at minimum 6A **and** importing from grid for **300 seconds** → stop

Goal: Use all available PV without exporting to the grid.

### Price-Based Charging
Sensor: `sensor.optimaler_ladeplan`

The plan computes the cheapest hours to reach `target_soc_morning` by `morning_target_hour`, plus opportunistic charging when price is below thresholds:
- Price ≤ `price_threshold_80` and SoC < 80% → charge (`price_80`)
- Price ≤ `price_threshold_50` and SoC < 50% → charge (`price_50`)
- Hour is in cheapest N hours before target time and SoC < target → charge (`morning_target`)

Tibber prices are fetched at 13:00 (when tomorrow's prices publish) and via the `tibber_prices_updated` event. The price automation (`energy_mgmt_execute_price_plan`) checks every 5 minutes and at the top of each hour.

### Manual Charging
Toggle: `input_boolean.manual_charging_mode`

When active, prefers PV if available, otherwise uses grid at full power (3-phase, 32A) until `manual_target_soc` is reached, after which it stops and sends a notification.

### Emergency Charging
Triggered when `sensor.tesla_ladestand` < 20% and data source is `live`. Starts immediately at maximum power (3-phase, 32A). Sends a high-priority push notification.

### Morning Readiness
Checked at 22:00. If `binary_sensor.morgenziel_erreichbar` is off, sends a push notification and starts `script.start_emergency_morning_charge` to ensure the target is met.

---

## Hardware Assumptions (hard-coded)

| Parameter | Value |
|---|---|
| Tesla battery capacity | 75 kWh |
| Price charging power | 11.04 kW (3-phase, 16A, 230V) |
| Duration estimate power | 7.4 kW |
| Charging efficiency loss | 15% |
| PV charging voltage | 230V (1-phase) |
| PV start stability | 120 s |
| PV stop stability | 300 s |
| Easee device ID | `4997305f9b10ff58595e095f3bdf74cd` |

---

## File Locations

| Location | Purpose |
|---|---|
| `/Volumes/config/packages/energy_management/` | Live installation (Raspberry Pi) |
| `/Volumes/config/configuration.yaml` | Main HA config |
| `/Volumes/config/dashboards/energy_management_ui.yaml` | Dashboard YAML |
| `/Volumes/config/.storage/lovelace.energie_management` | Live Lovelace storage |
| This repo `/packages/energy_management/` | Source of truth (kept in sync) |

---

## Notifications

All notifications use `notify.mobile_app` (group with `mobile_app_z_flip_7`).

Notification triggers:
- Charging started / stopped
- Emergency charging activated
- Tesla connection lost (>15 min)
- Integration unavailable (>10 min)
- Morning target at risk (22:00 check)
- Manual charging target reached
