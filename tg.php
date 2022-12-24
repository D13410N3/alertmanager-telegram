<?php
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

# Общая функция для запроса к API Телеграмма
function apiRequest($toSend = array() , $json = true) {
    $ch = curl_init(API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($toSend) : $toSend);
    if ($json) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json"
        ));
    }
    $a = curl_exec($ch);
    return json_decode($a, true);
}

# простая отправка сообщения
function sendMessage($id_chat, $text, $mark = '', $id_message = '') {
    $length = mb_strlen($text, 'utf-8');
    if ($length <= 4096) {
        $toSend = array(
            'method' => 'sendMessage',
            'chat_id' => $id_chat,
            'text' => $text
        );
        !empty($id_message) ? $toSend['reply_to_message_id'] = $id_message : '';
        !empty($mark) ? $toSend['parse_mode'] = $mark : '';
        return apiRequest($toSend);
    } else {
        $messNum = ceil($length / 4096);

        for ($i = 1;$i <= $messNum;$i++) {
            $start = ($i - 1) * 4096;
            $txt = mb_substr($text, $start, 4096, 'utf-8');
            $toSend = array(
                'method' => 'sendMessage',
                'chat_id' => $id_chat,
                'text' => $txt
            );
            !empty($id_message) ? $toSend['reply_to_message_id'] = $id_message : '';
            !empty($mark) ? $toSend['parse_mode'] = $mark : '';
            return apiRequest($toSend);
            sleep(1);
        }
    }
}

