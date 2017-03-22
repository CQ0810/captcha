<?php
use Captcha\CaptchaBuilder;

echo count(CaptchaBuilder::create()->build()->getFingerprint());
