<?php
namespace Coshi\Bundle\TranscodeBundle\Controller;

use Coshi\Bundle\TranscodeBundle\Event\TranscodeEvent;
use Coshi\Bundle\TranscodeBundle\Event\TranscodeEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Coshi\Bundle\TranscodeBundle\Transcoder\Transcoder;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class NotifyController extends Controller
{
    public function notifyAction(Request $request)
    {
        $logger = $this->get('logger');
        $postBody = file_get_contents('php://input');

        $notification = json_decode($postBody, true);

        if (!$notification) {
            return new Response();
        }

        if ($notification['Type'] == 'SubscriptionConfirmation') {
            $logger->info('sns_subscribe_url: ' . $notification['SubscribeURL']);
        } else {
            $message = json_decode($notification['Message'], true);
            $filename = basename($message['input']['key']);
            $amazonVideo = $this->get('coshi.amazon_transcoder.transcoder');
            $amazonVideo->setVideo($filename);
            $amazonVideo->complete($message);

            $logger->info('sns_transcode_complete: ' . $message['jobId']);
            $this->get('event_dispatcher')->dispatch(TranscodeEvents::TRANSCODE_COMPLETED, new TranscodeEvent($amazonVideo->getVideo()));
        }

        return new Response();
    }
}
