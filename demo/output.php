<?php
use Captcha\CaptchaBuilder;

header('Content-type: image/jpeg');
CaptchaBuilder::create()->build()->output();
