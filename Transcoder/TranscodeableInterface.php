<?php
namespace Coshi\Bundle\TranscodeBundle\Transcoder;

interface TranscodeableInterface
{
    public function getFilename();

    public function getVideoFilename($preset = null);

    public function getFilePath();

    public function setMediaurl($mediaurl);

    public function setAmazonUrl($amazonUrl);

    public function setAmazonTranscodeStatus($amazonTranscodeStatus);

    public function getAmazonTranscodeStatus();

    public function setThumbnailPath($thumbnailPath);

    public function getThumbnailPath();
}
