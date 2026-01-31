<?php

IPS_LogMessage("WebHook RAW", file_get_contents("php://input"));
$button = $_GET['button'];
$action = $_GET['action'];
echo "MyStrom button: " . $button;
echo "action: ". $action;

if ($button == 1) {
    IPS_LogMessage("Switching Button 1");
    $id = 57390;
    $status = PHUE_GetState($id);
    PHUE_SwitchMode($id, !$status);
}
if ($button == 2) {
    $parent = 22013;
    $state = IPS_GetObjectIDByName("Position", $parent);
    $roller = IPS_GetObjectIDByName("Roller", $parent);
    $value = GetValueInteger($state);
    if ($value > 0) {
        RequestAction($state, 0);
        WFC_PushNotification(20449, 'Zu!', 'Fenster Büro zu', '', 0);
    } else {
        RequestAction($roller, 0);
        WFC_PushNotification(20449, 'Auf!', 'Fenster Büro auf', '', 0);
    }
}
