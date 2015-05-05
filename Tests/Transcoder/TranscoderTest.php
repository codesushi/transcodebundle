<?php

namespace Coshi\Bundle\TranscodeBundle\Tests;

use Coshi\Bundle\TranscodeBundle\Transcoder\Transcoder;

class TranscoderTest extends \PHPUnit_Framework_TestCase
{
    protected $s3;
    protected $transcoder;

    protected function setUp()
    {
        $this->s3 = $this->getMockBuilder('Aws\S3\S3Client')
            ->disableOriginalConstructor()
            ->getMock();
        $this->transcoder = $this->getMockBuilder('Aws\ElasticTranscoder\ElasticTranscoderClient')
            ->disableOriginalConstructor()
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

    public function testGetKeyForVideo()
    {
        $video = $this->getMockBuilder('Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableInterface')
            ->getMock();
        $video->method('getFilename')
            ->willReturn('filename');

        $this->assertEquals('upload/filename', Transcoder::getKeyForVideo($video));
    }

    public function testUpload()
    {
        $bucketId = 'bucketId';
        $filename = 'filename';
        $sourceFile = 'home/server/images/filename.png';
        $objectUrl = 'http://object.url';

        $video = $this->getMockBuilder('Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableInterface')
            ->getMock();
        $video->method('getVideoFilename')
            ->willReturn($filename);
        $video->method('getFilePath')
            ->willReturn($sourceFile);

        $manager = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $container->method('get')
            ->with('doctrine.orm.entity_manager')
            ->willReturn($manager);
        $container->method('getParameter')
            ->with('coshi_transcode')
            ->willReturn(array('aws_s3_videos_bucket' => $bucketId));

        $this->s3->method('putObject')
            ->with(array(
                'Bucket' => $bucketId,
                'Key' => 'upload/' . $filename,
                'SourceFile' => $sourceFile,
                'ACL' => 'public-read'
            ))
            ->willReturn(array(
                'ObjectURL' => $objectUrl
            ));

        $video->expects($this->exactly(2))
            ->method('setAmazonTranscodeStatus');
        $video->expects($this->once())
            ->method('setMediaUrl')
            ->with($objectUrl);

        $transcoder = new Transcoder($container, $this->s3, $this->transcoder);
        $transcoder->setVideo($video);
        $transcoder->upload();
    }

    public function testUploadForUploadedVideo()
    {
        $video = $this->getMockBuilder('Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableInterface')
            ->getMock();
        $video->method('getAmazonTranscodeStatus')
            ->willReturn('processing');
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $transcoder = new Transcoder($container, $this->s3, $this->transcoder);
        $transcoder->setVideo($video);
        $this->assertNull($transcoder->upload());
    }

    public function testGetUrl()
    {
        $video = $this->getMockBuilder('Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableInterface')
            ->getMock();
        $video->method('getVideoFilename')
            ->willReturn('filename');
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $container->method('getParameter')
            ->with('coshi_transcode')
            ->willReturn(array('aws_s3_videos_bucket' => 'bucketID'));
        $this->s3->method('getObjectUrl')
            ->will($this->returnCallback(function($bucket, $key) {
                return $bucket . '/' . $key;
            }));

        $transcoder = new Transcoder($container, $this->s3, $this->transcoder);
        $transcoder->setVideo($video);
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
 