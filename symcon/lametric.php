<?php

$power = // tibber_power - tibber_power_production
$charging = // aktuelle_ladeleistung
$tempOut = // wetterstation_pro_wetter_status_temperature
$tempIn = // shellyhtg3_8cbfeaa52b90_temperature
$soc = // tesla_ladestand
$co2 = // luftqualitat_buro_carbon_dioxide

$json = '{
    "frames": [
        {
            "goalData": {
                "start": -7000,
                "current": '.$power.',
                "end": 7000,
                "unit": "W"
            },
            "icon": 630
        },
        {
            "goalData": {
                "start": 0,
                "current": '.$charging.',
                "end": 11,
                "unit": "kW"
            },
            "icon": 1172
        },
        {
            "goalData": {
                "start": -10,
                "current": '.$tempOut.',
                "end": 30,
                "unit": "°"
            },
            "icon": 39039
        },
        {
            "goalData": {
                "start": 15,
                "current": '.$tempIn.',
                "end": 30,
                "unit": "°"
            },
            "icon": 8135
        },
        {
            "goalData": {
                "start": 0,
                "current": '.$soc.',
                "end": 100,
                "unit": "%"
            },
            "icon": 1095
        },
        {
            "goalData": {
                "start": 0,
                "current": '.$co2.',
                "end": 2000,
                "unit": "p"
            },
            "icon": 30662
        }
    ]
}';

# Create a connection
$token = "OGI3YTQzMjIzNmNhOTAyYWExNmY0Nzg4ZjZmOTg5ZjRhZDc1MGZiYTAwM2FiZmQxMGEzOWQxMzA4MjQ4OTA5OQ==";
$ch = curl_init('https://192.168.0.104:4343/api/v1/dev/widget/update/com.lametric.f3bc8426115aba47518c57183e80a369/1');
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json','X-Access-Token: '.$token,'Cache-Control: no-cache'));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

# Get the response
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response);

var_dump($data);
/*

curl -X POST \
-H "Accept: application/json" \
-H "X-Access-Token: OGI3YTQzMjIzNmNhOTAyYWExNmY0Nzg4ZjZmOTg5ZjRhZDc1MGZiYTAwM2FiZmQxMGEzOWQxMzA4MjQ4OTA5OQ==" \
-H "Cache-Control: no-cache" \
-d '' \
https://<device local ip>:4343/api/v1/dev/witget/update/com.lametric.f3bc8426115aba47518c57183e80a369
*/
