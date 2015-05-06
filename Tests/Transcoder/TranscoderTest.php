<?php

namespace Coshi\Bundle\TranscodeBundle\Tests;

use Coshi\Bundle\TranscodeBundle\Transcoder\Transcoder;

class TranscoderTest extends \PHPUnit_Framework_TestCase
{
    protected $s3;
    protected $transcoder;
    protected $container;
    protected $video;

    protected function setUp()
    {
        $this->s3 = $this->getMockBuilder('Aws\S3\S3Client')
            ->disableOriginalConstructor()
            ->getMock();
        $this->transcoder = $this->getMockBuilder('Aws\ElasticTranscoder\ElasticTranscoderClient')
            ->disableOriginalConstructor()
            ->getMock();
        $this->container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $this->video = $this->getMockBuilder('Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableInterface')
            ->getMock();
    }

    public function testGetVideoForKeyRepositoryType()
    {
        $this->getVideoForKey(array(
            'type' => 'repository',
            'name' => 'repositoryName'
        ), true);
    }

    public function testGetVideoForKeyServiceTypeTest()
    {
        $this->getVideoForKey(array(
            'type' => 'service',
            'name' => 'serviceName'
        ));
    }

    public function testConvertVideo()
    {
        $filename = 'filename';
        $videoFilename = 'VideoFilename';
        $genericPreset = array('name' => 'generic', 'value' => '1351620000001-000010');
        $iphonePreset = array('name' => 'iphone', 'value' => '1351620000001-100010');
        $pipelineId = '1231212';
        $this->video->method('getAmazonTranscodeStatus')
            ->willReturn('uploaded');
        $this->video->method('getFilename')
            ->willReturn($filename);
        $this->video->method('getVideoFilename')
            ->willReturn($videoFilename);

        $collection = $this->getMockBuilder('Guzzle\Common\Collection')
            ->disableOriginalConstructor()
            ->getMock();

        $this->container->method('getParameter')
            ->with('coshi_transcode')
            ->willReturn(array(
                    'aws_transcoder_videos_presets' => array(
                        $genericPreset['name'] => $genericPreset['value'],
                        $iphonePreset['name'] => $iphonePreset['value']
                    ),
                    'aws_transcoder_videos_pipeline_id' => $pipelineId
                )
            );
        $this->transcoder->method('__call')
            ->with(
                'createJob',
                array(
                    array(
                        'PipelineId' => $pipelineId,
                        'Input' => array(
                            'Key' => 'upload/' . $filename
                        ),
                        'Outputs' => array(
                            array(
                                'Key' => $genericPreset['name'] . '/' . $videoFilename,
                                'PresetId' => $genericPreset['value'],
                                'ThumbnailPattern' => 'thumbnail/' . $filename . '/{count}'
                            ),
                            array(
                                'Key' => $iphonePreset['name'] . '/' . $videoFilename,
                                'PresetId' => $iphonePreset['value'],
                                'ThumbnailPattern' => 'thumbnail/' . $filename . '/{count}'
                            )
                        )
                    )
                ))
            ->willReturn($collection);
        $transcoder = new Transcoder($this->container, $this->s3, $this->transcoder);
        $transcoder->setVideo($this->video);
        $transcoder->convert();
    }

    public function testNotUploadedVideoConvert()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();

        $transcoder = new Transcoder($container, $this->s3, $this->transcoder);
        $transcoder->setVideo($this->video);
        $this->assertNull($transcoder->convert());
    }

    public function testGetKeyForVideo()
    {
        $this->video->method('getFilename')
            ->willReturn('filename');


        $this->assertEquals('upload/filename', Transcoder::getKeyForVideo($this->video));
    }

    public function testUpload()
    {
        $bucketId = 'bucketId';
        $filename = 'filename';
        $sourceFile = 'home/server/images/filename.png';
        $objectUrl = 'http://object.url';

        $this->video->method('getVideoFilename')
            ->willReturn($filename);
        $this->video->method('getFilePath')
            ->willReturn($sourceFile);

        $manager = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->container->method('get')
            ->with('doctrine.orm.entity_manager')
            ->willReturn($manager);
        $this->container->method('getParameter')
            ->with('coshi_transcode')
            ->willReturn(array('aws_s3_videos_bucket' => $bucketId));

        $this->s3->method('__call')
            ->with(
                'putObject',
                array(
                    array(
                        'Bucket' => $bucketId,
                        'Key' => 'upload/' . $filename,
                        'SourceFile' => $sourceFile,
                        'ACL' => 'public-read'
                    )
                ))
            ->willReturn(array(
                'ObjectURL' => $objectUrl
            ));

        $this->video->expects($this->exactly(2))
            ->method('setAmazonTranscodeStatus');
        $this->video->expects($this->once())
            ->method('setMediaUrl')
            ->with($objectUrl);

        $transcoder = new Transcoder($this->container, $this->s3, $this->transcoder);
        $transcoder->setVideo($this->video);
        $transcoder->upload();
    }

    public function testUploadForUploadedVideo()
    {
        $this->video->method('getAmazonTranscodeStatus')
            ->willReturn('processing');
        $transcoder = new Transcoder($this->container, $this->s3, $this->transcoder);
        $transcoder->setVideo($this->video);
        $this->assertNull($transcoder->upload());
    }

    public function testGetUrl()
    {
        $this->video->method('getVideoFilename')
            ->willReturn('filename');
        $this->container->method('getParameter')
            ->with('coshi_transcode')
            ->willReturn(array('aws_s3_videos_bucket' => 'bucketID'));
        $this->s3->method('getObjectUrl')
            ->will($this->returnCallback(function($bucket, $key) {
                return $bucket . '/' . $key;
            }));

        $transcoder = new Transcoder($this->container, $this->s3, $this->transcoder);
        $transcoder->setVideo($this->video);
        $this->assertEquals('bucketID/upload/filename', $transcoder->getUrl());
        $this->assertEquals('bucketID/iphonePreset/filename', $transcoder->getUrl('iphonePreset'));
    }

    private function getVideoForKey($config, $repository = false)
    {
        $provider = $this->getMockBuilder('Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableProviderInterface')
            ->setMethods(array('findOneByFileName', 'findAllUnprocessedVideos'))
            ->getMock();

        $manager = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->setMethods(array('getParameter', 'get'))
            ->getMock();

        $container->method('getParameter')
            ->with('coshi_transcode')
            ->willReturn(array('media_provider' => $config));


        $manager->method('getRepository')
            ->willReturn($provider);

        $provider->expects($this->once())
            ->method('findOneByFileName')
            ->with($this->equalTo('filename'));
        if ($repository) {
            $manager->expects($this->once())
                ->method('getRepository')
                ->with($this->equalTo($config['name']));
            $container->method('get')
                ->with('doctrine.orm.entity_manager')
                ->willReturn($manager);
        } else {
            $container->method('get')
                ->with($config['name'])
                ->willReturn($provider);
            $container->expects($this->once())
                ->method('get')
                ->with($this->equalTo($config['name']));
        }

        $transcoder = new Transcoder($container, $this->s3, $this->transcoder);
        $transcoder->getVideoForKey('upload/filename');
    }
}
 