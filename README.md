CodeShushi's TranscodeBundle
=============

TranscodeBundle - for symfony 2.1

TranscodeBundle from codesushi.co 

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
Add to your composer.json :
```yaml
coshi_transcode:
    media_class: #path to media class
    aws_access_key_id: #access key
    aws_secret_key: #secret key
    aws_s3_videos_bucket: #bucket name
    aws_transcoder_videos_pipeline_id: #pipeline id
    aws_transcoder_videos_presets:
        generic: #generic presets
        iphone4: #iphone4 presets
    aws_transcoder_region: #region
    media_provider:
        type: #repository or service
        name: #name of provider
```

