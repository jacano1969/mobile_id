<?php

require_once('../../config.php');
require_once('auth.php');
require_once('mobile_id_form.php');

// Login steps
define('INSER_NAME_OR_PHONE', 1);
define('WAIT_FOR_PIN1', 2);
define('MOBILE_ID_ERROR', 9);

$context = get_context_instance(CONTEXT_SYSTEM);

$PAGE->set_url("$CFG->httpswwwroot/auth/mobile_id/login.php");
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');

$PAGE->navbar->add(get_string('loginwithmobileid', 'auth_mobile_id'));
$PAGE->set_heading(get_string('loginwithmobileid', 'auth_mobile_id'));

$form = new auth_mobile_id_form();
$login = new auth_plugin_mobile_id();

$step = INSER_NAME_OR_PHONE;

$loginsesscode = (int) optional_param('startlogin', false, PARAM_NUMBER);
$waitsesscode = (int) optional_param('waitmore', false, PARAM_NUMBER);
$timeout = optional_param('timeout', false, PARAM_BOOL);
$error = optional_param('error', false, PARAM_BOOL);
$nouserdata = optional_param('nouserdata', false, PARAM_BOOL);
$multiplenumbers = optional_param('multiplenumbers', false, PARAM_BOOL);

if ($USER->id) { // User already logged in
    redirect('/login/');

} else if ($loginsesscode) { // Mobile-ID autentication successful
    $login->login($loginsesscode);

} else if ($error or $timeout or $nouserdata or $multiplenumbers) {
   $step = MOBILE_ID_ERROR;

} else if ($waitsesscode) { // After no Javascript status check
    $step = WAIT_FOR_PIN1;
    $sesscode = $waitsesscode;
    $controlcode = $login->get_control_code($sesscode);

} else if ($fromform = $form->get_data()) {
    // Start Mobile-ID login...
    $step = WAIT_FOR_PIN1;
    $PAGE->requires->js('/auth/mobile_id/status_update.js');
    $sesscode = $login->start_authenticate($fromform->mobile_id);
    if (!$login->status_okay($sesscode)) {
        throw new Exception('Mobile-ID not working.');
    }
    $controlcode = $login->get_control_code(intval($sesscode));
    // Now start checking status with AJAX...

} else
    $form->set_data($fromform);

// Outuput start...
echo $OUTPUT->header();

switch ($step) {

    case INSER_NAME_OR_PHONE:
        echo $OUTPUT->box(get_string('insertnameor', 'auth_mobile_id'));
        $form->display();
        break;

    case WAIT_FOR_PIN1:
        echo $OUTPUT->box_start();
        echo get_string('check_control_code', 'auth_mobile_id') . '<strong>' . $controlcode . '</strong><br />';
        echo get_string('then_insert_pin1', 'auth_mobile_id') . '<br />';
        echo get_string('waiting_for_mobile_id', 'auth_mobile_id');
        echo "<span id=\"hideWithJavascript\">... <a href=\"/auth/mobile_id/ajax.php?noajax=1&sesscode=$sesscode\">"
            . get_string('manual_update', 'auth_mobile_id') . "</a></span>";
        echo $OUTPUT->box_end();
	echo "<div style=\"display: none;\" id=\"sesscode\">$sesscode</div>";
        break;

    case MOBILE_ID_ERROR:
        if ($timeout)
            echo $OUTPUT->box(get_string('timeout', 'auth_mobile_id'));
        else if ($error)
            echo $OUTPUT->box(get_string('error', 'auth_mobile_id'));
        else if ($nouserdata)
            echo $OUTPUT->box(get_string('no_user_data', 'auth_mobile_id'));
        else if ($multiplenumbers)
            echo $OUTPUT->box(get_string('multiple_numbers', 'auth_mobile_id'));

        echo "<div><a href=\"/auth/mobile_id/login.php\">"
            . get_string('try_again', 'auth_mobile_id') . "</a></div>";
        break;

    default:
        throw new Exception('Undefined Mobile-ID login step');
}

echo $OUTPUT->footer();

