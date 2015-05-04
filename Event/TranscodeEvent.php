<?php
namespace Coshi\Bundle\TranscodeBundle\Event;

use Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableInterface;
use Symfony\Component\EventDispatcher\Event;

class TranscodeEvent extends Event
{
    protected $subject;

    public function __construct(TranscodeableInterface $subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return TranscodeableInterface
     */
    public function getSubject()
    {
        return $this->subject;
    }
} 