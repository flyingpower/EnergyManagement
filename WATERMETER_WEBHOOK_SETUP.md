# Water Meter Webhook Setup

This integration receives water consumption data from an external water meter device via webhook.

## Webhook Details

**URL:** `http://homeassistant.local:8123/api/webhook/watermeter_data`
**Method:** POST
**Content-Type:** application/json
**Local Network Only:** Yes (webhook only accepts requests from local network)

## Data Format

The water meter should send POST requests with this JSON structure:

```json
{
  "total": 123456,
  "literPerMinute": 12.5
}
```

**Fields:**
- `total` (integer): Total water consumption in liters since meter installation
- `literPerMinute` (float): Current water flow rate in liters per minute

## Example cURL Test

Test the webhook manually:

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"total": 123456, "literPerMinute": 12.5}' \
  http://homeassistant.local:8123/api/webhook/watermeter_data
```

Replace `homeassistant.local` with your Home Assistant IP if needed (e.g., `192.168.0.XXX`).

## Sensors Created

After loading the integration, you'll have these sensors:

### 📊 Dashboard Sensors

1. **sensor.water_meter_total**
   - Total water consumption (liters)
   - Device class: water
   - State class: total_increasing
   - Icon: Counter (mdi:counter)

2. **sensor.water_meter_flow_rate**
   - Current flow rate (L/min)
   - Updates in real-time when water is flowing
   - Icon: Water pump (mdi:water-pump)

3. **sensor.water_meter_daily**
   - Water consumption today (liters)
   - Resets at midnight
   - Device class: water
   - Icon: Check (mdi:water-check)

4. **binary_sensor.water_meter_flowing**
   - ON when water is flowing (flow rate > 0.1 L/min)
   - OFF when no water flow
   - Device class: running
   - Icon: Water pump on/off

### 🔧 Helper Entities (Hidden)

These store raw webhook data (not meant for dashboards):
- `input_number.watermeter_total`
- `input_number.watermeter_flow_rate`

## Dashboard Card Example

Add this to your Lovelace dashboard:

```yaml
type: entities
title: Water Meter
entities:
  - entity: sensor.water_meter_total
    name: Total Consumption
  - entity: sensor.water_meter_daily
    name: Today's Usage
  - entity: sensor.water_meter_flow_rate
    name: Current Flow
  - entity: binary_sensor.water_meter_flowing
    name: Water Flowing
```

Or use a gauge card for flow rate:

```yaml
type: gauge
entity: sensor.water_meter_flow_rate
name: Water Flow
unit: L/min
min: 0
max: 50
severity:
  green: 0
  yellow: 20
  red: 40
```

## Automation Example

Get notified when water flows for more than 30 minutes (potential leak):

```yaml
automation:
  - alias: "Water Meter - Leak Alert"
    trigger:
      - platform: state
        entity_id: binary_sensor.water_meter_flowing
        to: "on"
        for:
          minutes: 30
    action:
      - service: notify.mobile_app
        data:
          title: "⚠️ Potential Water Leak"
          message: >
            Water has been flowing continuously for 30+ minutes.
            Current flow: {{ states('sensor.water_meter_flow_rate') }} L/min
            Total today: {{ states('sensor.water_meter_daily') }} L
```

## Installation

1. Copy `watermeter.yaml` to `/config/packages/energy_management/`
2. Restart Home Assistant
3. Configure your water meter device to send data to the webhook URL
4. Test with the cURL command above
5. Add sensors to your dashboard

## Troubleshooting

### Webhook not receiving data

1. Check Home Assistant logs:
   - Settings → System → Logs
   - Search for "Water Meter webhook"

2. Verify webhook URL is accessible:
   ```bash
   curl http://YOUR_HA_IP:8123/api/webhook/watermeter_data
   ```
   Should return: `{"message": "Webhook watermeter_data is waiting for a trigger"}`

3. Check firewall settings (port 8123 must be open on local network)

### Sensors not updating

1. Check if input_number helpers are being updated:
   - Developer Tools → States
   - Search for `input_number.watermeter_total`

2. Check automation trace:
   - Settings → Automations → "Water Meter - Webhook Receiver"
   - Click "Traces" to see recent webhook calls

### Water meter device configuration

Make sure your water meter device is configured to:
- Use HTTP POST method (not GET)
- Set Content-Type header to `application/json`
- Send JSON payload (not form data)
- Use the correct Home Assistant IP address

## Migration from IP-Symcon

If migrating from IP-Symcon, simply update your water meter device configuration:

**Old (IP-Symcon):**
```
http://SYMCON_IP/hook/watermeter
```

**New (Home Assistant):**
```
http://HOMEASSISTANT_IP:8123/api/webhook/watermeter_data
```

The JSON format remains identical, so no changes needed to the water meter device itself!
