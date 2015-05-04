<?php
namespace Coshi\Bundle\TranscodeBundle\Transcoder;

use Symfony\Component\DependencyInjection\Container;

use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Aws\S3\S3Client;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class Transcoder
{
    private $container;
    /**
     * @var TranscodeableInterface
     */
    private $video;
    private $bucket;
    private $pipeline;
    private $presets;
    private $s3;
    private $transcoder;
    private $s3Object;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->createAWSclients();
    }

    public function setVideo($video)
    {
        // $video can be a Media entity or a s3 key (string)
        if (is_string($video)) {
            $this->video = $this->getVideoForKey($video);
        } else {
            $this->video = $video;
        }
    }

    public function upload()
    {
        if ($this->video->getAmazonTranscodeStatus() !== null) {
            return;
        }

        $this->updateVideoStatus('uploading');

        $this->s3Object = $this->s3->putObject(array(
            'Bucket'     => $this->bucket,
            'Key'        => $this->getKey(),
            'SourceFile' => $this->video->getFilePath(),
            'ACL'        => 'public-read',
        ));

        $this->updateVideoStatus('uploaded');
        $this->updateVideoMediaUrl($this->s3Object['ObjectURL']);

        return $this->s3Object;
    }

    public function convert()
    {
        if ($this->video->getAmazonTranscodeStatus() !== 'uploaded') {
            return;
        }

        return $this->createTranscoderJob();
    }

    public function complete($message)
    {
        $this->updateThumbnailPath($message['outputs'][0]['thumbnailPattern']);
        $this->video->setAmazonUrl($this->getAmazonBasePath() . $message['outputs'][1]['key']);
        $this->updateVideoStatus('converted');
    }

    public function getUrl($presetName = null)
    {
        $key = $this->getKey();

        if (!is_null($presetName)) {
            $key = $this->getKey($presetName);
        }

        $url = $this->s3->getObjectUrl($this->bucket, $key);

        return $url;
    }

    /**
     * @return TranscodeableInterface
     */
    public function getVideo()
    {
        return $this->video;
    }

    private function createAWSclients()
    {
        $key = $this->getConfig('aws_access_key_id');
        $secret = $this->getConfig('aws_secret_key');

        $this->bucket = $this->getConfig('aws_s3_videos_bucket');
        $this->pipeline = $this->getConfig('aws_transcoder_videos_pipeline_id');
        $this->presets = $this->getConfig('aws_transcoder_videos_presets');

        $this->s3 = S3Client::factory(array(
            'key'    => $key,
            'secret' => $secret,
        ));

        $this->transcoder = ElasticTranscoderClient::factory(array(
            'key'    => $key,
            'secret' => $secret,
            'region' => $this->getConfig('aws_transcoder_region'),
        ));
    }

    private function getKey($preset = null)
    {
        $preset = $preset ? $preset : 'upload';

        return $preset . '/' . $this->video->getVideoFilename($preset);
    }

    public static function getKeyForVideo(TranscodeableInterface $video)
    {
        return 'upload/' . $video->getFilename();
    }

    public function getVideoForKey($key)
    {
        $filename = str_replace('upload/', '', $key);

        return $this->getVideoProvider()->findOneByFileName($filename);
    }

    private function updateVideoStatus($status)
    {
        $this->video->setAmazonTranscodeStatus($status);
        $this->save();
    }

    private function updateVideoMediaUrl($url)
    {
        $this->video->setMediaurl($url);
        $this->save();
    }

    private function createTranscoderJob()
    {
        $output = pathinfo($this->video->getFilename(), PATHINFO_FILENAME);
        $outputs = array();
        foreach ($this->presets as $type => $preset) {
            $outputs[] = array(
                'Key' => $type . '/' . $this->video->getVideoFilename($type),
                'PresetId' => $preset,
                'ThumbnailPattern' => 'thumbnail/' . $output . '/{count}',
            );
        }

        $input = array(
            'Key' => $this->getKeyForVideo($this->video),
        );

        return $this->transcoder->createJob(array(
            'PipelineId' => $this->pipeline,
            'Input' => $input,
            'Outputs' => $outputs,
        ))->toArray();
    }

    private function updateThumbnailPath($path)
    {
        $base = $this->getAmazonBasePath();
        $this->video->setThumbnailPath($base . str_replace('{count}', '', $path) . '00002.png');
    }

    private function getAmazonBasePath()
    {
        return sprintf('https://s3-%s.amazonaws.com/%s/', $this->getConfig('aws_transcoder_region'), $this->getConfig('aws_s3_videos_bucket'));
    }

    private function save()
    {
        $this->getManager()->persist($this->video);
        $this->getManager()->flush();
    }

    private function getConfig($key)
    {
        return $this->container->getParameter('coshi_transcode')[$key];
    }

    private function getManager()
    {
        return $this->container->get('doctrine')->getManager();
    }

    /**
     * @return TranscodeableProviderInterface
     * @throws InvalidConfigurationException
     */
    private function getVideoProvider()
    {
        $providerType = $this->getConfig('media_provider')['type'];
        $providerName = $this->getConfig('media_provider')['name'];
        $provider = null;

        if (TranscodeableProviderInterface::RETRIEVER_TYPE_REPOSITORY === $providerType) {
            $provider = $this->getManager()->getRepository($providerName);
        }

        if (TranscodeableProviderInterface::RETRIEVER_TYPE_SERVICE === $providerType) {
            $provider = $this->container->get($providerName);
        }

        if (!$provider instanceof TranscodeableProviderInterface) {
            throw new InvalidConfigurationException(sprintf('Provider class should exist and implement "%s" interface',
                'Coshi\\Bundle\\TranscodeBundle\\Transcoder\\TranscodeableProviderInterface'));
        }

        return $provider;
    }
}
