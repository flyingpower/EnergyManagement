var Gpio = require('onoff').Gpio;
var request = require('request');
var url = 'http://HOMEASSISTANT_IP:8123/api/webhook/watermeter_data ';
var total = 0;
var timer = null;
var timestamp = 0;

console.log("Water Meter ready...");

var button = new Gpio(3, 'in', 'rising');
button.watch((err, value) => {
    if (value == 1) {
        total++;
        send(1);
    }
});

function send(step) {
    var now = new Date().getTime();
    var diff = now - timestamp;
    var lpm = step * 60000/diff;
    timestamp = now;
    var json = {
        total: total,
        literPerMinute: lpm
    };

    if (timer) {
        clearTimeout(timer);
    }
    if (step > 0) {
        timer = setTimeout(() => {
            send(0);
        }, 20000);
    }
    console.log("lpm: " + lpm + ", total: " + total);
    request.post(
        url,
        {
            json: json,
            'headers':{}
        },
        function (error, response, body) {
            if (!error && response.statusCode == 200) {
                console.log("Done");
            } else if (error) {
                console.log(error);
            } else {
                console.log(response);
            }
        }
    );
}
