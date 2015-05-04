CodeShushi's TranscodeBundle
=============

TranscodeBundle - for symfony 2.1

TranscodeBundle is designed to work with [Amazon Elastic Transcoder](https://aws.amazon.com/elastictranscoder/?nc1=f_ls)
> Amazon Elastic Transcoder is media transcoding in the cloud. It is designed to be a highly scalable, easy to use and a cost effective way for developers and businesses to convert (or “transcode”) media files from their source format into versions that will playback on devices like smartphones, tablets and PCs.

Install
============
Add to your composer.json :
```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/codesushi/transcodebundle
    }
]
```
Configure
============
Add to your config.yml :
```yaml
coshi_transcode:
    media_class: #path to media class. Must to implement TranscodeableInterface
    aws_access_key_id: #access key
    aws_secret_key: #secret key
    aws_s3_videos_bucket: #bucket name
    aws_transcoder_videos_pipeline_id: #pipeline id
    aws_transcoder_videos_presets:
        generic: #generic presets
        iphone4: #iphone4 presets
    aws_transcoder_region: #region
    media_provider: #must to implement TranscodeableProviderInterface
        type: #repository or service
        name: #name of provider
```
Read more about [pipeline](http://docs.aws.amazon.com/elastictranscoder/latest/developerguide/working-with-pipelines.html) and [presets](http://docs.aws.amazon.com/elastictranscoder/latest/developerguide/gs-4-create-a-preset.html)

Usage
===========
Example:

```php
$amazonVideo = $this->getContainer()->get('coshi.amazon_transcoder.transcoder');
$amazonVideo->setVideo($video); //$video can be a Media entity (TranscodeableProviderInterface) or a s3 key 
$s3Object = $amazonVideo->upload();
if (is_null($s3Object)) {
   //This video has been uploaded already!
} else {
   //Upload Finished!
}

$job = $amazonVideo->convert();

if (is_null($job)) {
   //This video has been queued for conversion already!
} else {
   //Transcoder job created! id: ' . $job['Job']['Id']
}
```
Instead of using a service in your code you can just to use ProcessVideosCommand for uploading and converting videos. Bundle also has its own type of events.
