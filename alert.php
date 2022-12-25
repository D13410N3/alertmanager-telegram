<?php
# Loading configuration file
$settings = @yaml_parse_file('settings.yaml') or die('Error parsing settings.yaml');

# Defining TG bot token
define('BOT_TOKEN', $settings['telegram']['token']);

# TG library
require_once 'tg.php';

# Parsing incoming event
$input = @file_get_contents('php://input');
$input_json = @json_decode($input, true);

# If debug is enabled, saving dump
if (isset($settings['debug'])) {
    file_put_contents('dumps/'.date('d_m_Y_H_i_s').'.log', $input);
}

if ($input_json === false OR $input_json === null) {
    $message[] = 'Alert received, but JSON-data couldn\'t be parsed';
    $message[] = '<code>'.$input.'</code>';
    sendMessage($settings['receivers']['primary'], implode(PHP_EOL, $message), 'HTML');
} else {
    foreach ($input_json['alerts'] as $key => $alert) {
        $message = [];
        # Checking alert status
        $status = $alert['status'] == 'resolved' ? '🟢' : '';
        if (empty($status)) {
            switch ($alert['labels']['severity']) {
                case 'info':        $status = '🔵';      break;
                case 'warning':     $status = '🟡';      break;
                case 'critical':    $status = '🔴';      break;

                default:            $status = '🟡';      break;
            }
        }

        # Creating alert message
        if (!empty($alert['annotations']['summary'])) {
            $alert_text = $alert['annotations']['summary'];
        } elseif (!empty($alert['annotations']['title']) && !empty($alert['annotations']['description'])) {
            $alert_text = '<b>'.$alert['annotations']['title'].'</b>'.PHP_EOL.$alert['annotations']['description'];
        } else {
            $alert_text = '<i>Empty alert message</i>';
        }

        # Adding additional alert info
        $add_info = [];
        if (!empty($alert['labels']['ip'])) {
            $add_info[] = 'IP: <code>'.$alert['labels']['ip'].'</code>';
        }
        if (!empty($alert['labels']['hostname'])) {
            $add_info[] = 'Hostname: <code>'.$alert['labels']['hostname'].'</code>';
        }
        if (!empty($alert['labels']['target_url'])) {
            $add_info[] = 'Target URL: '.$alert['labels']['target_url'];
        }

        # Formatting labels to a specific string
        $labels = [];
        foreach ($alert['labels'] as $key => $value) {
            $labels[] = $key.'="'.$value.'"';
        }
        $labels_string = '{'.implode(',', $labels).'}';

        # Creating external links
        $links['prometheus'] = '<a href="'.$alert['generatorURL'].'">Source</a>';
        $links['alertmanager'] = '<a href="'.$settings['links']['alertmanager'].'/#/silences/new?filter='.urlencode($labels_string).'">Silence</a>';
        if (isset($settings['links']['grafana'])) {
            $links['grafana'] = '<a href="'.$settings['links']['grafana'].'">Grafana</a>';
        }


        # Creating text message
        $text_message = $status.' '.$alert_text.PHP_EOL.PHP_EOL;
        $text_message .= implode(PHP_EOL, $add_info).PHP_EOL;
        $text_message .= implode(' / ', $links);


        # Finding receivers
        foreach ($alert['labels'] as $key => $value) {
            if (isset($settings['receivers'][$key])) {
                $receivers[$key] = $settings['receivers'][$key];
            }
        }
        # Overriding - adding default primary receiver
        $receivers['primary'] = $settings['receivers']['primary'];

        # Sending message(-s)
        foreach ($receivers as $key => $telegram_id) {
            sendMessage($telegram_id, $text_message, 'HTML');
            sleep(1);
        }

    }
}
