Create an energy management system for Home Assistant. Main focus is on charging the car for the lowest cost.

Following integrations exist:
- Tesla as EV to be charged
- Tibber to have hourly rates for grid energy
- easee EV Charger to charge the car
There is also a PV system, but it is not integrated. The energy surplus can be retrieved from Tibber.

Home Assistant is installed at http://192.168.0.136:8123

Rules to be implemented
#### Scenario 1: PV charging
- Check the energy surplus and start loading the car.
- Adapt the charging speed to stay as close to the 0W consumption/production for the houshold
- Stop the chargin process if there is not enough energy

#### Scenario 2: Charge from the grid when energy is cheap
- Make sure that the car has a defined SOC in the morning at 7.00 am
- If energy price is below 30 cent/kwh, charge the car up to 50%
- If energy price is below 28 cent/kwh, charge the car up to 80%

#### Scenario 3: Manual charging
- Charge the car manually to a certain SOC until a certain time of the day
- The user can define this in the Home Assistant app
- Adapt the loading speed depending on the energy surplus and energy price if necessary
