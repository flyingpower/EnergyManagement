# Quick Start Guide

This guide will get your Energy Management System up and running in 30 minutes.

## Prerequisites Checklist

Before starting, ensure you have:

- [ ] Home Assistant installed and running
- [ ] Tesla vehicle registered to your account
- [ ] Easee charger installed and configured
- [ ] Tibber electricity contract with API access
- [ ] Mobile app connected to Home Assistant

## Step 1: Install Integrations (10 min)

### 1.1 Tesla Integration

1. Open Home Assistant
2. Go to **Configuration** → **Integrations**
3. Click **Add Integration**
4. Search for "**Tesla**"
5. Enter your Tesla account email and password
6. Complete 2FA if prompted
7. Wait for integration to load your vehicle

**Verify:** Developer Tools → States → Search "tesla" → Should see `sensor.tesla_battery`

### 1.2 Tibber Integration

1. Get your API token:
   - Visit https://developer.tibber.com/
   - Log in with your Tibber account
   - Copy your Personal Access Token

2. In Home Assistant:
   - Go to **Configuration** → **Integrations**
   - Click **Add Integration**
   - Search for "**Tibber**"
   - Paste your API token
   - Click Submit

**Verify:** Developer Tools → States → Search "tibber" → Should see `sensor.tibber_prices`

### 1.3 Easee Integration (HACS)

1. Install HACS if not already installed
2. Go to **HACS** → **Integrations**
3. Click **Explore & Download Repositories**
4. Search for "**Easee**"
5. Click **Download**
6. Restart Home Assistant
7. Go to **Configuration** → **Integrations**
8. Click **Add Integration**
9. Search for "**Easee**"
10. Enter your Easee credentials

**Verify:** Developer Tools → States → Search "easee" → Should see `sensor.easee_power`

## Step 2: Copy Configuration Files (5 min)

### 2.1 Copy All Files

Copy these directories to your Home Assistant config folder:

```bash
/config/
├── packages/energy_management/  (all 4 YAML files)
├── dashboards/energy_management.yaml
└── configuration.yaml (merge with existing)
```

**Important:** If you already have a `configuration.yaml`, merge the content instead of replacing!

### 2.2 Create Secrets File

1. Copy `secrets.yaml.example` to `secrets.yaml`
2. Fill in your actual values:

```yaml
latitude: 52.5200       # Your actual latitude
longitude: 13.4050      # Your actual longitude
elevation: 34           # Your actual elevation

tibber_api_token: "your_actual_token_here"
tesla_username: "your@email.com"
tesla_password: "your_password"
easee_username: "your@email.com"
easee_password: "your_password"
```

## Step 3: Update Entity Names (10 min)

Your entity names might be different. Let's find them:

### 3.1 Find Your Entity IDs

1. Go to **Developer Tools** → **States**
2. Search for each integration and note the entity IDs:

| Integration | Search Term | Example Entity | Your Entity |
|-------------|-------------|----------------|-------------|
| Tesla Battery | "tesla battery" | `sensor.tesla_battery` | ____________ |
| Tesla Charging | "tesla charg" | `sensor.tesla_charging_state` | ____________ |
| Tibber Prices | "tibber price" | `sensor.tibber_prices` | ____________ |
| Tibber Consumption | "tibber" | `sensor.tibber_net_consumption` | ____________ |
| Easee Power | "easee power" | `sensor.easee_power` | ____________ |
| Easee Status | "easee status" | `sensor.easee_status` | ____________ |

### 3.2 Replace in Configuration

Open `packages/energy_management/sensors.yaml` and replace:

```yaml
# Before
state: >
  {{ states('sensor.tesla_battery') | float(0) }}

# After (if your entity is different)
state: >
  {{ states('sensor.your_actual_tesla_entity') | float(0) }}
```

**Quick Find & Replace:**
- Search: `sensor.tesla_battery` → Replace with your entity
- Search: `sensor.tesla_charging_state` → Replace with your entity
- Search: `sensor.tibber_prices` → Replace with your entity
- Search: `sensor.tibber_net_consumption` → Replace with your entity
- Search: `sensor.easee_power` → Replace with your entity
- Search: `sensor.easee_status` → Replace with your entity

## Step 4: Configure Notifications (2 min)

Find your mobile device name:

1. Go to **Configuration** → **Integrations**
2. Click on **Mobile App**
3. Note your device name (e.g., "iphone_von_michael")

Edit `configuration.yaml`:

