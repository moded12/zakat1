<?php
require_once __DIR__ . '/whatsapp_config.php';

// Twilio PHP SDK (تنزيل من composer: composer require twilio/sdk)
require_once __DIR__ . '/../../vendor/autoload.php';

use Twilio\Rest\Client;

function sendWhatsAppMsg($phone, $message)
{
    $client = new Client(TWILIO_SID, TWILIO_AUTH_TOKEN);

    try {
        $to = 'whatsapp:' . $phone; // مثال: whatsapp:+9627xxxxxxx
        $sent = $client->messages->create(
            $to,
            [
                'from' => TWILIO_WHATSAPP_FROM,
                'body' => $message
            ]
        );
        return $sent->sid; // إذا نجح الإرسال، يرجع SID
    } catch (Exception $e) {
        // سجل الخطأ أو أرسل رسالة
        return false;
    }
}
?>