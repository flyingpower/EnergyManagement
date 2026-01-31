<?php

$button = $_GET['button'];
$action = $_GET['action'];
IPS_LogMessage("Flic", file_get_contents("php://input"));
IPS_LogMessage("Flic", $button.' '.$action);

if ($button == 1) {
    if ($action == 'single') {
        // load with solar
        // http://192.168.0.20:3777/hook/Flic?button=1&action=single
        IPS_LogMessage("Flic","Single click Button 1");
        IPS_RunScript(31000);
        IPS_SetEventActive(32684, true);
    } else if ($action == 'double') {
        // load manually
        // http://192.168.0.20:3777/hook/Flic?button=1&action=double
        IPS_LogMessage("Flic","Double click Button 1");
        IPS_RunScript(31000);
        IPS_SetEventActive(32684, false);
    }
} else if ($button == 2) {
    // http://192.168.0.20:3777/hook/Flic?button=2&action=single
    IPS_LogMessage("Flic","Switching Button 2");
    $id = 16257;
    $status = PHUE_GetState($id);
    PHUE_SwitchMode($id, !$status);
}