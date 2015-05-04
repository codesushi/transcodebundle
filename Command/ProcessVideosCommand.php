<?php
namespace Coshi\Bundle\TranscodeBundle\Command;

use AppBundle\Entity\Book;
use Coshi\Bundle\TranscodeBundle\Transcoder\Transcoder;
use Coshi\Bundle\TranscodeBundle\Transcoder\TranscodeableProviderInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ProcessVideosCommand extends ContainerAwareCommand
{
    private $videos;
    private $manager;

    protected function configure()
    {
        $this
            ->setName('media:video:process')
            ->setDescription('Upload videos to amazon s3 to be processed by elastic transcoder')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->manager = $this->getContainer()->get('doctrine')->getManager();
        $this->queryVideos();

        if ($this->hasNoVideos()) {
            $output->writeln('No videos to process');
        } else {
            $output->writeln(sprintf('%d videos to process', count($this->videos)));
        }

        foreach ($this->videos as $video) {
            $output->writeln(sprintf('Processing file: %s', realpath($video->getFilePath())));

            $amazonVideo = $this->getContainer()->get('coshi.amazon_transcoder.transcoder');
            $amazonVideo->setVideo($video);

            $output->writeln('  Uploading...');
            $s3Object = $amazonVideo->upload();

            if (is_null($s3Object)) {
                $output->writeln('  This video has been uploaded already!');
            } else {
                $output->writeln('  Upload Finished!');
            }

            $output->writeln('  Converting...');
            $job = $amazonVideo->convert();

            if (is_null($job)) {
                $output->writeln('  This video has been queued for conversion already!');
            } else {
                $output->writeln(sprintf('  Transcoder job created! id: %s', $job['Job']['Id']));
            }
            $book = $this->manager->getRepository('AppBundle:Book')->findOneBy(array('hash' => $video->getCode()));

            if ($book instanceof Book) {
                $book->setTranscodeStatus(Book::TRANSCODE_STATUS_PROCESSING);
            }
        }
        $this->manager->flush();
    }

    private function queryVideos()
    {
        $this->videos = $this->getMediaProvider()->findAllUnprocessedVideos();
    }

    public function hasNoVideos()
    {
        return empty($this->videos);
    }

    private function getConfig($key)
    {
        return $this->getContainer()->getParameter('coshi_transcode')[$key];
    }

    /**
     * @return TranscodeableProviderInterface
     * @throws InvalidConfigurationException
     */
    private function getMediaProvider()
    {
        $providerType = $this->getConfig('media_provider')['type'];
        $providerName = $this->getConfig('media_provider')['name'];
        $provider = null;

        if (TranscodeableProviderInterface::RETRIEVER_TYPE_REPOSITORY === $providerType) {
            $provider = $this->manager->getRepository($providerName);
        }

        if (TranscodeableProviderInterface::RETRIEVER_TYPE_SERVICE === $providerType) {
            $provider = $this->getContainer()->get($providerName);
        }

        if (!$provider instanceof TranscodeableProviderInterface) {
            throw new InvalidConfigurationException(sprintf('Provider class should exist and implement "%s" interface',
                'Coshi\\Bundle\\TranscodeBundle\\Transcoder\\TranscodeableProviderInterface'));
        }

        return $provider;
    }
}