```yaml
notify:
  - name: mobile_app
    platform: group
    services:
      - service: mobile_app_iphone_von_michael  # Your device here
```

## Step 5: Restart & Verify (3 min)

### 5.1 Check Configuration

1. Go to **Configuration** → **Server Controls**
2. Click **Check Configuration**
3. Wait for validation
4. If errors appear, review the error message and fix

### 5.2 Restart Home Assistant

1. Click **Restart** in Server Controls
2. Wait 2-3 minutes for restart
3. Reload the Home Assistant page

### 5.3 Verify Entities Created

Go to **Developer Tools** → **States** and search for:

- `input_number.target_soc_morning` ✅
- `input_boolean.manual_charging_mode` ✅
- `input_select.charging_state` ✅
- `sensor.aktueller_lademodus` ✅
- `sensor.verfugbarer_pv_uberschuss` ✅

If you see these, installation was successful! 🎉

## Step 6: Add Dashboard (5 min)

### Option A: Quick Method (Storage Mode)

1. Go to **Overview** (main dashboard)
2. Click the **three dots** (top right)
3. Click **Edit Dashboard**
4. Click the **three dots** again → **Raw configuration editor**
5. Copy the entire content of `dashboards/energy_management.yaml`
6. Create a **new view** in your dashboard
7. Paste the content
8. Click **Save**

### Option B: Separate Dashboard (YAML Mode)

Uncomment in `configuration.yaml`:

```yaml
lovelace:
  mode: yaml
  dashboards:
    energy-management:
      mode: yaml
      title: Energie Management
      icon: mdi:car-electric
      filename: dashboards/energy_management.yaml
```

Restart Home Assistant.

Dashboard will be at: `http://your-ha:8123/lovelace/energie-management`

## Step 7: Test the System (5 min)

### 7.1 Enable PV Charging

1. Open the Energy Management dashboard
2. Go to **Einstellungen** section
3. Toggle **PV-Laden aktiviert** ON

### 7.2 Simulate PV Surplus (Testing)

To test without actual solar:

1. Create a helper: **Configuration** → **Helpers** → **Add Helper**
2. Select **Number**
3. Name: "Mock PV Surplus"
4. Min: 0, Max: 5000, Step: 100
5. Set to 2000W

6. Edit `sensors.yaml` temporarily:

```yaml
- name: "Verfügbarer PV-Überschuss"
  state: >
    {{ states('input_number.mock_pv_surplus') | float(0) }}
```

7. Restart Home Assistant

Now you can control PV surplus for testing!

### 7.3 Monitor Logs

Watch the logs for any errors:

1. Go to **Configuration** → **Logs**
2. Look for errors related to "energy_mgmt"
3. If errors appear, check entity names in configuration

## Quick Troubleshooting

### "Entity not found" errors

**Problem:** Wrong entity IDs in configuration

**Solution:**
1. Find correct entity: Developer Tools → States
2. Replace in `sensors.yaml`, `scripts.yaml`, `automations.yaml`
3. Restart Home Assistant

### Charging not starting

**Problem:** Car not detected as connected

**Solution:**
1. Check `binary_sensor.auto_angeschlossen`
2. Should be "on" when car is plugged in
3. If not, check Tesla integration

### No notifications

**Problem:** Mobile app service name incorrect

**Solution:**
1. Check Configuration → Integrations → Mobile App
2. Update service name in `configuration.yaml`
3. Test: Developer Tools → Services → notify.mobile_app

### Dashboard not showing

**Problem:** Lovelace mode conflict

**Solution:**
- Use Option A (Storage Mode) if unsure
- Don't mix storage and YAML mode

## Next Steps

Once everything is working:

1. **Adjust settings** to your preferences
2. **Monitor for 24 hours** to ensure stability
3. **Fine-tune thresholds** based on your usage
4. **Review notifications** to avoid alert fatigue
5. **Check cost savings** after first month

## Support

If you encounter issues:

1. Read the full [README.md](README.md)
2. Check Home Assistant logs
3. Verify all integrations are working
4. Review entity names match your system

## Success Checklist

Before considering the installation complete:

- [ ] All three integrations installed and working
- [ ] Configuration files copied and entities created
- [ ] Entity names updated to match your system
- [ ] Notifications configured and tested
- [ ] Dashboard visible and displaying data
- [ ] PV charging enabled and monitoring
- [ ] Car connected and detected
- [ ] No errors in Home Assistant logs
- [ ] Charging state shows "idle" or active mode
- [ ] Mobile notifications received

🎉 **Congratulations!** Your Energy Management System is ready!
