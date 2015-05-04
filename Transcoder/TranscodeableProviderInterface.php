<?php
namespace Coshi\Bundle\TranscodeBundle\Transcoder;

interface TranscodeableProviderInterface
{
    const RETRIEVER_TYPE_REPOSITORY = 'repository';
    const RETRIEVER_TYPE_SERVICE = 'service';

    public function findAllUnprocessedVideos();

    public function findOneByFilename($fileName);
}
