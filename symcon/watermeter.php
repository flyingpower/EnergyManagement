<?php

IPS_LogMessage("Water meter", "Water meter called");
$response = file_get_contents("php://input");
IPS_LogMessage("WebHook WaterMeter", $response);
$data = json_decode($response);
$total = $data->total;
$literPerMinute = $data->literPerMinute;

SetValueInteger(28902, $total);
SetValueFloat(24833, $literPerMinute);